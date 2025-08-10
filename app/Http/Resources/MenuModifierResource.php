<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Number;

class MenuModifierResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {   
        $placeholder = asset('images/menu-placeholder/1.jpg');

        return [
            'id' => $this->id,
            'group' => $this->group->name ?? null,
            'name' => $this->name,
            'category' => $this->category->name ?? null,
            'kitchen_name' => $this->kitchen_name,
            'receipt_name' => $this->receipt_name,
            'price' => Number::format($this->price ?? 0, 2),
            'cost' => Number::format($this->cost ?? 0, 2),
            // 'description' => $this->description,
            // 'is_taxable' => $this->is_taxable,
            'is_available' => $this->is_available,
            'is_discountable' => $this->is_discountable,
            // 'tare_weight' => $this->tare_weight,
            // 'scale_unit' => $this->scale_unit,
            // 'measurement_unit' => $this->measurement_unit,
            // 'is_locked' => $this->is_locked,
            // 'quantity' => $this->quantity,
            'is_modifier' => $this->is_modifier,
            'is_modifier_only' => $this->is_modifier_only,
            'img_url' => $this->image_url ?? $this->image->path ?? $placeholder,
            // 'img_path' => $this->image_url,
        ];
    }
}
