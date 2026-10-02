<?php

declare(strict_types=1);

namespace App\Helpers\Filter;

use App\Helpers\Filter\DTOs\FilterDTO;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class SearchFilterHelper
{
    private FilterRegistry $registry;

    public function __construct(?FilterRegistry $registry = null)
    {
        $this->registry = $registry ?? new FilterRegistry();
    }

    /**
     * Apply both normal search and advanced search filters to the query.
     *
     * @param  array<string>  $searchableColumns        Columns for normal search (like %%)
     * @param  array<string>  $allowedAdvancedColumns   Columns allowed for advanced filtering
     */
    public function apply(
        Request $request,
        array $searchableColumns,
        array $allowedAdvancedColumns,
        Builder $query
    ): Builder {
        $query = $this->applyNormalSearch($request, $searchableColumns, $query);
        $query = $this->applyAdvancedFilters($request, $allowedAdvancedColumns, $query);

        return $query;
    }

    /**
     * Apply a simple LIKE search across multiple columns.
     *
     * @param  array<string>  $columns
     */
    public function applyNormalSearch(
        Request $request,
        array $columns,
        Builder $query
    ): Builder {
        $search = $request->input('search');

        if (blank($search) || empty($columns)) {
            return $query;
        }

        return $query->whereAny($columns, 'like', "%{$search}%");
    }

    /**
     * Apply advanced filters from request.
     *
     * @param  array<string>  $allowedColumns
     */
    public function applyAdvancedFilters(
        Request $request,
        array $allowedColumns,
        Builder $query
    ): Builder {
        $filters = $request->input('advanceSearchFilter', []);

        if (! is_array($filters) || empty($filters)) {
            return $query;
        }

        foreach ($filters as $filterData) {
            $filter = FilterDTO::fromArray($filterData);

            if (! $filter->hasValue() || ! $filter->isAllowed($allowedColumns)) {
                continue;
            }

            $handler = $this->registry->resolve($filter->type->value);
            $query = $handler->apply($query, $filter);
        }

        return $query;
    }

    /**
     * Register a custom filter handler (fluent API).
     */
    public function withCustomHandler(\App\Helpers\Filter\Contracts\FilterHandler $handler): self
    {
        $this->registry->register($handler);

        return $this;
    }
}
