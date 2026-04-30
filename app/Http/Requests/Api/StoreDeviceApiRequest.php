<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Models\Device;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeviceApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Device::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(['tablet', 'printer_relay', 'relay_printer', 'print_bridge', 'direct_printer'])],
            // Codes are stored hashed, so SQL unique checks cannot validate duplicates.
            // Controller-level hash checks enforce uniqueness.
            'security_code' => ['required', 'string', 'regex:/^\d{6}$/'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'ip_address' => ['nullable', 'ip'],
        ];
    }

    public function messages(): array
    {
        return [
            'security_code.unique' => 'This security code is already assigned.',
        ];
    }
}
