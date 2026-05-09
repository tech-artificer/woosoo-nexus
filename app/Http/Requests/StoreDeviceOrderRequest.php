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
            // Intent-only fields (tablet staging contract)
            'guest_count'  => ['required', 'integer', 'min:1', 'max:20'],
            'package_id'   => ['required', 'integer', 'min:1'],

            // Client-supplied totals are optional; server always recalculates them.
            'subtotal'      => ['nullable', 'numeric', 'min:0'],
            'tax'           => ['nullable', 'numeric', 'min:0'],
            'discount'      => ['nullable', 'numeric', 'min:0'],
            'total_amount'  => ['nullable', 'numeric', 'min:0'],

            'session_id' => ['nullable', 'integer'],

            // Items: only menu_id + quantity are required; name/price/subtotal are optional
            // (server resolves them from POS menu catalog)
            'items'                    => ['required', 'array', 'min:1'],
            'items.*.menu_id'          => ['required', 'integer', 'min:1'],
            'items.*.quantity'         => ['required', 'integer', 'min:1', 'max:50'],
            'items.*.name'             => ['nullable', 'string'],
            'items.*.price'            => ['nullable', 'numeric', 'min:0'],
            'items.*.note'             => ['nullable', 'string'],
            'items.*.subtotal'         => ['nullable', 'numeric', 'min:0'],
            'items.*.ordered_menu_id'  => ['nullable', 'integer', 'min:1'],
            'items.*.tax'              => ['nullable', 'numeric', 'min:0'],
            'items.*.discount'         => ['nullable', 'numeric', 'min:0'],
            'items.*.is_package'       => ['nullable', 'boolean'],
            'items.*.modifiers'        => ['nullable', 'array'],
            'items.*.modifiers.*.menu_id'  => ['required_with:items.*.modifiers', 'integer'],
            'items.*.modifiers.*.quantity' => ['required_with:items.*.modifiers', 'integer', 'min:1'],
        ];
    }
}
