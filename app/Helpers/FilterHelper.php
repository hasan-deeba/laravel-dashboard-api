<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Helpers\Filter\SearchFilterHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Backward-compatible FilterHelper wrapper.
 *
 * This class delegates to the new SearchFilterHelper while maintaining
 * the original static API for seamless migration.
 *
 * @deprecated Use SearchFilterHelper directly for new code.
 */
class FilterHelper
{
    private static ?SearchFilterHelper $helper = null;

    /**
     * Apply both normal and advanced search filters.
     *
     * @param  array<string>  $normalSearchColumns
     * @param  array<string>  $allowedAdvanceSearchColumns
     */
    public static function takeCareOfSearch(
        Request $request,
        array $normalSearchColumns,
        array $allowedAdvanceSearchColumns,
        Builder $query
    ): Builder {
        return self::getHelper()->apply(
            $request,
            $normalSearchColumns,
            $allowedAdvanceSearchColumns,
            $query
        );
    }

    /**
     * Apply normal search only.
     *
     * @param  array<string>  $normalSearchColumns
     */
    public static function takeCareOfNormalSearch(
        Request $request,
        array $normalSearchColumns,
        Builder $query
    ): Builder {
        return self::getHelper()->applyNormalSearch($request, $normalSearchColumns, $query);
    }

    /**
     * Apply advanced search filters only.
     *
     * @param  array<string>  $allowedAdvanceSearchColumns
     */
    public static function takeCareOfAdvancedSearchFilter(
        Request $request,
        array $allowedAdvanceSearchColumns,
        Builder $query
    ): Builder {
        return self::getHelper()->applyAdvancedFilters($request, $allowedAdvanceSearchColumns, $query);
    }

    private static function getHelper(): SearchFilterHelper
    {
        if (self::$helper === null) {
            self::$helper = new SearchFilterHelper();
        }

        return self::$helper;
    }
}
