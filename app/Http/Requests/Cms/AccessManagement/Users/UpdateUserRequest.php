<?php

namespace App\Http\Requests\Cms\AccessManagement\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email',
                Rule::unique('users')->ignore($this->route('user'))
            ],
            'is_active' => ['boolean'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'roles' => ['required', 'array'],
            'roles.*' => ['exists:roles,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->missing('password')) {
            $this->request->remove('password');
        }
    }
}
