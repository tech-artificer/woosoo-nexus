<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class RotateDeviceSecurityCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Device owner can rotate their own code
        return $this->user()?->can('update', $this->route('device')) ?? false;
    }

    public function rules(): array
    {
        return [
            'security_code' => [
                'required',
                'string',
                'regex:/^\d{6}$/',
                // Codes are stored hashed, so SQL unique checks cannot validate duplicates.
                // Controller-level hash checks enforce uniqueness.
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'security_code.unique' => 'This security code is already assigned.',
            'security_code.regex' => 'Security code must be exactly 6 digits.',
        ];
    }
}
