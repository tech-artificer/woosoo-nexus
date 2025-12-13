<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GetUnprintedOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_id' => ['required', 'integer'],
            'since' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
