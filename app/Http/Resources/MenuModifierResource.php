<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuModifierResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Derive meat group label from receipt_name prefix (P=Pork, B=Beef, C=Chicken).
        $groupLabel = null;
        if ($this->receipt_name) {
            $groupLabel = match (strtoupper(substr((string) $this->receipt_name, 0, 1))) {
                'P' => 'Pork',
                'B' => 'Beef',
                'C' => 'Chicken',
                default => $this->group?->name ?? null,
            };
        } else {
            $groupLabel = $this->group?->name ?? null;
        }

        return [
            'id'              => $this->id,
            'group'           => $this->group?->name ?? null,
            'groupName'       => $groupLabel,
            'name'            => $this->name,
            'category'        => $this->category?->name ?? null,
            'kitchen_name'    => $this->kitchen_name,
            'receipt_name'    => $this->receipt_name,
            'price'           => number_format((float) ($this->price ?? 0), 2, '.', ','),
            'description'     => $this->description,
            'is_taxable'      => $this->is_taxable,
            'is_available'    => $this->is_available,
            'is_discountable' => $this->is_discountable,
            'is_modifier'     => $this->is_modifier,
            'is_modifier_only' => $this->is_modifier_only,
            'isMod'           => (bool) ($this->is_modifier ?? false),
            'isModOnly'       => (bool) ($this->is_modifier_only ?? false),
            'img_url'         => $this->image_url,
        ];
    }
}
