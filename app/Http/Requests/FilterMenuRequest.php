<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FilterMenuRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
     public function rules()
    {
        return [
            'menu_category_id' => ['nullable', 'integer'],
            'menu_course_type_id' => ['nullable', 'integer'],
            'menu_group_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Return an array of query parameters and their descriptions, which
     * are used to generate the Swagger documentation for this endpoint.
     *
     * @return array<string, string>
     */
    public function queryParameters()
    {
        return [
            'menu_category_id' => 'Filter by menu category ID',
            'menu_course_type_id' => 'Filter by course type ID',
            'menu_group_id' => 'Filter by menu group ID',
            'search' => 'Search by menu name',
        ];
    }
}
