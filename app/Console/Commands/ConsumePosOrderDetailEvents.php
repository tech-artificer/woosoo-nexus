<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Broadcasting\OrderBroadcaster;
use App\Models\DeviceOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * NEX-CASE-013: drains `woosoo_order_detail_outbox` rows written by POS
 * `AFTER UPDATE` triggers on `orders` (guest_count) and `order_checks`
 * (totals). For each row, re-reads the local DeviceOrder by canonical
 * `order_id` and dispatches `OrderDetailsUpdated` so the tablet redraws
 * subtotal/tax/total/guest_count without mutating its order-state machine.
 *
 * Mirrors {@see ConsumePosPaymentStatusEvents} for failure handling: catches
 * a fatal read error at the table level, increments attempts per row, marks
 * `failed_at` once `MaxAttempts` is reached, and exits non-zero if any rows
 * fail (drives schedule:work alerting).
 *
 * `MaxAttempts = 3` (vs. 5 for payment) — detail sync is non-critical visual
 * refresh; faster dead-letter keeps churn out of the outbox if a particular
 * order is unrecoverable on the local side.
 */
class ConsumePosOrderDetailEvents extends Command
{
    private const DetailOutboxTable = 'woosoo_order_detail_outbox';

    private const MaxAttempts = 3;

    protected $signature = 'pos:consume-order-detail-events {--limit=100 : Maximum POS detail outbox rows processed per run}';

    protected $description = 'Consume POS-local order-detail outbox rows and broadcast OrderDetailsUpdated.';

    public function handle(OrderBroadcaster $broadcaster): int
    {
        $limit = max(1, (int) $this->option('limit'));

        try {
            $rows = DB::connection('pos')
                ->table(self::DetailOutboxTable)
                ->whereNull('processed_at')
                ->whereNull('failed_at')
                ->where('attempts', '<', self::MaxAttempts)
                ->orderBy('id')
                ->limit($limit)
                ->get();
        } catch (\Throwable $e) {
            Log::warning('[POS Detail Outbox] Unable to read detail outbox', [
                'error' => $e->getMessage(),
            ]);
            $this->warn("Unable to read POS detail outbox: {$e->getMessage()}");

            return self::FAILURE;
        }

        $processed = 0;
        $failed = 0;

        foreach ($rows as $row) {
            try {
                $posOrderId = (int) $row->pos_order_id;

                $deviceOrder = DeviceOrder::where('order_id', $posOrderId)
                    ->with(['device.table', 'table', 'items.menu', 'serviceRequests'])
                    ->first();

                if ($deviceOrder === null) {
                    // No matching local order — POS edited an order Nexus has
                    // no record of. Dead-letter immediately; retrying cannot
                    // produce a different result.
                    throw new \RuntimeException(
                        "No DeviceOrder found for pos_order_id [{$posOrderId}]."
                    );
                }

                // Refresh POS-authoritative detail values onto the local row
                // BEFORE broadcasting. The outbox row carries only pos_order_id;
                // guest_count lives on POS `orders` and the monetary totals on
                // POS `order_checks`. Without this, OrderBroadcastPayload would
                // serialize the stale local columns and the detail update the
                // trigger fired for would be silently lost.
                $this->refreshDetailFromPos($deviceOrder, $posOrderId);

                $broadcaster->detailsUpdated($deviceOrder);

                DB::connection('pos')
                    ->table(self::DetailOutboxTable)
                    ->where('id', (int) $row->id)
                    ->update([
                        'processed_at' => now(),
                        'last_error' => null,
                        'updated_at' => now(),
                    ]);

                $processed++;
            } catch (\Throwable $e) {
                $failed++;
                $this->recordFailure($row, $e);
            }
        }

        Log::info('[POS Detail Outbox] Detail events consumed', [
            'processed' => $processed,
            'failed' => $failed,
            'limit' => $limit,
        ]);

        $this->info("POS detail outbox consumed: processed={$processed}, failed={$failed}, limit={$limit}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Re-read POS-authoritative detail columns and persist them onto the local
     * DeviceOrder so the broadcast carries fresh values. POS is the source of
     * truth: `guest_count` on `orders`, monetary totals on `order_checks`
     * (`Order` hasOne `OrderCheck` by `order_id`). The tablet renders these
     * read-only and never recomputes pricing. The POS connection is already
     * proven reachable (the outbox read above succeeded), so a missing row
     * simply returns null and is skipped rather than treated as an error.
     */
    private function refreshDetailFromPos(DeviceOrder $deviceOrder, int $posOrderId): void
    {
        $pos = DB::connection('pos');

        $guestCount = $pos->table('orders')->where('id', $posOrderId)->value('guest_count');
        if ($guestCount !== null) {
            $deviceOrder->guest_count = (int) $guestCount;
        }

        $check = $pos->table('order_checks')->where('order_id', $posOrderId)->first();
        if ($check !== null) {
            if (isset($check->subtotal_amount)) {
                $deviceOrder->subtotal = $check->subtotal_amount;
            }
            if (isset($check->tax_amount)) {
                $deviceOrder->tax = $check->tax_amount;
            }
            if (isset($check->discount_amount)) {
                $deviceOrder->discount = $check->discount_amount;
            }
            if (isset($check->total_amount)) {
                $deviceOrder->total = $check->total_amount;
            }
        }

        if ($deviceOrder->isDirty()) {
            $deviceOrder->save();
        }
    }

    private function recordFailure(object $row, \Throwable $e): void
    {
        $attempts = (int) ($row->attempts ?? 0) + 1;
        $updates = [
            'attempts' => $attempts,
            'last_error' => $e->getMessage(),
            'updated_at' => now(),
        ];

        if ($attempts >= self::MaxAttempts) {
            $updates['failed_at'] = now();
        }

        DB::connection('pos')
            ->table(self::DetailOutboxTable)
            ->where('id', (int) $row->id)
            ->update($updates);

        Log::warning('[POS Detail Outbox] Event failed', [
            'outbox_id' => $row->id ?? null,
            'pos_order_id' => $row->pos_order_id ?? null,
            'attempts' => $attempts,
            'failed' => $attempts >= self::MaxAttempts,
            'error' => $e->getMessage(),
        ]);
    }
}
