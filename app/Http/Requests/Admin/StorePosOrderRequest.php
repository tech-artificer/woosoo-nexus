<?php
// Extracted from PosController: validate order creation payloads.

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePosOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guest_count' => ['required', 'integer', 'min:1', 'max:50'],
            'reference' => ['nullable', 'string', 'max:50'],
        ];
    }
}
