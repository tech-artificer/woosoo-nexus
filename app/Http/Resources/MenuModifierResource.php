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

        // Prefer kitchen_name or description when the POS `name` is just a bare
        // receipt code (e.g. "P1", "B10", "C1"). Bare codes match a single letter
        // followed by one or two digits with nothing else.
        $displayName = $this->name;
        if (preg_match('/^[A-Za-z]\d{1,2}$/', trim((string) $displayName))) {
            $displayName = $this->kitchen_name ?: $this->description ?: $displayName;
        }

        return [
            'id'              => $this->id,
            'group'           => $this->group?->name ?? null,
            'groupName'       => $groupLabel,
            'name'            => $displayName,
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
