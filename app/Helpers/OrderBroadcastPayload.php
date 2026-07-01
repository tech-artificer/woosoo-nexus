<?php

namespace App\Helpers;

use App\Models\DeviceOrder;
use App\Models\Package;

class OrderBroadcastPayload
{
    public static function make(DeviceOrder $order): array
    {
        // App-DB relations are always safe to load.
        $order->loadMissing(['items', 'device', 'serviceRequests']);

        // Table and menu live on the Krypton (POS) connection. Degrade gracefully if POS
        // is unreachable so an order broadcast never 500s the caller (e.g. KDS advance):
        // table falls back to null and item names fall back to the stored item name.
        try {
            $order->loadMissing(['device.table', 'table', 'items.menu']);
        } catch (\Throwable $e) {
            // POS down — resolve POS-backed relations to null so the access below does not
            // re-trigger a lazy load (which would throw again). Table → null; item names
            // fall back to the stored item name.
            $order->setRelation('table', null);
            $order->device?->setRelation('table', null);
            $order->items?->each(fn ($item) => $item->setRelation('menu', null));
        }

        $table = $order->device?->table ?? $order->table;

        // The package itself is stored as its own item row (menu_id = the package's
        // krypton_menu_id) alongside the meat/side items it expands to. Flag it per-item
        // (rather than dropping it) so consumers that want a full line-item breakdown
        // (e.g. admin order review) keep seeing it; KDS filters on this flag client-side.
        $packageMenuIds = Package::pluck('krypton_menu_id')->filter()->all();
        $anchorItem = $order->items?->first(fn ($it) => in_array($it->menu_id, $packageMenuIds, true));
        $packageName = $anchorItem
            ? ($anchorItem->menu?->kitchen_name ?? $anchorItem->menu?->name ?? $anchorItem->name)
            : null;

        return [
            'id' => $order->id,
            'order_id' => $order->order_id,
            'order_number' => $order->order_number,
            'device_id' => $order->device_id,
            'table_id' => $order->table_id,
            'branch_id' => $order->branch_id,
            'session_id' => $order->session_id,
            'status' => $order->status,
            'kds_state' => $order->status->kdsState(),
            'kds_type' => ($order->items?->isNotEmpty() && $order->items->every(fn ($it) => (bool) ($it->is_refill ?? false))) ? 'refill' : 'initial',
            'is_printed' => (bool) ($order->is_printed ?? false),
            'printed_at' => $order->printed_at?->toIso8601String(),
            'printed_by' => $order->printed_by,
            'subtotal' => $order->subtotal ?? $order->sub_total ?? null,
            'tax' => $order->tax,
            'discount' => $order->discount,
            'total' => $order->total,
            'guest_count' => $order->guest_count,
            'package_name' => $packageName,
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
                'name' => $it->menu?->kitchen_name ?? $it->menu?->name ?? $it->name,
                'quantity' => $it->quantity,
                'price' => $it->price,
                'subtotal' => $it->subtotal,
                'is_refill' => (bool) ($it->is_refill ?? false),
                'done' => (bool) ($it->done ?? false),
                'done_at' => $it->done_at?->toIso8601String(),
                'notes' => ($it->notes && $it->notes !== 'Package modifier') ? $it->notes : null,
                'type' => $it->type ?? null,
                'is_package_anchor' => in_array($it->menu_id, $packageMenuIds, true),
            ])->values()->all(),
            'void_reason' => $order->void_reason ?? null,
            'recalled' => $order->recalled ?? 0,
            'serviceRequests' => $order->serviceRequests ?? [],
        ];
    }
}
