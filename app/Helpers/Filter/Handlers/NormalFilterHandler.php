<?php

declare(strict_types=1);

namespace App\Helpers\Filter\Handlers;

use App\Helpers\Filter\Contracts\FilterHandler;
use App\Helpers\Filter\DTOs\FilterDTO;
use App\Helpers\Filter\Enums\FilterType;
use Illuminate\Database\Eloquent\Builder;

final class NormalFilterHandler implements FilterHandler
{
    public function apply(Builder $query, FilterDTO $filter): Builder
    {
        //Add an exception for the activity log filter when check a model item activity.
        // like when I'm in roles table ad I need to check a role previous logs
        if ($filter->key === 'subject_type') {
            return $query->where('subject_type', 'like', '%\\' . $filter->value);
        }
        return $query->where($filter->key, $filter->value);
    }

    public function supports(string $type): bool
    {
        return FilterType::NORMAL->value === $type;
    }
}
