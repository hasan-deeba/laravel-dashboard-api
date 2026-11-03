<?php

namespace App\Http\Requests\Api\Clients\Auth;

use App\Enum\GenderType;
use App\Enum\MaritalStatusType;
use App\Models\Clients\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ClientRegisterRequest extends FormRequest
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
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'age' => ['nullable', 'integer', 'min:1', 'max:150'],
            'gender' => ['required', Rule::in(GenderType::values())],
            'marital_status' => ['required', Rule::in(MaritalStatusType::values())],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . Client::class],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
