<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceOrder;
use App\Models\RefillSubmission;
use App\Models\PrintEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Durable Refill Submission Guard
 *
 * Prevents duplicate POS ordered_menu rows by tracking refill submission state
 * in a database table with row-level locking.
 *
 * State Machine:
 *   NEW → PROCESSING → POS_CREATED → MIRRORED → PRINT_EVENT_CREATED → COMPLETED
 *                        ↓              ↓                ↓
 *                     FAILED ←────── FAILED ←──────── FAILED
 *
 * Key guarantees:
 * 1. Same client_submission_id + device_id + order_id is globally unique (DB constraint)
 * 2. POS insert only happens once per submission (tracked by pos_ordered_menu_ids)
 * 3. Response is cached for idempotent replay
 * 4. 409 returned if same submission is already processing
 */
class DurableRefillGuard
{
    /**
     * Attempt to start a new refill submission.
     *
     * @return array{
     *   submission: RefillSubmission,
     *   is_new: bool,
     *   can_replay: bool,
     *   cached_response: array|null,
     *   is_processing: bool
     * }
     *
     * @throws \RuntimeException if submission is processing (409 should be returned)
     */
    public function startSubmission(
        Device $device,
        DeviceOrder $order,
        string $clientSubmissionId
    ): array {
        return DB::transaction(function () use ($device, $order, $clientSubmissionId) {
            // Try to find existing submission with lock
            $submission = RefillSubmission::where([
                'device_id' => $device->id,
                'order_id' => $order->id,
                'client_submission_id' => $clientSubmissionId,
            ])->lockForUpdate()->first();

            if ($submission) {
                // Existing submission found
                if ($submission->canReplay()) {
                    return [
                        'submission' => $submission,
                        'is_new' => false,
                        'can_replay' => true,
                        'cached_response' => $submission->getCachedResponse(),
                        'is_processing' => false,
                    ];
                }

                if ($submission->isProcessing()) {
                    return [
                        'submission' => $submission,
                        'is_new' => false,
                        'can_replay' => false,
                        'cached_response' => null,
                        'is_processing' => true,
                    ];
                }

                // Terminal state (COMPLETED without response, or FAILED) - can retry
                if ($submission->status === 'FAILED') {
                    $submission->transitionTo('PROCESSING');
                    return [
                        'submission' => $submission,
                        'is_new' => false,
                        'can_replay' => false,
                        'cached_response' => null,
                        'is_processing' => false,
                    ];
                }

                // COMPLETED without cached response - treat as retry
                return [
                    'submission' => $submission,
                    'is_new' => false,
                    'can_replay' => false,
                    'cached_response' => null,
                    'is_processing' => false,
                ];
            }

            // Create new submission
            $submission = RefillSubmission::create([
                'device_id' => $device->id,
                'order_id' => $order->id,
                'client_submission_id' => $clientSubmissionId,
                'status' => 'NEW',
            ]);

            $submission->transitionTo('PROCESSING');

            return [
                'submission' => $submission,
                'is_new' => true,
                'can_replay' => false,
                'cached_response' => null,
                'is_processing' => false,
            ];
        });
    }

    /**
     * Mark that POS ordered_menu rows have been created.
     * Stores the ordered_menu IDs to prevent duplicate inserts on retry.
     *
     * @param RefillSubmission $submission
     * @param array<int> $orderedMenuIds
     * @throws Throwable
     */
    public function markPosCreated(RefillSubmission $submission, array $orderedMenuIds): void
    {
        DB::transaction(function () use ($submission, $orderedMenuIds) {
            $submission->refresh();
            
            if ($submission->status === 'POS_CREATED') {
                // Already marked - verify IDs match
                $existingIds = $submission->pos_ordered_menu_ids ?? [];
                if ($existingIds === $orderedMenuIds) {
                    Log::info('Refill submission already has POS items recorded', [
                        'submission_id' => $submission->id,
                        'ordered_menu_ids' => $orderedMenuIds,
                    ]);
                    return;
                }
                
                // Mismatch - this shouldn't happen with proper idempotency
                Log::warning('Refill submission POS IDs mismatch', [
                    'submission_id' => $submission->id,
                    'existing_ids' => $existingIds,
                    'new_ids' => $orderedMenuIds,
                ]);
            }

            $submission->recordPosItems($orderedMenuIds);
            $submission->transitionTo('POS_CREATED');

            Log::info('Refill submission marked POS_CREATED', [
                'submission_id' => $submission->id,
                'ordered_menu_count' => count($orderedMenuIds),
            ]);
        });
    }

    /**
     * Mark that local mirror has been completed.
     */
    public function markMirrored(RefillSubmission $submission): void
    {
        $submission->refresh();
        $submission->transitionTo('MIRRORED');

        Log::info('Refill submission marked MIRRORED', [
            'submission_id' => $submission->id,
        ]);
    }

    /**
     * Mark that print event has been created.
     */
    public function markPrintEventCreated(RefillSubmission $submission, PrintEvent $printEvent): void
    {
        DB::transaction(function () use ($submission, $printEvent) {
            $submission->refresh();
            $submission->print_event_id = $printEvent->id;
            $submission->save();
            $submission->transitionTo('PRINT_EVENT_CREATED');

            Log::info('Refill submission marked PRINT_EVENT_CREATED', [
                'submission_id' => $submission->id,
                'print_event_id' => $printEvent->id,
            ]);
        });
    }

    /**
     * Mark submission as completed with cached response for replay.
     */
    public function markCompleted(RefillSubmission $submission, array $responsePayload, int $status = 200): void
    {
        DB::transaction(function () use ($submission, $responsePayload, $status) {
            $submission->refresh();
            $submission->cacheResponse($responsePayload, $status);
            $submission->transitionTo('COMPLETED');

            Log::info('Refill submission marked COMPLETED', [
                'submission_id' => $submission->id,
                'response_status' => $status,
            ]);
        });
    }

    /**
     * Mark submission as failed.
     */
    public function markFailed(RefillSubmission $submission, string $errorMessage): void
    {
        try {
            $submission->refresh();
            $submission->transitionTo('FAILED', $errorMessage);

            Log::warning('Refill submission marked FAILED', [
                'submission_id' => $submission->id,
                'error' => $errorMessage,
            ]);
        } catch (Throwable $e) {
            // Don't throw - failure tracking is best-effort
            Log::error('Failed to mark refill submission as failed', [
                'submission_id' => $submission->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get or create submission for a refill request.
     * Returns appropriate response if submission is already completed or processing.
     *
     * @return array{
     *   proceed: bool,
     *   submission: RefillSubmission|null,
     *   response: \Illuminate\Http\JsonResponse|null
     * }
     */
    public function guard(
        Device $device,
        DeviceOrder $order,
        string $clientSubmissionId
    ): array {
        $result = $this->startSubmission($device, $order, $clientSubmissionId);

        // Already completed - return cached response
        if ($result['can_replay']) {
            $cached = $result['cached_response'];
            return [
                'proceed' => false,
                'submission' => $result['submission'],
                'response' => response()->json(
                    $cached['body'],
                    $cached['status'],
                    ['X-Idempotent-Replay' => 'true', 'X-Refill-Guard' => 'durable']
                ),
            ];
        }

        // Currently processing - return 409
        if ($result['is_processing']) {
            return [
                'proceed' => false,
                'submission' => $result['submission'],
                'response' => response()->json([
                    'success' => false,
                    'message' => 'Refill request already processing',
                    'error' => [
                        'code' => 'REFILL_IN_PROGRESS',
                        'submission_status' => $result['submission']->status,
                    ],
                ], 409, ['X-Refill-Guard' => 'durable']),
            ];
        }

        // New or retryable submission - proceed
        return [
            'proceed' => true,
            'submission' => $result['submission'],
            'response' => null,
        ];
    }

    /**
     * Check if POS insert is already done for this submission.
     * If so, returns the previously created ordered_menu IDs.
     *
     * @return array{
     *   already_done: bool,
     *   ordered_menu_ids: array<int>|null
     * }
     */
    public function checkPosAlreadyDone(RefillSubmission $submission): array
    {
        $submission->refresh();

        if (in_array($submission->status, ['POS_CREATED', 'MIRRORED', 'PRINT_EVENT_CREATED', 'COMPLETED'], true)) {
            return [
                'already_done' => true,
                'ordered_menu_ids' => $submission->pos_ordered_menu_ids ?? [],
            ];
        }

        return [
            'already_done' => false,
            'ordered_menu_ids' => null,
        ];
    }
}
