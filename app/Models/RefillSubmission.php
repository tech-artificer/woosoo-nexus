<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Database\Factories\RefillSubmissionFactory;

/**
 * App\Models\RefillSubmission
 *
 * Durable refill submission guard for idempotent refill processing.
 * Tracks state machine: NEW → PROCESSING → POS_CREATED → MIRRORED → PRINT_EVENT_CREATED → COMPLETED
 * 
 * The unique constraint on (device_id, order_id, client_submission_id) ensures
 * that retries with the same idempotency key never duplicate POS ordered_menu rows.
 */
class RefillSubmission extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): RefillSubmissionFactory
    {
        return RefillSubmissionFactory::new();
    }

    protected $table = 'refill_submissions';

    protected $fillable = [
        'device_id',
        'order_id',
        'client_submission_id',
        'status',
        'print_event_id',
        'pos_ordered_menu_ids',
        'response_payload',
        'response_status',
        'error_message',
        'started_at',
        'pos_created_at',
        'mirrored_at',
        'print_event_created_at',
        'completed_at',
        'failed_at',
    ];

    protected $casts = [
        'pos_ordered_menu_ids' => 'array',
        'response_payload' => 'array',
        'started_at' => 'datetime',
        'pos_created_at' => 'datetime',
        'mirrored_at' => 'datetime',
        'print_event_created_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    /**
     * Valid state transitions
     */
    public const STATES = [
        'NEW',
        'PROCESSING',
        'POS_CREATED',
        'MIRRORED',
        'PRINT_EVENT_CREATED',
        'COMPLETED',
        'FAILED',
    ];

    /**
     * State transitions that are allowed
     */
    private const ALLOWED_TRANSITIONS = [
        'NEW' => ['PROCESSING', 'FAILED'],
        'PROCESSING' => ['POS_CREATED', 'FAILED'],
        'POS_CREATED' => ['MIRRORED', 'FAILED'],
        'MIRRORED' => ['PRINT_EVENT_CREATED', 'FAILED'],
        'PRINT_EVENT_CREATED' => ['COMPLETED', 'FAILED'],
        'COMPLETED' => [],
        'FAILED' => ['PROCESSING'], // Allow retry from FAILED
    ];

    /**
     * Device that made this submission
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'id');
    }

    /**
     * Order being refilled
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(DeviceOrder::class, 'order_id', 'id');
    }

    /**
     * Associated print event (if created)
     */
    public function printEvent(): BelongsTo
    {
        return $this->belongsTo(PrintEvent::class, 'print_event_id', 'id');
    }

    /**
     * Transition to a new state if allowed
     */
    public function transitionTo(string $newState, ?string $errorMessage = null): bool
    {
        $currentState = $this->status;
        
        if (!in_array($newState, self::STATES, true)) {
            throw new \InvalidArgumentException("Invalid state: {$newState}");
        }

        $allowed = self::ALLOWED_TRANSITIONS[$currentState] ?? [];
        if (!in_array($newState, $allowed, true)) {
            throw new \InvalidArgumentException(
                "Invalid state transition: {$currentState} → {$newState}"
            );
        }

        $this->status = $newState;

        // Update timestamp fields based on state
        switch ($newState) {
            case 'PROCESSING':
                $this->started_at ??= now();
                break;
            case 'POS_CREATED':
                $this->pos_created_at = now();
                break;
            case 'MIRRORED':
                $this->mirrored_at = now();
                break;
            case 'PRINT_EVENT_CREATED':
                $this->print_event_created_at = now();
                break;
            case 'COMPLETED':
                $this->completed_at = now();
                break;
            case 'FAILED':
                $this->failed_at = now();
                if ($errorMessage) {
                    $this->error_message = $errorMessage;
                }
                break;
        }

        return $this->save();
    }

    /**
     * Check if this submission can be safely replayed (idempotent response available)
     */
    public function canReplay(): bool
    {
        return $this->status === 'COMPLETED' && !empty($this->response_payload);
    }

    /**
     * Check if this submission is currently being processed
     */
    public function isProcessing(): bool
    {
        return in_array($this->status, ['PROCESSING', 'POS_CREATED', 'MIRRORED', 'PRINT_EVENT_CREATED'], true);
    }

    /**
     * Check if this submission is in a terminal state
     */
    public function isTerminal(): bool
    {
        return in_array($this->status, ['COMPLETED', 'FAILED'], true);
    }

    /**
     * Store POS ordered_menu IDs to prevent duplicate inserts
     */
    public function recordPosItems(array $orderedMenuIds): void
    {
        $this->pos_ordered_menu_ids = $orderedMenuIds;
        $this->save();
    }

    /**
     * Get cached response for replay
     */
    public function getCachedResponse(): ?array
    {
        if (!$this->canReplay()) {
            return null;
        }

        return [
            'body' => $this->response_payload,
            'status' => $this->response_status ?? 200,
        ];
    }

    /**
     * Cache response for future replays
     */
    public function cacheResponse(array $payload, int $status = 200): void
    {
        $this->response_payload = $payload;
        $this->response_status = $status;
        $this->save();
    }
}
