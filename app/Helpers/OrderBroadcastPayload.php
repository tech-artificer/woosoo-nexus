<?php

namespace App\Helpers;

use App\Enums\OrderStatus;
use App\Models\DeviceOrder;

class OrderBroadcastPayload
{
    public static function make(DeviceOrder $order): array
    {
        // Ensure related data is available for downstream consumers
        $order->loadMissing(['device.table', 'table', 'items.menu', 'serviceRequests']);

        $table = $order->device?->table ?? $order->table;

        return [
            'id' => $order->id,
            'order_id' => $order->order_id,
            'order_number' => $order->order_number,
            'device_id' => $order->device_id,
            'table_id' => $order->table_id,
            'branch_id' => $order->branch_id,
            'session_id' => $order->session_id,
            'status' => $order->status,
            'kds_state' => self::toKdsState($order->status),
            'kds_type' => ($order->items?->isNotEmpty() && $order->items->every(fn ($it) => (bool) ($it->is_refill ?? false))) ? 'refill' : 'initial',
            'is_printed' => (bool) ($order->is_printed ?? false),
            'printed_at' => $order->printed_at?->toIso8601String(),
            'printed_by' => $order->printed_by,
            'subtotal' => $order->subtotal ?? $order->sub_total ?? null,
            'tax' => $order->tax,
            'discount' => $order->discount,
            'total' => $order->total,
            'guest_count' => $order->guest_count,
            'created_at' => $order->created_at?->toIso8601String(),
            'updated_at' => $order->updated_at?->toIso8601String(),
            'device' => $order->device ? [
                'id' => $order->device->id,
                'name' => $order->device->name,
            ] : null,
            'table' => $table ? [
                'id' => $table->id,
                'name' => $table->name,
            ] : null,
            'items' => $order->items?->map(fn ($it) => [
                'id' => $it->id,
                'name' => $it->menu?->receipt_name ?? $it->menu?->name ?? $it->name,
                'quantity' => $it->quantity,
                'price' => $it->price,
                'subtotal' => $it->subtotal,
                'is_refill' => (bool) ($it->is_refill ?? false),
                'notes' => $it->notes ?? null,
                'type' => $it->type ?? null,
            ])->values()->all(),
            'serviceRequests' => $order->serviceRequests ?? [],
        ];
    }

    private static function toKdsState(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::PENDING,
            OrderStatus::CONFIRMED => 'new',
            OrderStatus::IN_PROGRESS => 'preparing',
            OrderStatus::READY => 'ready',
            OrderStatus::SERVED => 'served',
            OrderStatus::VOIDED => 'voided',
            default => 'new',
        };
    }
}
