<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->is_admin ?? false);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'ip_address' => [
                'required',
                'ip',
                Rule::unique('devices', 'ip_address')
                    ->ignore(optional($this->route('device'))->id)
                    ->whereNull('deleted_at'),
            ],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'table_id' => ['nullable', 'integer', Rule::exists('pos.tables', 'id')],
            'type' => ['nullable', Rule::in(['tablet', 'printer_relay'])],
            'last_ip_address' => ['nullable', 'ip'],
        ];
    }
}
