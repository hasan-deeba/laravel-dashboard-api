<?php

namespace App\Http\Requests\Api\Clients\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ClientLogoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_token' => ['nullable', 'string', 'max:512'],
        ];
    }
}
