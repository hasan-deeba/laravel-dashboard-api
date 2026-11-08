<?php

namespace App\Http\Requests\Api\Settings;

use App\Enum\AppPlatform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublicSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'platform' => ['nullable', 'required_with:app_version', 'string', Rule::in(AppPlatform::values())],
            'app_version' => [
                'nullable', 'required_with:platform', 'string', 'max:32',
                'regex:/^(0|[1-9][0-9]{0,8})\.(0|[1-9][0-9]{0,8})\.(0|[1-9][0-9]{0,8})$/',
            ],
        ];
    }
}
