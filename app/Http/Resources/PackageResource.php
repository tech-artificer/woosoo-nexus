<?php
// Audit Fix (2026-04-06): normalize package + modifier payloads for admin package UI.

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
            'krypton_menu_id' => (int) $this->krypton_menu_id,
            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) $this->sort_order,
            'modifiers' => $this->whenLoaded('modifiers', function () {
                return $this->modifiers
                    ->sortBy('sort_order')
                    ->values()
                    ->map(function ($modifier) {
                        return [
                            'id' => $modifier->id,
                            'krypton_menu_id' => (int) $modifier->krypton_menu_id,
                            'sort_order' => (int) $modifier->sort_order,
                        ];
                    })
                    ->all();
            }, []),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
