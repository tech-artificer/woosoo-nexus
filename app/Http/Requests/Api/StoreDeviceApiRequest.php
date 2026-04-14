<?php

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
            'type' => ['required', 'string', Rule::in(['tablet', 'relay_printer', 'print_bridge', 'direct_printer'])],
            'security_code' => ['required', 'string', 'regex:/^\d{6}$/'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'ip_address' => ['nullable', 'ip'],
        ];
    }
}
