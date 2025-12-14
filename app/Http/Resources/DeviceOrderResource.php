<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceOrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Avoid querying the external POS `tables` during tests. When
        // running in the `testing` environment, return null so resources
        // don't attempt a POS DB connection which CI does not provide.
        $table = null;
        if (! (app()->environment('testing') || env('APP_ENV') === 'testing')) {
            try {
                $table = $this->table?->checkTableStatus();
            } catch (\Throwable $e) {
                report($e);
                $table = null;
            }
        }
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'order_number' => $this->order_number,
            'device' => new DeviceResource($this->device),
            'order' => $this->order,
            'table' => $table,
            'tablename' => $table['name'] ?? $this->table?->name ?? null,
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
            'items' => collect($this->items)->map(fn($it) => [
                'id' => $it->id,
                'menu_id' => $it->menu_id,
                'name' => $it->menu?->receipt_name ?? $it->menu?->name ?? null,
                'quantity' => $it->quantity ?? null,
                'price' => $it->price ?? null,
                'subtotal' => $it->subtotal ?? null,
                'note' => $it->notes ?? null,
                'printed' => $it->is_printed ?? false,
            ])->values()->all(),
            'print_events' => $this->whenLoaded('printEvents', function () {
                return collect($this->printEvents)->map(fn($e) => [
                    'id' => $e->id,
                    'event_type' => $e->event_type,
                    'meta' => $e->meta,
                    'is_acknowledged' => $e->is_acknowledged ?? false,
                    'acknowledged_at' => $e->acknowledged_at?->toIso8601String(),
                    'created_at' => $e->created_at?->toIso8601String(),
                ])->values()->all();
            }),
        ];
    }
}
