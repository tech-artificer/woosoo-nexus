<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Number;

class MenuResource extends JsonResource
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
            'menu_group_id' => $this->menu_group_id ?? null,
            'menu_category_id' => $this->menu_category_id ?? null,
            'menu_course_type_id' => $this->menu_course_type_id ?? null,
            'menu_tax_type_id' => $this->menu_tax_type_id ?? null,
            'group' => $this->group->name ?? null,
            'category' => $this->category->name ?? null,
            'course' => $this->course->name ?? null,
            'name' => $this->name,
            'kitchen_name' => $this->kitchen_name,
            'receipt_name' => $this->receipt_name,
            'price' => Number::format($this->price ?? 0, 2),
            'cost' => Number::format($this->cost ?? 0, 2),
            // 'description' => $this->description,
            'index' => $this->index,
            'is_taxable' => $this->is_taxable,
            // 'is_available' => $this->is_available,
            'is_modifier' => $this->is_modifier,
            'is_modifier_only' => $this->is_modifier_only,
            // 'can_open_price' => $this->can_open_price,
            'is_discountable' => $this->is_discountable,
            // 'tare_weight' => $this->tare_weight,
            // 'scale_unit' => $this->scale_unit,
            'measurement_unit' => $this->measurement_unit,
            // 'auto_counter' => $this->auto_counter,
            'is_locked' => $this->is_locked,
            'quantity' => $this->quantity,
            'in_stock' => $this->in_stock,
            // 'prompt_for_note' => $this->prompt_for_note,
            'is_modifier_only' => $this->is_modifier_only,
            'container_id' => $this->container_id,
            // 'quantity_option' => $this->quantity_option,
            // 'units' => $this->units,
            // 'show_unit_price_on_receipt' => $this->show_unit_price_on_receipt,
            // 'decimal_places_for_qty_input' => $this->decimal_places_for_qty_input,
            // 'prompt_description' => $this->prompt_description,
            // 'serving_count' => $this->serving_count,
            // 'best_seller_candidate' => $this->best_seller_candidate,
            // 'calories' => $this->calories,
            // 'guest_count' => $this->guest_count,
            'img_url' => $this->image_url ?? $this->image->path ?? $placeholder,
            // 'image' => $this->image?->path,
            // 'images' => $this->menuImage,
            // 'img_path' => $this->image_url,
            'tax' => $this->whenLoaded('tax', new TaxResource($this->tax)) ?? null,
            'tax_amount' => $this->taxComputation($this->guest_count),
            'modifiers' => $this->whenLoaded('modifiers', MenuModifierResource::collection($this->loadModifiers())),
            // 'modifiers' => $this->whenLoaded('modifiers', MenuModifierResource::collection($this->modifiers)) ?? null,
            // 'modifiers' => MenuModifierResource::collection($this->getComputedModifiersAttribute()),
        ];
    }
}
