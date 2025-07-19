<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceResource extends JsonResource
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
            'device_uuid' => $this->device_uuid,
            'branch' => $this->branch,
            'name' => $this->name,
            'table' => $this->whenLoaded('table', fn () => $this->table->name) ?? $this->table_id,
        ];
    }
}
