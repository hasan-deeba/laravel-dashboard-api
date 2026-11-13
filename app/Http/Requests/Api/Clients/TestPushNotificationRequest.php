<?php

namespace App\Http\Requests\Api\Clients;

use Illuminate\Foundation\Http\FormRequest;

class TestPushNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:1000'],
        ];
    }
}
