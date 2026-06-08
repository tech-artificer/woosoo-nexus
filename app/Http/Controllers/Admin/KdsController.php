<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\DeviceOrder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KdsController extends Controller
{
    private const HIDDEN_STATUSES = [
        OrderStatus::COMPLETED,
        OrderStatus::CANCELLED,
        OrderStatus::ARCHIVED,
    ];

    public function index(Request $request): Response
    {
        $orders = DeviceOrder::with(['device.table', 'table', 'items.menu'])
            ->whereNotIn('status', array_map(fn ($s) => $s->value, self::HIDDEN_STATUSES))
            ->orderBy('created_at')
            ->get();

        return Inertia::render('KDS/Display', [
            'title' => 'Kitchen Display',
            'initialTickets' => $orders->map(fn ($order) => $this->toTicket($order))->values(),
        ]);
    }

    private function toTicket(DeviceOrder $order): array
    {
        $table = $order->device?->table ?? $order->table;
        $items = $order->items ?? collect();
        $isRefill = $items->isNotEmpty() && $items->every(fn ($it) => (bool) ($it->is_refill ?? false));
        $now = now();
        $createdAt = $order->created_at;
        $issuedAtMs = $createdAt ? $createdAt->timestamp * 1000 : $now->timestamp * 1000;
        $elapsed = $createdAt ? (int) $createdAt->diffInSeconds($now) : 0;
        $isTerminal = in_array($order->status, [OrderStatus::SERVED, OrderStatus::VOIDED]);
        $frozenElapsed = ($isTerminal && $order->updated_at && $createdAt)
            ? (int) $createdAt->diffInSeconds($order->updated_at)
            : null;

        return [
            'id' => (string) $order->id,
            'table' => $table?->name ?? '—',
            'type' => $isRefill ? 'refill' : 'initial',
            'issued' => $createdAt?->format('g:i A') ?? '',
            'issuedAt' => $issuedAtMs,
            'elapsed' => $elapsed,
            'frozenElapsed' => $frozenElapsed,
            'state' => $this->toKdsState($order->status),
            'items' => $items->map(fn ($it) => [
                'id' => (string) $it->id,
                'qty' => (int) ($it->quantity ?? 1),
                'name' => $it->menu?->receipt_name ?? $it->menu?->name ?? $it->name ?? '',
                'done' => false,
            ])->values()->all(),
            'recalled' => null,
            'voidReason' => null,
        ];
    }

    private function toKdsState(OrderStatus $status): string
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
