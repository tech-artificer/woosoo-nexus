<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportQueryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool { return true; }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'page' => ['nullable','integer','min:1'],
            'per_page' => ['nullable','integer','min:1','max:500'],
            'sort_by' => ['nullable','string','max:64'],
            'sort_dir' => ['nullable','in:asc,desc'],
            'q' => ['nullable','string','max:255'],
            'filters' => ['nullable','array'],
            'filters.*' => ['nullable','string','max:255'],
        ];
    }
    public function queryParams(): array
    {
        return [
            'page' => (int)($this->input('page', 1)),
            'perPage' => (int)($this->input('per_page', 10)),
            'sortBy' => $this->input('sort_by'),
            'sortDir' => $this->input('sort_dir','asc'),
            'q' => $this->input('q'),
            'filters' => (array)$this->input('filters', []),
        ];
    }
    
}
