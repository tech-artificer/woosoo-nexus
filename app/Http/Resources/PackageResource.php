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
            'krypton_menu_id' => $this->krypton_menu_id === null ? null : (int) $this->krypton_menu_id,
            'name' => $this->name,
            'description' => $this->description,
            'base_price' => $this->base_price === null ? null : (float) $this->base_price,
            'min_meat' => $this->min_meat === null ? null : (int) $this->min_meat,
            'max_meat' => $this->max_meat === null ? null : (int) $this->max_meat,
            'banner_media_id' => $this->banner_media_id,
            'is_active' => (bool) $this->is_active,
            'is_most_popular' => (bool) $this->is_most_popular,
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
