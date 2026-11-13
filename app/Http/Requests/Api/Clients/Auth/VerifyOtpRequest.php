<?php

namespace App\Http\Requests\Api\Clients\Auth;

use App\Enum\AppPlatform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyOtpRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'exists:clients,email'],
            'code' => ['required', 'string'],
            'device_token' => ['nullable', 'string', 'max:512', 'required_with:device_platform'],
            'device_platform' => ['nullable', 'string', Rule::in(AppPlatform::values()), 'required_with:device_token'],
        ];
    }
}
