<?php

namespace App\Console\Commands;

use App\Services\Pos\PosOrderStatusFinalizer;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncPosOrderPaymentStatus extends Command
{
    protected $signature = 'pos:sync-payment-statuses {--chunk=200 : Number of local device_orders rows processed per batch}';

    protected $description = 'Synchronize paid/voided POS orders into local device_orders for split-database deployments.';

    public function handle(PosOrderStatusFinalizer $finalizer): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));
        $totalChecked = 0;
        $totalUpdated = 0;

        $openStatuses = PosOrderStatusFinalizer::OPEN_STATUSES;

        /** @var ConnectionInterface $local */
        $local = DB::connection();
        /** @var ConnectionInterface $pos */
        $pos = DB::connection('pos');

        // Skip cleanly when POS is unreachable (e.g. dev/staging on a different
        // network). Without this guard every scheduled tick throws a full
        // QueryException stack trace into laravel.log — at ~30KB per failure
        // and one tick per minute the log balloons and the scheduler reports
        // exit-code-1 alerts continuously. Treating a hard connection failure
        // as a no-op is correct: there's nothing to sync if POS is offline.
        try {
            $pos->getPdo();
        } catch (\Throwable $e) {
            Log::warning('[pos:sync-payment-statuses] POS DB unreachable — skipping this tick', [
                'error' => $e->getMessage(),
            ]);
            $this->warn('POS DB unreachable. Skipping sync.');

            return self::FAILURE;
        }

        $local
            ->table('device_orders')
            ->select(['id', 'order_id', 'status'])
            ->whereNotNull('order_id')
            ->whereIn('status', $openStatuses)
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use (&$totalChecked, &$totalUpdated, $pos, $finalizer): void {
                $totalChecked += $rows->count();

                $posOrderIds = $rows
                    ->pluck('order_id')
                    ->filter(fn ($id): bool => is_numeric($id) && (int) $id > 0)
                    ->map(fn ($id): int => (int) $id)
                    ->values();

                if ($posOrderIds->isEmpty()) {
                    return;
                }

                $posOrders = $pos
                    ->table('orders')
                    ->select(['id', 'is_voided', 'is_open', 'date_time_closed'])
                    ->whereIn('id', $posOrderIds)
                    ->where(function ($query): void {
                        $query->whereNotNull('date_time_closed')
                            ->orWhere('is_voided', 1)
                            ->orWhere('is_open', 0);
                    })
                    ->get()
                    ->mapWithKeys(static function ($order): array {
                        // POS stores IDs as zero-padded strings (e.g., "0000019635").
                        // Normalize keys to int so local `order_id` numeric values map correctly.
                        return [(int) $order->id => $order];
                    });

                foreach ($rows as $row) {
                    $posOrder = $posOrders->get((int) $row->order_id);

                    if (! $posOrder) {
                        continue;
                    }

                    $nextStatus = PosOrderStatusFinalizer::terminalStatusFromPosOrder($posOrder);

                    if (! $nextStatus || (string) $row->status === $nextStatus->value) {
                        continue;
                    }

                    if ($finalizer->finalizeDeviceOrderId(
                        (int) $row->id,
                        $nextStatus,
                        'system:pos-sync'
                    )) {
                        $totalUpdated++;
                    }
                }
            }, 'id');

        Log::info('[POS Sync] Payment status reconciliation completed', [
            'checked' => $totalChecked,
            'updated' => $totalUpdated,
            'chunk' => $chunkSize,
        ]);

        $this->info("POS sync complete: checked={$totalChecked}, updated={$totalUpdated}, chunk={$chunkSize}");

        return self::SUCCESS;
    }
}
