<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeviceRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'ip_address' => ['required', 'ip', \Illuminate\Validation\Rule::unique('devices', 'ip_address')],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'table_id' => ['nullable', 'integer', Rule::exists('pos.tables', 'id')],
            // Optional for admin web flow: if omitted, code is auto-generated server-side.
            'security_code' => ['nullable', 'string', 'regex:/^\d{6}$/'],
        ];
    }
}
