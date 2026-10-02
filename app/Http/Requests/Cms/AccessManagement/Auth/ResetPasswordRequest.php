<?php

namespace App\Http\Requests\Cms\AccessManagement\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => 'required|exists:users,email',
            'code' => 'required|string',
            'password' => ['required', 'confirmed', Password::default()],
        ];
    }
}
