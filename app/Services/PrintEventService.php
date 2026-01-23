<?php

namespace App\Services;

use App\Models\PrintEvent;
use App\Models\DeviceOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PrintEventService
{
    /**
     * Create a print event for a DeviceOrder.
     */
    public function createForOrder(DeviceOrder $deviceOrder, string $eventType, array $meta = []): ?PrintEvent
    {
        // Treat `session_id` as device-local. Do not consult POS sessions here.
        // Always create print events for device orders; session scoping is handled client-side.

        Log::info("Creating print event", [
            'device_order_id' => $deviceOrder->id,
            'order_id' => $deviceOrder->order_id,
            'event_type' => $eventType,
            'branch_id' => $deviceOrder->branch_id
        ]);

        $event = PrintEvent::create([
            'device_order_id' => $deviceOrder->id,
            'event_type' => $eventType,
            'meta' => $meta,
        ]);

        if (!$event) {
            Log::error("Failed to create print event", [
                'device_order_id' => $deviceOrder->id,
                'event_type' => $eventType
            ]);
        } else {
            Log::info("Print event created successfully", [
                'print_event_id' => $event->id,
                'device_order_id' => $deviceOrder->id
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
     * @param int $printEventId
     * @param string|null $printerId
     * @param string|null $printedAt
     * @return array{print_event: \App\Models\PrintEvent, was_updated: bool}
     */
    public function ack(int $printEventId, ?string $printerId = null, ?string $printedAt = null, ?int $acknowledgedByDeviceId = null, ?string $printerName = null): array
    {
        $ackAt = $printedAt ? Carbon::parse($printedAt)->utc() : Carbon::now()->utc();
        $result = DB::transaction(function () use ($printEventId, $printerId, $ackAt, $acknowledgedByDeviceId, $printerName) {
            // Lock the row to avoid race conditions when multiple workers
            // acknowledge/fail the same print event concurrently.
            $evt = PrintEvent::where('id', $printEventId)->lockForUpdate()->first();

            if (! $evt) {
                // Caller expects an exception-like behavior similar to findOrFail.
                throw new \Illuminate\Database\Eloquent\ModelNotFoundException("PrintEvent not found: {$printEventId}");
            }

            // If already acknowledged, do not modify attempts or timestamps.
            if ($evt->is_acknowledged) {
                return ['evt' => $evt, 'was_updated' => false];
            }

            $evt->is_acknowledged = true;
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

            $evt->attempts = (int) ($evt->attempts ?? 0) + 1;
            $evt->updated_at = Carbon::now()->utc();
            $evt->save();

            // Propagate printed status to the associated device order
            // so clients have a consistent source of truth on the order.
            /** @var \App\Models\DeviceOrder|null $order */
            $order = $evt->deviceOrder;
            if ($order) {
                // Do not overwrite existing printed_at if present; use latest ack time if empty
                $order->is_printed = 1;
                $order->printed_by = $printerId ?? $order->printed_by;
                $order->printed_at = $order->printed_at ?: $ackAt;
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
     * @param int $printEventId
     * @param string|null $error
     * @param int|null $acknowledgedByDeviceId
     * @return array{print_event: \App\Models\PrintEvent, was_updated: bool}
     */
    public function fail(int $printEventId, ?string $error = null, ?int $acknowledgedByDeviceId = null): array
    {
        $result = DB::transaction(function () use ($printEventId, $error, $acknowledgedByDeviceId) {
            $evt = PrintEvent::where('id', $printEventId)->lockForUpdate()->first();

            if (! $evt) {
                throw new \Illuminate\Database\Eloquent\ModelNotFoundException("PrintEvent not found: {$printEventId}");
            }

            // Do not mark as failed if already acknowledged/printed.
            if ($evt->is_acknowledged) {
                return ['evt' => $evt, 'was_updated' => false];
            }

            $evt->attempts = (int) ($evt->attempts ?? 0) + 1;
            $evt->last_error = $error;
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
}
