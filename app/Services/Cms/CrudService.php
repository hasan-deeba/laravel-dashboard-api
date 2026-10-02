<?php

namespace App\Services\Cms;

use App\Helpers\Filter\SearchFilterHelper;
use App\Services\ResultService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CrudService
{
    private SearchFilterHelper $filterHelper;

    protected string $mainModel;
    protected array $normalSearchColumns = [];
    protected array $allowedAdvanceSearchColumns = [];
    protected array $orderBy = [];
    protected string $sortStrategy = 'desc';

    protected array $reportHeadings = [];
    protected array $reportFields = [];
    protected array $deleteOption = [];

    protected array $beforeSaveHooks = [];
    protected array $afterSaveHooks = [];
    protected array $beforeDeleteHooks = [];

    public function __construct(string $mainModel)
    {
        $this->mainModel = $mainModel;
        $this->filterHelper = new SearchFilterHelper();
    }

    public static function for(string $mainModel): static
    {
        return new static($mainModel);
    }

    /* -------------------------------------------------------------------------- */
    /*                              FLUENT SETTERS                                */
    /* -------------------------------------------------------------------------- */

    public function searchColumns(array $normal = [], array $advanced = []): static
    {
        $this->normalSearchColumns = $normal;
        $this->allowedAdvanceSearchColumns = $advanced;
        return $this;
    }

    public function orderBy(array $orderBy, string $strategy = 'desc'): static
    {
        $this->orderBy = $orderBy;
        $this->sortStrategy = $strategy;
        return $this;
    }

    public function deleteOptions(array $options): static
    {
        $this->deleteOption = $options;
        return $this;
    }

    public function report(array $headings, array $fields): static
    {
        $this->reportHeadings = $headings;
        $this->reportFields = $fields;
        return $this;
    }

    public function beforeSave(array $hooks): static
    {
        $this->beforeSaveHooks = $hooks;
        return $this;
    }

    public function afterSave(array $hooks): static
    {
        $this->afterSaveHooks = $hooks;
        return $this;
    }

    public function beforeDelete(array $hooks): static
    {
        $this->beforeDeleteHooks = $hooks;
        return $this;
    }

    /* -------------------------------------------------------------------------- */
    /*                               CRUD ACTIONS                                 */
    /* -------------------------------------------------------------------------- */

    public function index(Request $request): ResultService
    {
        $query = $this->mainModel::query();

        $query = $this->filterHelper->apply(
            request: $request,
            searchableColumns: $this->normalSearchColumns ?: ['name'],
            allowedAdvancedColumns: $this->allowedAdvanceSearchColumns ?: ['is_active', 'created_at'],
            query: $query
        );

        if (!empty($this->orderBy)) {
            foreach ($this->orderBy as $item) {
                $query->orderBy($item, $this->sortStrategy);
            }
        }

        $perPage = $request->get('per_page', 10);
        $items = $query->paginate($perPage);

        return new ResultService(
            true,
            200,
            'success data',
            [
                'pageResponse' => $items,
                'items' => app($this->mainModel)->transformList($items)
            ]
        );
    }

    public function show($id): ResultService
    {
        $item = $this->mainModel::find($id);

        return new ResultService(
            (bool)$item,
            $item ? 200 : 404,
            $item ? 'Success data' : 'Not found',
            $item ? $item->transformItemExclude() : null
        );
    }

    public function store(array $data): ResultService
    {
        try {
            return DB::transaction(function () use ($data) {
                $data = $this->runBeforeSaveHooks($data, null, true);

                $item = $this->mainModel::create($data);

                $this->runAfterSaveHooks($item, $data, true);

                return new ResultService(item: $item->transformItemInclude());
            });
        } catch (Exception $e) {
            return new ResultService(false, 422, $e->getMessage(), null);
        }
    }

    public function update(array $data, $id): ResultService
    {
        $item = $this->mainModel::find($id);

        if (!$item) {
            return new ResultService(false, 404, 'Not found', null);
        }

        try {
            return DB::transaction(function () use ($item, $data) {
                $data = $this->runBeforeSaveHooks($data, $item, false);

                $item->update($data);

                $this->runAfterSaveHooks($item, $data, false);

                return new ResultService(
                    true,
                    200,
                    'Success data',
                    $item->transformItemExclude()
                );
            });
        } catch (Exception $e) {
            return new ResultService(false, 422, $e->getMessage(), null);
        }
    }

    public function destroy(int $id): ResultService
    {
        $item = $this->mainModel::find($id);

        if (!$item) {
            return new ResultService(false, 404, 'Not found', null);
        }

        if ($this->hasBlockingChildren($item)) {
            return new ResultService(
                false,
                409,
                $this->deleteOption['message'] ?? 'Cannot delete item with active dependencies.',
                null
            );
        }

        try {
            return DB::transaction(function () use ($item) {
                $this->runBeforeDeleteHooks($item);

                $item->delete();

                return new ResultService(
                    true,
                    200,
                    'Item deleted successfully',
                    $item->transformItemInclude()
                );
            });
        } catch (Exception $e) {
            return new ResultService(false, 422, $e->getMessage(), null);
        }
    }

    public function toggleActiveStatus($id): ResultService
    {
        $item = $this->mainModel::find($id);

        if ($item) {
            $item->update(['is_active' => !$item->is_active]);
        }

        return new ResultService(
            (bool)$item,
            $item ? 200 : 404,
            $item ? 'Success data' : 'Not found',
            $item ? $item->transformItemExclude() : null
        );
    }

    public function reOrder(Request $request)
    {
        return DB::transaction(function () use ($request) {
            foreach ($request->get('data', []) as $row) {
                $item = $this->mainModel::find($row['id']);
                if ($item) {
                    $item->sort_order = $row['sort_order'];
                    $item->save();
                }
            }
        });
    }

    public function export(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return Excel::download(
            new Report($this->mainModel, $this->reportFields, $this->reportHeadings, $request->get('filters', [])),
            'page.xlsx'
        );
    }

    /* -------------------------------------------------------------------------- */
    /*                              HOOK RUNNERS                                  */
    /* -------------------------------------------------------------------------- */

    protected function runBeforeSaveHooks(array $data, $item, bool $isCreated): array
    {
        foreach ($this->beforeSaveHooks as $hook) {
            $executable = is_string($hook) ? app($hook) : $hook;
            $modifiedData = $executable($data, $item, $isCreated);

            if (is_array($modifiedData)) {
                $data = $modifiedData;
            }
        }
        return $data;
    }

    protected function runAfterSaveHooks($item, array $data, bool $isCreated): void
    {
        foreach ($this->afterSaveHooks as $hook) {
            $executable = is_string($hook) ? app($hook) : $hook;
            $executable($item, $data, $isCreated);
        }
    }

    protected function runBeforeDeleteHooks($item): void
    {
        foreach ($this->beforeDeleteHooks as $hook) {
            $executable = is_string($hook) ? app($hook) : $hook;
            $executable($item);
        }
    }

    protected function hasBlockingChildren($item): bool
    {
        if (empty($this->deleteOption['child'])) {
            return false;
        }

        return $item->{$this->deleteOption['child']}->isNotEmpty();
    }
}
