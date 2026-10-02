<?php

declare(strict_types=1);

namespace App\Helpers\Filter\Enums;

enum FilterType: string
{
    case DATE = 'date';
    case NUMBER = 'number';
    case TEXT = 'text';
    case MULTIPLE_SELECT = 'multiple-select';
    case NORMAL = 'normal';

    /**
     * Create a FilterType from a string, defaulting to NORMAL for unknown types.
     */
    public static function fromString(string $type): self
    {
        return self::tryFrom($type) ?? self::NORMAL;
    }
}
