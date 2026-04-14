<?php
// Audit Fix (2026-04-06): validate package create payloads for admin package management.

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'krypton_menu_id' => ['required', 'integer', 'min:1', 'unique:packages,krypton_menu_id'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'modifiers' => ['nullable', 'array'],
            'modifiers.*.krypton_menu_id' => ['required', 'integer', 'min:1'],
            'modifiers.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
