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
            // relationLoaded() is a safe array_key_exists check; getRelation() throws
            // on missing keys in Laravel 12. SP-hydrated models store category/course
            // as raw string attributes, so fall back to getRawOriginal() when not loaded.
            'category'        => $this->relationLoaded('category') ? $this->category?->name : ($this->getRawOriginal('category') ?? null),
            'course'          => $this->relationLoaded('course') ? $this->course?->name : ($this->getRawOriginal('course') ?? null),
            // SP-hydrated models (get_menus_by_group, get_menus_by_category, etc.)
            // return camelCase aliases (menuName) instead of the column name (name).
            // Fall back to menuName so SP and Eloquent paths both populate the field.
            'name'            => $this->name ?? $this->menuName ?? null,
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
