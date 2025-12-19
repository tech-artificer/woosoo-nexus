<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $targetUser = $this->route('user');
        $authUser = $this->user();
        
        // Admin or super-admin can update any user
        if ($authUser?->hasAnyRole(['admin', 'super-admin'])) {
            return true;
        }
        
        // Users can only update their own profile
        return $authUser?->id === $targetUser?->id;
    }

    public function rules()
    {
        $userId = optional($this->route('user'))->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,' . $userId],
            'password' => ['nullable', 'string', 'min:8'],
            'branches' => ['nullable', 'array'],
            'branches.*' => ['integer', 'exists:branches,id'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ];
    }
}
