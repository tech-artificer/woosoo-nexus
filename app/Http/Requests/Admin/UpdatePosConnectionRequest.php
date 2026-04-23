<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePosConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    public function rules(): array
    {
        return [
            'host'     => ['required', 'string', 'max:253'],
            'port'     => ['required', 'integer', 'min:1', 'max:65535'],
            'database' => ['required', 'string', 'max:64'],
            'username' => ['required', 'string', 'max:80'],
            // Password is optional on update — omitting it keeps the existing stored value.
            'password' => ['nullable', 'string', 'max:255'],
        ];
    }
}
