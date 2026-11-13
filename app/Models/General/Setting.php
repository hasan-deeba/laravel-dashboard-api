<?php

namespace App\Models\General;

use App\Enum\SettingType;
use App\Traits\ActivityLogsTrait;
use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use ActivityLogsTrait, ModelTrait;

    protected $fillable = ['key', 'value'];

    // Declare these so ModelTrait initialization uses real properties rather
    // than creating dynamic attributes that Eloquent would try to persist.
    protected array $extraFields = [];
    protected array $specialFields = [];

    protected $minimumAllowedKey = ['id', 'key', 'value', 'created_at'];
    protected $notAllowedKey = ['updated_at'];
    protected $logExcept = ['created_at', 'updated_at'];

    protected function defaultExtraFields(): array
    {
        return [
            'value' => function () {
                $value = $this->getAttribute('value');
                $definition = config('settings.fields.'.$this->key);

                if (($definition['type'] ?? null) === SettingType::TRANSLATABLE_MARKDOWN) {
                    return $value[app()->getLocale()] ?? null;
                }

                return $value;
            },
        ];
    }

    /**
     * Values are JSON in a portable text column, so strings, booleans and
     * translation maps retain their types instead of being cast to strings.
     */
    protected function value(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === null
                ? null
                : json_decode($value, true, 512, JSON_THROW_ON_ERROR),
            set: fn (mixed $value) => $value === null
                ? null
                : json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        );
    }
}
