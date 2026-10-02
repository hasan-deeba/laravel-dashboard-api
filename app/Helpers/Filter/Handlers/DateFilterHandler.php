<?php

declare(strict_types=1);

namespace App\Helpers\Filter\Handlers;

use App\Helpers\Filter\Contracts\FilterHandler;
use App\Helpers\Filter\DTOs\FilterDTO;
use App\Helpers\Filter\Enums\FilterType;
use Illuminate\Database\Eloquent\Builder;

final class DateFilterHandler implements FilterHandler
{
    public function apply(Builder $query, FilterDTO $filter): Builder
    {
        return $query->whereDate(
            $filter->key,
            $filter->strategy->toOperator(),
            $filter->value
        );
    }

    public function supports(string $type): bool
    {
        return FilterType::DATE->value === $type;
    }
}
