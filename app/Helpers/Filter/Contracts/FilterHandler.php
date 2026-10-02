<?php

declare(strict_types=1);

namespace App\Helpers\Filter\Contracts;

use App\Helpers\Filter\DTOs\FilterDTO;
use Illuminate\Database\Eloquent\Builder;

interface FilterHandler
{
    /**
     * Apply the filter to the query builder.
     */
    public function apply(Builder $query, FilterDTO $filter): Builder;

    /**
     * Check if this handler supports the given filter type.
     */
    public function supports(string $type): bool;
}
