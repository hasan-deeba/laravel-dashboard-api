<?php

namespace App\Enum;

enum SettingType: string
{
    case TEXT = 'TEXT';
    case TRANSLATABLE_TEXT = 'TRANSLATABLE_TEXT';
    case BOOLEAN = 'BOOLEAN';
    case IMAGE = 'IMAGE';
    case EMAIL = 'EMAIL';
    case URL = 'URL';
    case PHONE = 'PHONE';
    case COLOR = 'COLOR';
    case LOCALE = 'LOCALE';
    case VERSION = 'VERSION';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function labels(array $except = []): array
    {
        return collect(self::cases())
            ->reject(fn (self $case) => in_array($case->value, $except))
            ->mapWithKeys(fn (self $case) => [
                $case->value => __("enums.setting_type.{$case->name}"),
            ])->toArray();
    }

    public static function label(self $value): ?string
    {
        return static::labels()[$value->value] ?? null;
    }

    public static function colors(): array
    {
        return [
            self::TEXT->value => 'gray',
            self::TRANSLATABLE_TEXT->value => 'primary',
            self::BOOLEAN->value => 'success',
            self::IMAGE->value => 'info',
            self::EMAIL->value => 'primary',
            self::URL->value => 'info',
            self::PHONE->value => 'gray',
            self::COLOR->value => 'warning',
            self::LOCALE->value => 'primary',
            self::VERSION->value => 'warning',
        ];
    }

    public static function color(self $value): ?string
    {
        return self::colors()[$value->value] ?? 'primary';
    }
}
