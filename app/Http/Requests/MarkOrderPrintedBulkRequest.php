<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkOrderPrintedBulkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_ids' => ['required', 'array', 'min:1', 'max:100'],
            'order_ids.*' => ['required', 'integer'],
            'printed_at' => ['nullable', 'date'],
            'printer_id' => ['nullable', 'string', 'max:100'],
        ];
    }
}
