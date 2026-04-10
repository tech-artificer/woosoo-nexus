<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use App\Http\Resources\BaseResource;

class DeviceOrderResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Avoid querying the external POS `tables` or other POS models
        // during tests (they would hit the `pos` connection). In testing
        // environments, return null/fallbacks so resources remain stable.
        $isTesting = app()->environment('testing') || env('APP_ENV') === 'testing';
        $items = $this->relationLoaded('items') ? $this->items : collect();
        $device = $this->relationLoaded('device') ? $this->device : null;
        $tableRelation = $this->relationLoaded('table') ? $this->table : null;
        $orderRelation = $this->relationLoaded('order') ? $this->order : null;

        $table = null;
        if (! $isTesting && $tableRelation) {
            try {
                $table = $tableRelation->checkTableStatus();
            } catch (\Throwable $e) {
                report($e);
                $table = null;
            }
        }

        $packageId = collect($items)
            ->map(function ($item) {
                return $item->ordered_menu_id ?? null;
            })
            ->first(function ($orderedMenuId) {
                return ! is_null($orderedMenuId);
            });

        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'order_uuid' => $this->order_uuid,
            'order_number' => $this->order_number,
            'package_id' => $packageId,
            'device' => $device ? new DeviceResource($device) : null,
            'order' => $isTesting ? null : $orderRelation,
            'table' => $table,
            'tablename' => $table['name'] ?? $tableRelation?->name ?? null,
            'status' => $this->status,
            // Expose monetary fields so clients (devices/clients) can display totals immediately
            'subtotal' => $this->subtotal ?? ($this->meta['order_check']->subtotal_amount ?? null),
            'tax' => $this->tax ?? ($this->meta['order_check']->tax_amount ?? null),
            'discount' => $this->discount ?? ($this->meta['order_check']->discount_amount ?? null),
            'total' => $this->total ?? ($this->meta['order_check']->total_amount ?? null),
            'guest_count' => $this->guest_count,
            'is_printed' => $this->is_printed ?? false,
            'printed_at' => $this->printed_at?->toIso8601String(),
            'printed_by' => $this->printed_by ?? null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'items' => collect($items)->map(function ($it) use ($isTesting) {
                $menuName = null;
                if (! $isTesting) {
                    try {
                        $menuName = $it->menu?->receipt_name ?? $it->menu?->name ?? null;
                    } catch (\Throwable $_e) {
                        $menuName = null;
                    }
                }

                return [
                    'id' => $it->id,
                    'menu_id' => $it->menu_id,
                    'ordered_menu_id' => $it->ordered_menu_id ?? null,
                    'menu' => ! $isTesting ? [
                        'id' => $it->menu?->id ?? null,
                        'name' => $it->menu?->name ?? null,
                        'receipt_name' => $it->menu?->receipt_name ?? null,
                    ] : null,
                    'name' => $menuName,
                    'status' => $it->status,
                    'quantity' => $it->quantity ?? null,
                    'price' => $it->price ?? null,
                    'subtotal' => $it->subtotal ?? null,
                    'tax' => $it->tax ?? null,
                    'total' => $it->total ?? null,
                    'notes' => $it->notes ?? null,
                    'note' => $it->notes ?? null,
                    'is_refill' => $it->is_refill ?? false,
                    'printed' => $it->is_printed ?? false,
                    'created_at' => $it->created_at?->toIso8601String(),
                ];
            })->values()->all(),
            'print_events' => $this->whenLoaded('printEvents', function () {
                return PrintEventResource::collection($this->printEvents);
            }),
        ];
    }
}
