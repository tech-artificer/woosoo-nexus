<?php

namespace App\Models;

use App\Casts\UtcDateTimeCast;
use App\Enums\PrintEventStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * App\Models\PrintEvent
 *
 * attempts: server-side retry counter incremented by PrintEventService::ack() and ::fail().
 * attempt_count: device-reported attempts from relay fail payloads; only updated when fail() receives a value.
 * These can diverge, so backend retry logic should read attempts.
 */
class PrintEvent extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'print_events';
    protected $fillable = [
        'device_order_id',
        'printer_id',
        'printer_name',                // NEW: Human-readable printer name
        'event_type',
        'status',                      // State machine: pending|reserved|printing|printed|failed
        'reserved_by_device_id',       // Device that reserved this job
        'reserved_at',                 // When the job was reserved
        'meta',
        'is_acknowledged',
        'acknowledged_at',
        'acknowledged_by_device_id',   // NEW: Track which relay device acked (audit trail)
        'attempts',                    // Backend-managed retry counter.
        'attempt_count',               // Device-reported attempts from relay payload.
        'last_error',
        'failed_at',
        'backend_status',              // Task 2.3: backend broadcast lifecycle
        'broadcast_at',                // Task 2.3: when the backend last broadcast this event
        'retry_count',                 // Task 2.3: backend re-broadcast counter (≠ device-ack 'attempts')
        'idempotency_key',             // WS2: Idempotency key for print events
        'client_submission_id',        // WS2: Client submission ID for tracking
        'refill_number',               // WS2: Refill number for refill events
    ];

    protected $casts = [
        'meta' => 'array',
        'status' => PrintEventStatus::class,
        'reserved_at' => UtcDateTimeCast::class,
        'is_acknowledged' => 'boolean',
        'acknowledged_at' => UtcDateTimeCast::class,
        'attempts' => 'integer',           // Backend-managed retry counter.
        'attempt_count' => 'integer',      // Device-reported attempts from relay payload.
        'failed_at' => UtcDateTimeCast::class,
        'broadcast_at' => UtcDateTimeCast::class,
        'retry_count' => 'integer',
        'refill_number' => 'integer',
    ];

    public function deviceOrder(): BelongsTo
    {
        return $this->belongsTo(DeviceOrder::class, 'device_order_id', 'id');
    }

    /**
     * B2: Audit trail relationship - which relay device acknowledged this print event.
     * Links to the Device that sent the ack, creating an immutable audit trail.
     * 
     * @return BelongsTo
     */
    public function acknowledgedByDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'acknowledged_by_device_id', 'id');
    }

    /**
     * WS2: Relationship to print event items
     * 
     * @return HasMany
     */
    public function printEventItems(): HasMany
    {
        return $this->hasMany(\App\Models\PrintEventItem::class, 'print_event_id', 'id');
    }
}
