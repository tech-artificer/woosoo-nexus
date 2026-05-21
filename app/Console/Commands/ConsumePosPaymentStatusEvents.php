<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Events\SessionReset;
use App\Services\Pos\PosOrderStatusFinalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConsumePosPaymentStatusEvents extends Command
{
    private const OrderOutboxTable = 'woosoo_order_status_outbox';

    private const SessionOutboxTable = 'woosoo_session_status_outbox';

    private const MaxAttempts = 5;

    protected $signature = 'pos:consume-payment-status-events {--limit=100 : Maximum POS outbox rows processed per outbox per run}';

    protected $description = 'Consume POS-local order payment and daily session-close outbox rows.';

    public function handle(PosOrderStatusFinalizer $finalizer): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $processedOrders = 0;
        $failedOrders = 0;
        $processedSessions = 0;
        $failedSessions = 0;

        [$processedOrders, $failedOrders] = $this->consumeOrderRows($finalizer, $limit);
        [$processedSessions, $failedSessions] = $this->consumeSessionRows($limit);

        $failed = $failedOrders + $failedSessions;

        Log::info('[POS Outbox] Status events consumed', [
            'processed_orders' => $processedOrders,
            'failed_orders' => $failedOrders,
            'processed_sessions' => $processedSessions,
            'failed_sessions' => $failedSessions,
            'limit' => $limit,
        ]);

        $this->info(
            "POS outbox consumed: orders={$processedOrders}, sessions={$processedSessions}, failed={$failed}, limit={$limit}"
        );

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function consumeOrderRows(PosOrderStatusFinalizer $finalizer, int $limit): array
    {
        try {
            $rows = DB::connection('pos')
                ->table(self::OrderOutboxTable)
                ->whereNull('processed_at')
                ->whereNull('failed_at')
                ->where('attempts', '<', self::MaxAttempts)
                ->orderBy('id')
                ->limit($limit)
                ->get();
        } catch (\Throwable $e) {
            Log::warning('[POS Outbox] Unable to read payment status outbox', [
                'error' => $e->getMessage(),
            ]);
            $this->warn("Unable to read POS payment status outbox: {$e->getMessage()}");

            return [0, 1];
        }

        $processed = 0;
        $failed = 0;

        foreach ($rows as $row) {
            try {
                $targetStatus = OrderStatus::from((string) $row->target_status);

                if (! in_array($targetStatus, [OrderStatus::COMPLETED, OrderStatus::VOIDED], true)) {
                    throw new \InvalidArgumentException("Unsupported target_status [{$row->target_status}].");
                }

                $finalizer->finalizeByPosOrderId(
                    (int) $row->pos_order_id,
                    $targetStatus,
                    'system:pos-outbox'
                );

                DB::connection('pos')
                    ->table(self::OrderOutboxTable)
                    ->where('id', (int) $row->id)
                    ->update([
                        'processed_at' => now(),
                        'last_error' => null,
                        'updated_at' => now(),
                    ]);

                $processed++;
            } catch (\Throwable $e) {
                $failed++;
                $this->recordFailure(self::OrderOutboxTable, $row, $e, [
                    'pos_order_id' => $row->pos_order_id ?? null,
                ]);
            }
        }

        return [$processed, $failed];
    }

    private function consumeSessionRows(int $limit): array
    {
        try {
            $rows = DB::connection('pos')
                ->table(self::SessionOutboxTable)
                ->whereNull('processed_at')
                ->whereNull('failed_at')
                ->where('attempts', '<', self::MaxAttempts)
                ->orderBy('id')
                ->limit($limit)
                ->get();
        } catch (\Throwable $e) {
            Log::warning('[POS Outbox] Unable to read session status outbox', [
                'error' => $e->getMessage(),
            ]);
            $this->warn("Unable to read POS session status outbox: {$e->getMessage()}");

            return [0, 1];
        }

        $processed = 0;
        $failed = 0;

        foreach ($rows as $row) {
            try {
                if ((string) $row->event_type !== 'closed') {
                    throw new \InvalidArgumentException("Unsupported event_type [{$row->event_type}].");
                }

                $sessionId = (int) $row->pos_session_id;
                $version = (int) Cache::increment("session:{$sessionId}:version");

                SessionReset::dispatch($sessionId, $version);

                DB::connection('pos')
                    ->table(self::SessionOutboxTable)
                    ->where('id', (int) $row->id)
                    ->update([
                        'processed_at' => now(),
                        'last_error' => null,
                        'updated_at' => now(),
                    ]);

                $processed++;
            } catch (\Throwable $e) {
                $failed++;
                $this->recordFailure(self::SessionOutboxTable, $row, $e, [
                    'pos_session_id' => $row->pos_session_id ?? null,
                ]);
            }
        }

        return [$processed, $failed];
    }

    private function recordFailure(string $table, object $row, \Throwable $e, array $context): void
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
            ->table($table)
            ->where('id', (int) $row->id)
            ->update($updates);

        Log::warning('[POS Outbox] Event failed', [
            'table' => $table,
            'outbox_id' => $row->id ?? null,
            'attempts' => $attempts,
            'failed' => $attempts >= self::MaxAttempts,
            'error' => $e->getMessage(),
        ] + $context);
    }
}
