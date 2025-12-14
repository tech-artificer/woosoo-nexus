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
            'printed_at' => ['nullable', 'date'],
        ];
    }
}
