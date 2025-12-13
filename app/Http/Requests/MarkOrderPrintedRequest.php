<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkOrderPrintedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'printed_at' => ['nullable', 'date'],
            'printer_id' => ['nullable', 'string', 'max:100'],
        ];
    }
}
