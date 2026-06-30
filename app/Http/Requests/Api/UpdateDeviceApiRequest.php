<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Models\Device;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeviceApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The auth:device middleware already guarantees a valid device token;
        // no additional is_admin check applies on the V1 device-authenticated path.
        return $this->user() instanceof Device;
    }

    public function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:255'],
            'ip_address'      => [
                'required',
                'ip',
                Rule::unique('devices', 'ip_address')
                    ->ignore(optional($this->route('device'))->id)
                    ->whereNull('deleted_at'),
            ],
            'port'            => ['nullable', 'integer', 'min:1', 'max:65535'],
            'table_id'        => ['nullable', 'integer', Rule::exists('pos.tables', 'id')],
            'type'            => ['nullable', Rule::in(['tablet', 'printer_relay'])],
            'last_ip_address' => ['nullable', 'ip'],
        ];
    }
}
