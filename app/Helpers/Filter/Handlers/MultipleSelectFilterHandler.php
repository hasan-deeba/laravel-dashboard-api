<?php

declare(strict_types=1);

namespace App\Helpers\Filter\Handlers;

use App\Helpers\Filter\Contracts\FilterHandler;
use App\Helpers\Filter\DTOs\FilterDTO;
use App\Helpers\Filter\Enums\FilterType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

final class MultipleSelectFilterHandler implements FilterHandler
{
    public function apply(Builder $query, FilterDTO $filter): Builder
    {
        $values = is_array($filter->value) ? $filter->value : [$filter->value];
        $model = $query->getModel();

        $relationName = $filter->key;

        // Check if the key/relation exists and is a valid Eloquent Relation
        if (method_exists($model, $relationName)) {
            $relation = $model->{$relationName}();

            if ($relation instanceof Relation) {
                $relatedKey = $relation->getRelated()->getKeyName();

                return $query->whereHas($relationName, function ($q) use ($values, $relatedKey) {
                    $q->whereIn($relatedKey, $values);
                });
            }
        }

        // Fallback to standard column whereIn for non-relational columns
        return $query->whereIn($filter->key, $values);
    }

    public function supports(string $type): bool
    {
        return FilterType::MULTIPLE_SELECT->value === $type;
    }
}
