<?php

declare(strict_types=1);

namespace App\Helpers\Filter\Enums;

enum FilterStrategy: string
{
    case GREATER_THAN_OR_EQUAL = 'gte';
    case LESS_THAN_OR_EQUAL = 'lte';
    case EQUAL = 'eq';
    case GREATER_THAN = 'gt';
    case LESS_THAN = 'lt';

    /**
     * Convert strategy to SQL operator.
     */
    public function toOperator(): string
    {
        return match ($this) {
            self::GREATER_THAN_OR_EQUAL => '>=',
            self::LESS_THAN_OR_EQUAL => '<=',
            self::EQUAL => '=',
            self::GREATER_THAN => '>',
            self::LESS_THAN => '<',
        };
    }

    /**
     * Create a FilterStrategy from a string, defaulting to EQUAL for unknown strategies.
     */
    public static function fromString(string $strategy): self
    {
        return self::tryFrom($strategy) ?? self::EQUAL;
    }
}
