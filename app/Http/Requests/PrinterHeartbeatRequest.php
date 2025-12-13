<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PrinterHeartbeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'printer_id' => ['required', 'string', 'max:100'],
            'printer_name' => ['nullable', 'string', 'max:255'],
            'bluetooth_address' => ['nullable', 'string', 'max:17'],
            'app_version' => ['nullable', 'string', 'max:20'],
            'session_id' => ['nullable', 'integer'],
            'last_printed_order_id' => ['nullable', 'integer'],
            'timestamp' => ['nullable', 'date'],
        ];
    }
}
