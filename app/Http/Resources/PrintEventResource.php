<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class PrintEventResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_type' => $this->event_type ?? null,
            'meta' => $this->meta ?? null,
            'status' => $this->status ?? null,
            'attempts' => $this->attempts ?? 0,
            'attempt_count' => $this->attempt_count ?? null,
            'failed_at' => $this->dateField($this->failed_at ?? null),
            'is_acknowledged' => $this->is_acknowledged ?? false,
            'acknowledged_at' => $this->dateField($this->acknowledged_at ?? null),
            'printed_at' => $this->dateField($this->printed_at ?? null),
            'created_at' => $this->dateField($this->created_at ?? null),
            'device_order' => $this->relation('deviceOrder', DeviceOrderResource::class),
        ];
    }
}
