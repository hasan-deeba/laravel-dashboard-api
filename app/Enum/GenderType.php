<?php

namespace App\Enum;

enum GenderType : string {

    case MALE = 'MALE';
    case FEMALE = 'FEMALE';


    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function labels(array $except = []): array
    {
        return collect(self::cases())
            ->reject(fn(self $case) => in_array($case->value, $except))
            ->mapWithKeys(fn(self $case) => [
                $case->value => __(
                    "enums.gender_type.{$case->name}"
                )
            ])
            ->toArray();
    }
    public static function label(self $value): ?string
    {
        return static::labels()[$value->value] ?? null;
    }

    public static function colors(): array
    {
        return [
            self::MALE->value => 'primary',
            self::FEMALE->value => 'danger',
        ];
    }
    public static function color(self $value): ?string
    {
        return self::colors()[$value->value] ?? 'primary';
    }
}
