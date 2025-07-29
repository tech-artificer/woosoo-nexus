<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // 'id' => $this->id,
            'name' => $this->name,
            // 'tax_set_id' => $this->tax_set_id,
            // 'tax_type_id' => $this->tax_type_id,
            // 'is_inclusive' => $this->is_inclusive,
            'percentage' => $this->percentage,
            'rounding' => $this->rounding,
            // 'is_available' => $this->is_available,
        ];
    }
}
