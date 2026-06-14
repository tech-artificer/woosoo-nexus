<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'base_price' => (float) $this->base_price,
            'min_meat' => (int) $this->min_meat,
            'max_meat' => (int) $this->max_meat,
            'min_side' => (int) $this->min_side,
            'max_side' => (int) $this->max_side,
            'min_dessert' => (int) $this->min_dessert,
            'max_dessert' => (int) $this->max_dessert,
            'min_beverage' => (int) $this->min_beverage,
            'max_beverage' => (int) $this->max_beverage,
            'banner_media_id' => $this->banner_media_id,
            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) $this->sort_order,
            'allowed_menus' => $this->whenLoaded(
                'allowedMenus',
                fn () => $this->allowedMenus->values()->all()
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
