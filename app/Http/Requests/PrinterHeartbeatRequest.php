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
            'device_id' => ['nullable', 'integer'],  // Exists check done in controller for 403 error
            'printer_id' => ['required', 'string', 'max:100'],
            'printer_name' => ['nullable', 'string', 'max:255'],
            'bluetooth_address' => ['nullable', 'string', 'max:17'],
            'app_version' => ['nullable', 'string', 'max:20'],
            'session_id' => ['nullable', 'integer'],
            'last_print_event_id' => ['nullable', 'integer', 'exists:print_events,id'],
            'last_printed_order_id' => ['nullable', 'integer'],
            'timestamp' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:online,printer_connected,queue_pending,queue_failed'],
        ];
    }
}
