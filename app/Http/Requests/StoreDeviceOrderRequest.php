<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeviceOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [

            'total_amount' => ['required', 'numeric'],
            'note' => ['nullable', 'string'],
            'guest_count' => ['required', 'integer','min:1'],
            'items' => ['required', 'array'],
            'items.*.menu_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric'],
            'items.*.note' => ['nullable', 'string'],
            'items.*.subtotal' => ['required', 'numeric'],
            'items.*.ordered_menu_id' => ['nullable', 'integer'],
            'items.*.tax' => ['nullable', 'numeric'],
            'items.*.discount' => ['nullable', 'numeric'],

            // 'order' => ['required', 'integer'],
            // 'menu_id' => ['required', 'integer', 'exists:menus,id'],
            // 'menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            // 'menu_item_modifiers' => ['required', 'array', 'exists:menu_item_modifiers,id'],
            // 'menu_item_modifiers.*' => ['required', 'integer', 'exists:menu_item_modifiers,id'],
            // 'quantity' => ['required', 'integer'],
            // 'price' => ['required', 'numeric'],
            // 'is_done' => ['required', 'boolean'],
            // 'is_cancelled' => ['required', 'boolean'],  
            // 'is_ready' => ['required', 'boolean'],
        ];
    }
}
