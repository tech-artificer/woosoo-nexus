<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FailPrintEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'error' => ['nullable', 'string', 'max:1000'],
            'printer_name' => ['nullable', 'string', 'max:100'],
            'bluetooth_address' => ['nullable', 'string', 'max:50'],
            'app_version' => ['nullable', 'string', 'max:20'],
        ];
    }
}
