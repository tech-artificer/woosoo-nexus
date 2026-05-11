<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceOrder;
use App\Models\RefillSubmission;
use App\Models\PrintEvent;

/**
 * Refill Submission Service
 *
 * High-level service for managing refill submissions.
 * Wraps DurableRefillGuard to provide a more convenient API for tests and controllers.
 */
class RefillSubmissionService
{
    private DurableRefillGuard $guard;

    public function __construct(DurableRefillGuard $guard)
    {
        $this->guard = $guard;
    }

    /**
     * Attempt to start a new refill submission or find existing.
     *
     * @return array{
     *   status: 'new'|'completed'|'conflict',
     *   submission: RefillSubmission,
     *   response: array|null
     * }
     */
    public function acquireOrFindSubmission(
        Device $device,
        DeviceOrder $order,
        string $clientSubmissionId
    ): array {
        $result = $this->guard->startSubmission($device, $order, $clientSubmissionId);

        // Determine status based on result
        if ($result['can_replay']) {
            $cached = $result['cached_response'];
            return [
                'status' => 'completed',
                'submission' => $result['submission'],
                'response' => $cached['body'] ?? null,
            ];
        }

        if ($result['is_processing']) {
            return [
                'status' => 'conflict',
                'submission' => $result['submission'],
                'response' => null,
            ];
        }

        // New submission or retry
        return [
            'status' => 'new',
            'submission' => $result['submission'],
            'response' => null,
        ];
    }

    /**
     * Mark that POS ordered_menu rows have been created.
     */
    public function markPosCreated(RefillSubmission $submission, array $orderedMenuIds): bool
    {
        try {
            $this->guard->markPosCreated($submission, $orderedMenuIds);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Mark that local mirror has been completed.
     */
    public function markMirrored(RefillSubmission $submission): bool
    {
        try {
            $this->guard->markMirrored($submission);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Mark that print event has been created.
     */
    public function markPrintEventCreated(RefillSubmission $submission, ?PrintEvent $printEvent = null): bool
    {
        try {
            if ($printEvent) {
                $this->guard->markPrintEventCreated($submission, $printEvent);
            } else {
                // For tests that don't have a print event, just transition state
                $submission->transitionTo('PRINT_EVENT_CREATED');
            }
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Mark submission as completed with cached response for replay.
     */
    public function completeSubmission(RefillSubmission $submission, array $responsePayload): bool
    {
        try {
            $this->guard->markCompleted($submission, $responsePayload);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Mark submission as failed.
     */
    public function markFailed(RefillSubmission $submission, string $errorMessage): bool
    {
        try {
            $this->guard->markFailed($submission, $errorMessage);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Verify that POS result matches stored IDs.
     */
    public function verifyPosResultMatches(RefillSubmission $submission, array $orderedMenuIds): bool
    {
        $storedIds = $submission->pos_ordered_menu_ids ?? [];

        // If no stored IDs, return true (no previous data to verify against)
        if (empty($storedIds)) {
            return true;
        }

        // Compare the arrays
        sort($storedIds);
        sort($orderedMenuIds);

        return $storedIds === $orderedMenuIds;
    }
}
