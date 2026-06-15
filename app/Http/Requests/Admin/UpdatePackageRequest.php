<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'base_price' => ['nullable', 'numeric', 'min:0'],
            'min_meat' => ['nullable', 'integer', 'min:0'],
            'max_meat' => ['nullable', 'integer', 'min:0', 'gte:min_meat'],
            'banner_media_id' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
            'is_most_popular' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'allowed_menus' => ['nullable', 'array'],
            'allowed_menus.*.krypton_menu_id' => ['required', 'integer', 'min:1'],
            'allowed_menus.*.menu_type' => ['nullable', 'string', 'in:meat'],
            'allowed_menus.*.meat_category_code' => ['nullable', 'string', 'max:50'],
            'allowed_menus.*.extra_price' => ['nullable', 'numeric', 'min:0'],
            'allowed_menus.*.quantity_limit' => ['nullable', 'integer', 'min:1'],
            'allowed_menus.*.is_required' => ['nullable', 'boolean'],
            'allowed_menus.*.is_default' => ['nullable', 'boolean'],
            'allowed_menus.*.is_active' => ['nullable', 'boolean'],
            'allowed_menus.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
