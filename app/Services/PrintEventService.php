<?php

namespace App\Services;

use App\Enums\PrintEventStatus;
use App\Models\DeviceOrder;
use App\Models\PrintEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PrintEventService
{
    /**
     * Create a print event for a DeviceOrder.
     *
     * NOTE: When NEXUS_PRINT_EVENTS_ENABLED=false (MVP default), this method
     * no-ops and returns null. woosoo-print-bridge is the active print execution
     * path. Enable the config flag for future printer expansion work.
     */
    public function createForOrder(DeviceOrder $deviceOrder, string $eventType, array $meta = []): ?PrintEvent
    {
        // Check feature flag - disabled by default for MVP (woosoo-print-bridge is primary)
        if (! config('api.print_events_enabled', false)) {
            Log::info('PrintEvent creation skipped: woosoo-print-bridge is primary print execution path', [
                'device_order_id' => $deviceOrder->id,
                'event_type' => $eventType,
            ]);
            return null;
        }

        // Treat `session_id` as device-local. Do not consult POS sessions here.
        // Always create print events for device orders; session scoping is handled client-side.

        Log::info('Creating print event', [
            'device_order_id' => $deviceOrder->id,
            'order_id' => $deviceOrder->order_id,
            'event_type' => $eventType,
            'branch_id' => $deviceOrder->branch_id,
        ]);

        $event = PrintEvent::create([
            'device_order_id' => $deviceOrder->id,
            'event_type' => $eventType,
            'status' => PrintEventStatus::PENDING,
            'meta' => $meta,
        ]);

        if (! $event) {
            Log::error('Failed to create print event', [
                'device_order_id' => $deviceOrder->id,
                'event_type' => $eventType,
            ]);
        } else {
            Log::info('Print event created successfully', [
                'print_event_id' => $event->id,
                'device_order_id' => $deviceOrder->id,
            ]);
        }

        return $event;
    }

    /**
     * Fetch a PrintEvent by id or fail.
     */
    public function getById(int $id): PrintEvent
    {
        return PrintEvent::findOrFail($id);
    }

    /**
     * Acknowledge a PrintEvent in a concurrency-safe manner.
     * Performs a conditional update so multiple acks don't overwrite each other.
     *
     * @return array{print_event: \App\Models\PrintEvent, was_updated: bool}
     */
    public function ack(int $printEventId, ?string $printerId = null, ?string $printedAt = null, ?int $acknowledgedByDeviceId = null, ?string $printerName = null, ?string $verificationMode = null): array
    {
        if ($printedAt) {
            $ackAt = Carbon::parse($printedAt);
        } else {
            $ackAt = Carbon::now();
        }
        $result = DB::transaction(function () use ($printEventId, $printerId, $ackAt, $acknowledgedByDeviceId, $printerName, $verificationMode) {
            // Lock the row to avoid race conditions when multiple workers
            // acknowledge/fail the same print event concurrently.
            $evt = PrintEvent::where('id', $printEventId)->lockForUpdate()->first();

            if (! $evt) {
                // Caller expects an exception-like behavior similar to findOrFail.
                throw new \Illuminate\Database\Eloquent\ModelNotFoundException("PrintEvent not found: {$printEventId}");
            }

            // If already acknowledged/printed, do not modify attempts or timestamps.
            if ($evt->is_acknowledged) {
                return ['evt' => $evt, 'was_updated' => false];
            }

            $evt->is_acknowledged = true;
            $evt->status = PrintEventStatus::PRINTED;
            $evt->backend_status = 'acked';
            $evt->acknowledged_at = $ackAt;
            if ($printerId !== null) {
                $evt->printer_id = $printerId;
            }
            if ($printerName !== null) {
                $evt->printer_name = $printerName;
            }
            if ($acknowledgedByDeviceId !== null) {
                $evt->acknowledged_by_device_id = $acknowledgedByDeviceId;
            }

            if ($verificationMode !== null) {
                $meta = is_array($evt->meta) ? $evt->meta : [];
                $meta['verification_mode'] = $verificationMode;
                $evt->meta = $meta;
            }

            $evt->attempts = (int) ($evt->attempts ?? 0) + 1;
            $evt->updated_at = Carbon::now()->utc();
            $evt->save();

            // WS2: Mark items as printed using PrintTicketService
            $printTicketService = new PrintTicketService();
            $printTicketService->markItemsAsPrinted($evt);

            // Propagate printed status to the associated device order
            // so clients have a consistent source of truth on the order.
            /** @var \App\Models\DeviceOrder|null $order */
            $order = $evt->deviceOrder;
            if ($order) {
                $order->is_printed = 1;
                $order->printed_by = $printerId ?? $order->printed_by;
                $order->printed_at = $ackAt;
                $order->save();
            }

            return ['evt' => $evt, 'was_updated' => true];
        });

        return [
            'print_event' => $result['evt'],
            'was_updated' => $result['was_updated'],
        ];
    }

    /**
     * Mark a PrintEvent as failed (increment attempts and store the error).
     *
     * @return array{print_event: \App\Models\PrintEvent, was_updated: bool}
     */
    public function fail(
        int $printEventId,
        ?string $error = null,
        ?int $acknowledgedByDeviceId = null,
        ?string $failedAt = null,
        ?int $attemptCount = null,
    ): array
    {
        $result = DB::transaction(function () use ($printEventId, $error, $acknowledgedByDeviceId, $failedAt, $attemptCount) {
            $evt = PrintEvent::where('id', $printEventId)->lockForUpdate()->first();

            if (! $evt) {
                throw new \Illuminate\Database\Eloquent\ModelNotFoundException("PrintEvent not found: {$printEventId}");
            }

            // Do not mark as failed if already acknowledged/printed.
            if ($evt->is_acknowledged) {
                return ['evt' => $evt, 'was_updated' => false];
            }

            $resolvedFailedAt = $failedAt
                ? Carbon::parse($failedAt)->utc()
                : Carbon::now()->utc();

            $evt->attempts = (int) ($evt->attempts ?? 0) + 1;
            // Preserve existing device-reported attempt_count when omitted; attempts is always incremented above.
            // If callers need to overwrite it, they must pass an explicit integer value (for example 0).
            $evt->attempt_count = $attemptCount ?? $evt->attempt_count;
            $evt->last_error = $error;
            $evt->failed_at = $resolvedFailedAt;
            $evt->status = PrintEventStatus::FAILED;
            $evt->backend_status = 'failed';
            if ($acknowledgedByDeviceId !== null) {
                $evt->acknowledged_by_device_id = $acknowledgedByDeviceId;
            }
            $evt->updated_at = Carbon::now()->utc();
            $evt->save();

            return ['evt' => $evt, 'was_updated' => true];
        });

        return [
            'print_event' => $result['evt'],
            'was_updated' => $result['was_updated'],
        ];
    }

    /**
     * Atomically reserve a pending PrintEvent for a specific bridge device.
     * Returns ['print_event' => ..., 'reserved' => bool].
     * Returns reserved=false (409) if the job is already reserved/printing/printed.
     */
    public function reserve(int $printEventId, int $deviceId): array
    {
        $result = DB::transaction(function () use ($printEventId, $deviceId) {
            $evt = PrintEvent::where('id', $printEventId)->lockForUpdate()->first();

            if (! $evt) {
                throw new \Illuminate\Database\Eloquent\ModelNotFoundException("PrintEvent not found: {$printEventId}");
            }

            // Only pending events can be reserved
            $currentStatus = $evt->status instanceof PrintEventStatus
                ? $evt->status
                : PrintEventStatus::tryFrom($evt->status ?? 'pending');

            if ($currentStatus !== PrintEventStatus::PENDING) {
                return ['evt' => $evt, 'reserved' => false];
            }

            $evt->status = PrintEventStatus::RESERVED;
            $evt->reserved_by_device_id = $deviceId;
            $evt->reserved_at = Carbon::now();
            $evt->updated_at = Carbon::now()->utc();
            $evt->save();

            return ['evt' => $evt, 'reserved' => true];
        });

        return [
            'print_event' => $result['evt'],
            'reserved' => $result['reserved'],
        ];
    }

}
