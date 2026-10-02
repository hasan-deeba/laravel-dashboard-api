<?php

namespace App\Http\Requests\Cms\AccessManagement\Roles;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateRoleRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('roles')->ignore($this->route('role'))],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,id'],
        ];
    }
}
