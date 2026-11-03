<?php

namespace App\Enum;

enum MaritalStatusType : string {

    case SINGLE = 'SINGLE';
    case MARRIED = 'MARRIED';
    case DIVORCED = 'DIVORCED';
    case WIDOWED = 'WIDOWED';


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
                    "enums.marital_status_type.{$case->name}"
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
            self::SINGLE->value => 'primary',
            self::MARRIED->value => 'success',
            self::DIVORCED->value => 'warning',
            self::WIDOWED->value => 'gray',
        ];
    }
    public static function color(self $value): ?string
    {
        return self::colors()[$value->value] ?? 'primary';
    }
}
