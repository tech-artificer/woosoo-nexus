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
        ];
    }
}
