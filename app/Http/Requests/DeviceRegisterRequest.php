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
            'name' => ['nullable', 'string', 'max:255'],
            // Primary contract: security_code. passcode is accepted as a
            // short-lived alias for older tablet bundles. `code` alias removed.
            'passcode'      => ['nullable', 'string', 'regex:/^\d{6}$/', 'required_without:security_code'],
            'security_code' => ['nullable', 'string', 'regex:/^\d{6}$/', 'required_without:passcode'],
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
            'passcode.required'      => 'A security code is required.',
            'passcode.regex'         => 'The security code must be a 6-digit numeric code.',
            'security_code.required' => 'The security code is required.',
            'security_code.regex'    => 'The security code must be a 6-digit numeric code.',
        ];
    }
}
