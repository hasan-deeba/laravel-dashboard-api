<?php

namespace App\Http\Requests\Cms\Settings;

use App\Enum\AppPlatform;
use App\Enum\SettingType;
use App\Models\Settings\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // The CMS controller enforces settings.edit.
    }

    public function rules(): array
    {
        $rules = [];
        foreach (config('settings.fields', []) as $key => $definition) {
            $parts = explode('.', $key);
            for ($i = 1; $i < count($parts); $i++) {
                $rules[implode('.', array_slice($parts, 0, $i))] = ['sometimes', 'array'];
            }

            $rules[$key] = ['sometimes', ...($definition['nullable'] ? ['nullable'] : [])];
            $rules[$key] = [...$rules[$key], ...match ($definition['type']) {
                SettingType::TEXT => ['string', 'max:'.($definition['max_length'] ?? 255)],
                SettingType::TRANSLATABLE_TEXT => ['array'],
                SettingType::BOOLEAN => ['boolean'],
                SettingType::IMAGE => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:max_width=4096,max_height=4096'],
                SettingType::EMAIL => ['email:rfc', 'max:254'],
                SettingType::URL => ['url:http,https', 'max:2048'],
                SettingType::PHONE => ['string', 'max:40', 'regex:/^\+?[0-9 ().-]+$/'],
                SettingType::COLOR => ['string', 'regex:/^#[0-9a-fA-F]{6}$/'],
                SettingType::VERSION => ['string', 'max:32', 'regex:/^(0|[1-9][0-9]{0,8})\.(0|[1-9][0-9]{0,8})\.(0|[1-9][0-9]{0,8})$/'],
            }];

            if ($definition['type'] === SettingType::TRANSLATABLE_TEXT) {
                $max = 'max:'.($definition['max_length'] ?? 255);
                $rules["{$key}.en"] = ['sometimes', 'required', 'string', $max];
                $rules["{$key}.ar"] = ['sometimes', 'nullable', 'string', $max];
            }
        }

        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $definitions = config('settings.fields', []);
            $allowed = array_keys($definitions);
            foreach ($definitions as $key => $definition) {
                if ($definition['type'] === SettingType::TRANSLATABLE_TEXT) {
                    array_push($allowed, "{$key}.en", "{$key}.ar");
                }
            }

            $input = Arr::dot($this->all());
            foreach (array_keys($input) as $key) {
                if (!in_array($key, $allowed, true)) {
                    $validator->errors()->add($key, __('messages.Unknown setting'));
                }
            }
            $hasSetting = false;
            foreach (array_keys($definitions) as $key) {
                // Arr::has also accepts a partial translation such as
                // branding.app_name.ar without requiring the whole object.
                if (Arr::has($this->all(), $key)) {
                    $hasSetting = true;
                    break;
                }
            }
            if (!$hasSetting) {
                $validator->errors()->add('settings', __('messages.No settings provided'));
            }

            // Account for omitted fields in a partial update, not just this payload.
            $defaults = [];
            foreach ($definitions as $key => $definition) {
                $defaults[$key] = $definition['default'];
            }
            $effective = array_replace($defaults, Setting::pluck('value', 'key')->all(), Arr::only($input, array_keys($definitions)));

            foreach (AppPlatform::values() as $platform) {
                if (!$effective["apps.{$platform}.lock_for_update"]) {
                    continue;
                }
                foreach (['version', 'store_url'] as $field) {
                    $key = "apps.{$platform}.{$field}";
                    if (blank($effective[$key])) {
                        $validator->errors()->add($key, __('messages.Update version and store link are required when locked'));
                    }
                }
            }
        }];
    }
}
