<?php

namespace App\Enum;

enum ActivityLogType : string {
    case draft = 'draft';
    case updated = 'updated';
    case created = 'created';
    case deleted = 'deleted';
    case restored = 'restored';


    public static function labels(array $except = []): array
    {
        return collect(self::cases())
            ->reject(fn(self $case) => in_array($case->value, $except))
            ->mapWithKeys(fn(self $case) => [
                $case->value => __(
                    "enums.activity_log_type.{$case->name}"
                )
            ])
            ->toArray();
    }
    public static function label(string $value): ?string
    {
        return static::labels()[$value] ?? null;
    }

    public static function colors(): array
    {
        return [
            self::draft->value => 'gray',
            self::updated->value => 'warning',
            self::created->value => 'success',
            self::deleted->value => 'danger',
            self::restored->value => 'info',
        ];
    }
    public static function color(string $value): ?string
    {
        return self::colors()[$value] ?? 'primary';
    }
}
