<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePackageConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'min_meat' => ['nullable', 'integer', 'min:0'],
            'max_meat' => ['nullable', 'integer', 'min:0'],
            'min_side' => ['nullable', 'integer', 'min:0'],
            'max_side' => ['nullable', 'integer', 'min:0'],
            'min_dessert' => ['nullable', 'integer', 'min:0'],
            'max_dessert' => ['nullable', 'integer', 'min:0'],
            'min_beverage' => ['nullable', 'integer', 'min:0'],
            'max_beverage' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
