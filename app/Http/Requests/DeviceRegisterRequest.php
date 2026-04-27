<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeviceRegisterRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            // Primary contract: passcode (global shared passcode from config)
            // Legacy aliases: security_code and code (same format — any one is sufficient)
            'passcode'      => ['nullable', 'string', 'regex:/^\d{6}$/', 'required_without_all:security_code,code'],
            'security_code' => ['nullable', 'string', 'regex:/^\d{6}$/', 'required_without_all:passcode,code'],
            'code'          => ['nullable', 'string', 'regex:/^\d{6}$/', 'required_without_all:passcode,security_code'],
            'app_version' => ['nullable', 'string', 'max:255'],
            'ip_address' => ['nullable', 'ip'],
            'ip' => ['nullable', 'ip'],
            'user_agent' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'passcode.required'      => 'A passcode is required.',
            'passcode.regex'         => 'The passcode must be a 6-digit numeric code.',
            'security_code.required' => 'The security code is required.',
            'security_code.regex'    => 'The security code must be a 6-digit numeric code.',
            'code.regex'             => 'The code alias must be a 6-digit numeric code.',
            'name.required'          => 'Device name is required.',
        ];
    }
}
