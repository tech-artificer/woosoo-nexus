<?php

declare(strict_types=1);

namespace App\Events\Order;

use App\Models\DeviceOrder;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast on the table.{tableId} channel when a POS-originated order is
 * detected for a table. Lets tablets that boot after the order was created
 * receive it via the late-subscription table channel rather than the boot-time
 * REST poll (which handles the pre-boot case).
 *
 * @see contracts/websocket-events.contract.md
 * @see App\Http\Controllers\Api\V2\TabletApiController::activeOrder()
 */
class OrderStartedFromPos implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly DeviceOrder $order) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('table.'.$this->order->table_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.started-from-pos';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'snapshot' => $this->buildSnapshot(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSnapshot(): array
    {
        $this->order->loadMissing(['items']);

        $initialItems = $this->order->items
            ->where('is_refill', false)
            ->map(fn ($it) => $this->buildItemShape($it))
            ->values()
            ->all();

        $refillItems = $this->order->items
            ->where('is_refill', true)
            ->map(fn ($it) => $this->buildItemShape($it))
            ->values()
            ->all();

        $rounds = [];

        if (! empty($initialItems)) {
            $rounds[] = [
                'kind' => 'initial',
                'number' => 1,
                'submittedAt' => $this->order->created_at?->toIso8601String() ?? now()->toIso8601String(),
                'items' => $initialItems,
                'serverOrderId' => $this->order->order_id,
                'serverRefillId' => null,
                'serverTotal' => (float) ($this->order->total ?? 0),
                'pos_originated' => true,
            ];
        }

        if (! empty($refillItems)) {
            $rounds[] = [
                'kind' => 'refill',
                'number' => 2,
                'submittedAt' => $this->order->updated_at?->toIso8601String() ?? now()->toIso8601String(),
                'items' => $refillItems,
                'serverOrderId' => $this->order->order_id,
                'serverRefillId' => null,
                'serverTotal' => 0,
                'pos_originated' => true,
            ];
        }

        return [
            'order_id' => $this->order->order_id,
            'order_number' => $this->order->order_number,
            'table_id' => $this->order->table_id,
            'session_id' => $this->order->session_id,
            'guest_count' => (int) ($this->order->guest_count ?? 0),
            'status' => $this->order->status->value ?? $this->order->status,
            'rounds' => $rounds,
            'discounts' => [],
            'subtotal' => (float) ($this->order->subtotal ?? $this->order->sub_total ?? 0),
            'discount_total' => (float) ($this->order->discount ?? 0),
            'total' => (float) ($this->order->total ?? 0),
            'started_at' => $this->order->created_at?->toIso8601String() ?? now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildItemShape(mixed $item): array
    {
        return [
            'id' => $item->menu_id,
            'menu_id' => $item->menu_id,
            'name' => $item->menu?->name ?? $item->menu?->receipt_name ?? "Menu #{$item->menu_id}",
            'quantity' => (int) $item->quantity,
            'price' => (float) $item->price,
            'isUnlimited' => false,
            'category' => null,
            'img_url' => null,
        ];
    }
}
