<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'group'           => $this->group?->name ?? null,
            'groupName'       => $this->groupName ?? $this->group?->name ?? null,
            'category'        => $this->category?->name ?? null,
            'course'          => $this->course?->name ?? null,
            'name'            => $this->name,
            'kitchen_name'    => $this->kitchen_name,
            'receipt_name'    => $this->receipt_name,
            'price'           => number_format((float) ($this->price ?? 0), 2, '.', ','),
            'cost'            => number_format((float) ($this->cost ?? 0), 2, '.', ','),
            'is_taxable'      => $this->is_taxable,
            'is_modifier'     => $this->is_modifier,
            'is_modifier_only' => $this->is_modifier_only,
            'isMod'           => (bool) ($this->is_modifier ?? false),
            'isModOnly'       => (bool) ($this->is_modifier_only ?? false),
            'is_discountable' => $this->is_discountable,
            'img_url'         => $this->image_url,
            'tax'             => $this->whenLoaded('tax', fn () => $this->tax ? new TaxResource($this->tax) : null),
            'tax_amount'      => $this->taxComputation($this->guest_count),
            'modifiers'       => $this->whenLoaded('modifiers', fn () => MenuModifierResource::collection($this->modifiers)),
        ];
    }
}
