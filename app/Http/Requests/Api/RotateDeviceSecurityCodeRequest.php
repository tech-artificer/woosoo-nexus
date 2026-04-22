<?php

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
        $deviceId = $this->route('device')?->id;

        return [
            'security_code' => [
                'required',
                'string',
                'regex:/^\d{6}$/',
                // Unique except for this device's own ID (in case of retry)
                "unique:devices,security_code,{$deviceId}",
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
