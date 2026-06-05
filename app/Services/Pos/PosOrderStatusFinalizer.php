<?php

namespace App\Services\Pos;

use App\Enums\OrderStatus;
use App\Events\Order\OrderCompleted;
use App\Events\Order\OrderStatusUpdated;
use App\Events\Order\OrderVoided;
use App\Events\Order\PaymentCompleted;
use App\Models\DeviceOrder;
use App\Services\AuditLogService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class PosOrderStatusFinalizer
{
    public const OPEN_STATUSES = [
        OrderStatus::PENDING->value,
        OrderStatus::CONFIRMED->value,
        OrderStatus::IN_PROGRESS->value,
        OrderStatus::READY->value,
        OrderStatus::SERVED->value,
    ];

    public function finalizeByPosOrderId(
        int $posOrderId,
        OrderStatus $targetStatus,
        string $actorType = 'system'
    ): bool {
        return DB::transaction(function () use ($posOrderId, $targetStatus, $actorType): bool {
            return $this->finalizeMatchingOrder(
                DB::table('device_orders')
                    ->select(['id', 'status'])
                    ->where('order_id', $posOrderId)
                    ->orderBy('id'),
                $targetStatus,
                $actorType
            );
        });
    }

    public function finalizeDeviceOrderId(
        int $deviceOrderId,
        OrderStatus $targetStatus,
        string $actorType = 'system'
    ): bool {
        return DB::transaction(function () use ($deviceOrderId, $targetStatus, $actorType): bool {
            return $this->finalizeMatchingOrder(
                DB::table('device_orders')
                    ->select(['id', 'status'])
                    ->where('id', $deviceOrderId),
                $targetStatus,
                $actorType
            );
        });
    }

    public static function terminalStatusFromPosOrder(object $posOrder): ?OrderStatus
    {
        if ((int) ($posOrder->is_voided ?? 0) === 1) {
            return OrderStatus::VOIDED;
        }

        if ((int) ($posOrder->is_open ?? 1) === 0 || $posOrder->date_time_closed !== null) {
            return OrderStatus::COMPLETED;
        }

        return null;
    }

    private function dispatchTerminalEvents(DeviceOrder $deviceOrder, OrderStatus $targetStatus): void
    {
        OrderStatusUpdated::dispatch($deviceOrder);

        if ($targetStatus === OrderStatus::COMPLETED) {
            OrderCompleted::dispatch($deviceOrder);
            PaymentCompleted::dispatch($deviceOrder);
        }

        if ($targetStatus === OrderStatus::VOIDED) {
            OrderVoided::dispatch($deviceOrder);
        }
    }

    private function finalizeMatchingOrder(Builder $query, OrderStatus $targetStatus, string $actorType): bool
    {
        $row = $query
            ->whereIn('status', self::OPEN_STATUSES)
            ->first();

        if (! $row) {
            return false;
        }

        $updated = DB::table('device_orders')
            ->where('id', (int) $row->id)
            ->where('status', (string) $row->status)
            ->whereIn('status', self::OPEN_STATUSES)
            ->update([
                'status' => $targetStatus->value,
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            return false;
        }

        AuditLogService::orderStatusChanged(
            null,
            (int) $row->id,
            (string) $row->status,
            $targetStatus->value,
            null,
            $actorType
        );

        $deviceOrder = DeviceOrder::query()->find((int) $row->id);
        if (! $deviceOrder) {
            throw new \RuntimeException("DeviceOrder {$row->id} disappeared after status update.");
        }

        $this->dispatchTerminalEvents($deviceOrder, $targetStatus);

        return true;
    }
}
