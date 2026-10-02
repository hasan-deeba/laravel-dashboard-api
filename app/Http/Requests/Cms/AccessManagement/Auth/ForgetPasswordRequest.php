<?php

namespace App\Http\Requests\Cms\AccessManagement\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ForgetPasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => 'required|exists:users,email',
        ];
    }
}
