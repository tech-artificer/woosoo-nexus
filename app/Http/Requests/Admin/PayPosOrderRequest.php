<?php
// Extracted from PosController: validate order payment payloads.

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PayPosOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_type_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'tip' => ['nullable', 'numeric', 'min:0'],
            'card_company' => ['nullable', 'string', 'max:50'],
            'card_number' => ['nullable', 'string', 'max:30'],
            'unique_code' => ['nullable', 'string', 'max:50'],
            'auth_code' => ['nullable', 'string', 'max:50'],
            'expiration_date' => ['nullable', 'string', 'max:16'],
        ];
    }
}
