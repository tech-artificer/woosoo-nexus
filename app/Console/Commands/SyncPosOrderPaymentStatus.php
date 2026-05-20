<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Events\Order\OrderCompleted;
use App\Events\Order\OrderStatusUpdated;
use App\Events\Order\OrderVoided;
use App\Events\Order\PaymentCompleted;
use App\Events\SessionReset;
use App\Models\DeviceOrder;
use App\Services\AuditLogService;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncPosOrderPaymentStatus extends Command
{
    protected $signature = 'pos:sync-payment-statuses {--chunk=200 : Number of local device_orders rows processed per batch}';

    protected $description = 'Synchronize paid/voided POS orders into local device_orders for split-database deployments.';

    public function handle(): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));
        $totalChecked = 0;
        $totalUpdated = 0;

        $openStatuses = [
            OrderStatus::PENDING->value,
            OrderStatus::CONFIRMED->value,
            OrderStatus::IN_PROGRESS->value,
            OrderStatus::READY->value,
            OrderStatus::SERVED->value,
        ];

        /** @var ConnectionInterface $local */
        $local = DB::connection('mysql');
        /** @var ConnectionInterface $pos */
        $pos = DB::connection('pos');

        $local
            ->table('device_orders')
            ->select(['id', 'order_id', 'status'])
            ->whereNotNull('order_id')
            ->whereIn('status', $openStatuses)
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use (&$totalChecked, &$totalUpdated, $pos, $local): void {
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

                    $nextStatus = ((int) ($posOrder->is_voided ?? 0) === 1)
                        ? OrderStatus::VOIDED
                        : OrderStatus::COMPLETED;

                    if ((string) $row->status === $nextStatus->value) {
                        continue;
                    }

                    $updated = $local
                        ->table('device_orders')
                        ->where('id', (int) $row->id)
                        ->update([
                            'status' => $nextStatus->value,
                            'updated_at' => now(),
                        ]);

                    if ($updated === 0) {
                        continue;
                    }

                    $totalUpdated++;

                    AuditLogService::orderStatusChanged(
                        null,
                        (int) $row->id,
                        (string) $row->status,
                        $nextStatus->value,
                        null,
                        'system'
                    );

                    $deviceOrder = DeviceOrder::query()->find((int) $row->id);
                    if (! $deviceOrder) {
                        continue;
                    }

                    // This update path bypasses Eloquent mutators/observers intentionally,
                    // so we manually emit the real-time events expected by tablet/admin clients.
                    OrderStatusUpdated::dispatch($deviceOrder);

                    if ($nextStatus === OrderStatus::COMPLETED) {
                        OrderCompleted::dispatch($deviceOrder);
                        PaymentCompleted::dispatch($deviceOrder);
                    }

                    if ($nextStatus === OrderStatus::VOIDED) {
                        OrderVoided::dispatch($deviceOrder);
                    }

                    // Broadcast session.reset on the session channel so the tablet
                    // ends the session immediately without relying on order-ID matching.
                    if ($deviceOrder->session_id) {
                        SessionReset::dispatch((int) $deviceOrder->session_id);
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
