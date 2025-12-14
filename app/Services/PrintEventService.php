<?php

namespace App\Services;

use App\Models\PrintEvent;
use App\Models\DeviceOrder;
use Illuminate\Support\Facades\DB;
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
        return PrintEvent::create([
            'device_order_id' => $deviceOrder->id,
            'event_type' => $eventType,
            'meta' => $meta,
        ]);
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
    public function ack(int $printEventId, ?string $printerId = null, ?string $printedAt = null): array
    {
        $ackAt = $printedAt ? Carbon::parse($printedAt)->utc() : Carbon::now()->utc();

        // Conditional update: only flip is_acknowledged when it's currently false.
        // Return whether this call actually performed the acknowledgement.
        $updated = PrintEvent::where('id', $printEventId)
            ->where('is_acknowledged', false)
            ->update([
                'is_acknowledged' => true,
                'acknowledged_at' => $ackAt,
                'printer_id' => $printerId,
                'attempts' => DB::raw('attempts + 1'),
                'updated_at' => Carbon::now()->utc(),
            ]);

        $evt = $this->getById($printEventId);

        return [
            'print_event' => $evt,
            'was_updated' => ($updated > 0),
        ];
    }

    /**
     * Mark a PrintEvent as failed (increment attempts and store the error).
     *
     * @param int $printEventId
     * @param string|null $error
     * @return array{print_event: \App\Models\PrintEvent, was_updated: bool}
     */
    public function fail(int $printEventId, ?string $error = null): array
    {
        $updated = PrintEvent::where('id', $printEventId)
            ->update([
                'attempts' => DB::raw('attempts + 1'),
                'last_error' => $error,
                'updated_at' => Carbon::now()->utc(),
            ]);

        $evt = $this->getById($printEventId);

        return [
            'print_event' => $evt,
            'was_updated' => ($updated > 0),
        ];
    }
}
