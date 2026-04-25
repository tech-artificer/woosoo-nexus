<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceRequestResource extends JsonResource
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
            'table_name' => $this->table_name,
            'table_service_name' => $this->table_service_name,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'order_id' => $this->order_id,
            'table_service_id' => $this->table_service_id,
            'acknowledged_at' => $this->acknowledged_at,
            'updated_at' => $this->updated_at,
            'is_active' => $this->is_active,
            'is_archived' => $this->is_archived,
            'created_at' => $this->created_at,
        ];
    }
}
