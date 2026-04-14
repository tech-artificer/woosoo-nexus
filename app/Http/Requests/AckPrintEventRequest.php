<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AckPrintEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'printer_id' => ['nullable', 'string', 'max:100'],
            'printer_name' => ['nullable', 'string', 'max:100'],
            'bluetooth_address' => ['nullable', 'string', 'max:50'],
            'app_version' => ['nullable', 'string', 'max:20'],
            'printed_at' => ['nullable', 'date'],
        ];
    }
}
