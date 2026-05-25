<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Events\Order\OrderCompleted;
use App\Events\Order\OrderStatusUpdated;
use App\Events\Order\OrderVoided;
use App\Events\SessionReset;
use App\Models\DeviceOrder;
use App\Services\AuditLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ForceEndSession extends Command
{
    protected $signature = 'session:force-end
        {session_id : KryptonSession ID stored in device_orders.session_id}
        {--force   : Close even if orders are still open in the POS (voids them locally)}';

    protected $description = 'Force-end a stuck tablet session. Closes open orders after confirming POS state. Use --force to override when POS is unreachable or order is genuinely still open.';

    private const OPEN_STATUSES = [
        OrderStatus::PENDING->value,
        OrderStatus::CONFIRMED->value,
        OrderStatus::IN_PROGRESS->value,
        OrderStatus::READY->value,
        OrderStatus::SERVED->value,
    ];

    public function handle(): int
    {
        $sessionId = (int) $this->argument('session_id');
        $force     = (bool) $this->option('force');

        $openOrders = DeviceOrder::query()
            ->where('session_id', $sessionId)
            ->whereIn('status', self::OPEN_STATUSES)
            ->get();

        if ($openOrders->isEmpty()) {
            $this->info("No open orders found for session {$sessionId}. Broadcasting reset anyway.");
            $this->broadcastReset($sessionId);
            return self::SUCCESS;
        }

        $this->info("Found {$openOrders->count()} open order(s) for session {$sessionId}:");
        $this->table(
            ['DeviceOrder ID', 'POS Order ID', 'Current Status'],
            $openOrders->map(fn ($o) => [$o->id, $o->order_id ?? '—', is_string($o->status) ? $o->status : $o->status->value])
        );

        // Query POS for all linked order IDs to determine actual state.
        $posOrderIds = $openOrders
            ->whereNotNull('order_id')
            ->pluck('order_id')
            ->filter(fn ($id) => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->values();

        $posOrders = collect();
        $posReachable = true;

        if ($posOrderIds->isNotEmpty()) {
            try {
                $posOrders = DB::connection('pos')
                    ->table('orders')
                    ->select(['id', 'is_voided', 'is_open', 'date_time_closed'])
                    ->whereIn('id', $posOrderIds)
                    ->get()
                    ->mapWithKeys(fn ($o) => [(int) $o->id => $o]);
            } catch (\Throwable $e) {
                $posReachable = false;
                $this->warn("Could not reach POS DB: {$e->getMessage()}");
                if (! $force) {
                    $this->error('Cannot confirm POS state. Pass --force to void all open orders and end the session anyway.');
                    return self::FAILURE;
                }
            }
        }

        // Without --force, refuse if any order is still genuinely open in POS.
        if ($posReachable && ! $force) {
            $blockers = $openOrders->filter(function ($order) use ($posOrders) {
                if (! $order->order_id) {
                    return false; // No POS link — safe to cancel locally.
                }
                $pos = $posOrders->get((int) $order->order_id);
                if (! $pos) {
                    return false; // Not found in POS — treat as detached, safe to close.
                }
                // Genuinely still open in POS
                return (int) ($pos->is_voided ?? 0) === 0
                    && (int) ($pos->is_open ?? 1) === 1
                    && $pos->date_time_closed === null;
            });

            if ($blockers->isNotEmpty()) {
                $this->error("The following orders are still OPEN in the POS and cannot be force-ended without --force:");
                $this->table(
                    ['DeviceOrder ID', 'POS Order ID'],
                    $blockers->map(fn ($o) => [$o->id, $o->order_id])
                );
                $this->line('Either close/void the order in the POS first, or re-run with --force to void locally.');
                return self::FAILURE;
            }
        }

        // Apply terminal status to each open order.
        $closed = 0;
        foreach ($openOrders as $order) {
            $nextStatus = $this->resolveNextStatus($order, $posOrders, $posReachable);
            $prevStatus = is_string($order->status) ? $order->status : $order->status->value;

            $updated = DB::table('device_orders')
                ->where('id', (int) $order->id)
                ->update([
                    'status'     => $nextStatus->value,
                    'updated_at' => now(),
                ]);

            if ($updated === 0) {
                $this->warn("DeviceOrder #{$order->id}: DB update returned 0 rows — may already be in a terminal state.");
                continue;
            }

            $closed++;

            AuditLogService::orderStatusChanged(
                null,
                (int) $order->id,
                $prevStatus,
                $nextStatus->value,
                null,
                'admin:force-end-session'
            );

            Log::info('[ForceEndSession] Order status updated', [
                'device_order_id' => $order->id,
                'session_id'      => $sessionId,
                'old_status'      => $prevStatus,
                'new_status'      => $nextStatus->value,
                'forced'          => $force,
            ]);

            // Dispatch real-time events (same pattern as SyncPosOrderPaymentStatus).
            $fresh = DeviceOrder::query()->find((int) $order->id);
            if (! $fresh) {
                continue;
            }

            OrderStatusUpdated::dispatch($fresh);

            if ($nextStatus === OrderStatus::COMPLETED) {
                OrderCompleted::dispatch($fresh);
            }

            if ($nextStatus === OrderStatus::VOIDED) {
                OrderVoided::dispatch($fresh);
            }
        }

        $this->info("Orders closed: {$closed}");

        $this->broadcastReset($sessionId);

        return self::SUCCESS;
    }

    private function resolveNextStatus(DeviceOrder $order, $posOrders, bool $posReachable): OrderStatus
    {
        if (! $order->order_id || ! $posReachable) {
            return OrderStatus::VOIDED;
        }

        $pos = $posOrders->get((int) $order->order_id);

        if (! $pos) {
            // No matching POS order found — cancel locally.
            return OrderStatus::VOIDED;
        }

        if ((int) ($pos->is_voided ?? 0) === 1) {
            return OrderStatus::VOIDED;
        }

        if ((int) ($pos->is_open ?? 1) === 0 || $pos->date_time_closed !== null) {
            return OrderStatus::COMPLETED;
        }

        // Still open in POS but --force was passed.
        return OrderStatus::VOIDED;
    }

    private function broadcastReset(int $sessionId): void
    {
        try {
            SessionReset::dispatch($sessionId);
            $this->info("SessionReset broadcast dispatched for session {$sessionId}.");
        } catch (\Throwable $e) {
            $this->warn("SessionReset broadcast failed (order data updated successfully): {$e->getMessage()}");
            $this->warn('Fix REVERB_HOST=reverb in woosoo-nexus/.env and restart the app container, then the tablet will reset on reconnect.');
            Log::warning('[ForceEndSession] SessionReset broadcast failed', [
                'session_id' => $sessionId,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
