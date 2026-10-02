<?php

namespace App\Enum;

enum OTPType : string {


    case RESET_PASSWORD = 'RESET_PASSWORD';
    case REGISTER = 'REGISTER';


    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function nameForValue(string $value): ?string
    {
        return self::labels()[$value] ?? null;
    }

    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn(self $case) => [
                $case->value => __(
                    "enums.otp_code_type.{$case->name}"
                )
            ])
            ->toArray();
    }
    public static function label(string $value): ?string
    {
        return static::labels()[$value] ?? null;
    }
}
