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
            'port' => ['nullable', 'integer'],
            'table_id' => ['nullable', 'integer', Rule::exists('pos.tables', 'id')],
        ];
    }
}
