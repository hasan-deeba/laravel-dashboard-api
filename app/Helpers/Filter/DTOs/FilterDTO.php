<?php

declare(strict_types=1);

namespace App\Helpers\Filter\DTOs;

use App\Helpers\Filter\Enums\FilterStrategy;
use App\Helpers\Filter\Enums\FilterType;

final readonly class FilterDTO
{
    public function __construct(
        public string $key,
        public mixed $value,
        public FilterType $type,
        public FilterStrategy $strategy,
    ) {
    }

    /**
     * Create a FilterDTO from an array (e.g., request input).
     */
    public static function fromArray(array $data): self
    {
        return new self(
            key: $data['key'] ?? '',
            value: $data['value'] ?? null,
            type: FilterType::fromString($data['type'] ?? 'normal'),
            strategy: FilterStrategy::fromString($data['strategy'] ?? 'eq'),
        );
    }

    /**
     * Check if the filter has a non-empty value.
     */
    public function hasValue(): bool
    {
        return $this->value !== null
            && $this->value !== ''
            && !(is_array($this->value) && empty($this->value));
    }

    /**
     * Check if the filter key is in the list of allowed columns.
     *
     * @param array<string> $allowedColumns
     */
    public function isAllowed(array $allowedColumns): bool
    {
        return in_array($this->key, $allowedColumns, true);
    }
}
