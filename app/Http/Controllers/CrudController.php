<?php

namespace App\Http\Controllers;

use App\Services\Cms\CrudService;
use App\Traits\ApiResponse;
use App\Traits\AuthorizesPermissions;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

abstract class CrudController extends BaseController
{
    use AuthorizesPermissions, AuthorizesRequests, DispatchesJobs, ValidatesRequests, ApiResponse;

    protected CrudService $mainService;
    protected ?string $createRequest = null;
    protected ?string $updateRequest = null;

    public function __construct(CrudService $mainService)
    {
        $this->mainService = $mainService;
    }

    public function builder(Request $request): JsonResponse
    {
        $result = $this->mainService->builder($request);
        return $this->showAll($result->item);
    }

    public function index(Request $request): JsonResponse
    {
        $result = $this->mainService->index($request);

        if ($result->valid) {
            return $this->paginatedResponse(
                $result->item['pageResponse'],
                $result->item['items'],
                $result->code
            );
        }

        return $this->errorResponse($result->message, $result->code);
    }

    public function show(int|string $id): JsonResponse
    {
        $result = $this->mainService->show($id);

        if ($result->valid) {
            return $this->showOne($result->item, $result->code);
        }

        return $this->errorResponse($result->message, $result->code);
    }

    public function store(Request $request): JsonResponse
    {
        $data = app($this->createRequest)->validated();
        $result = $this->mainService->store($data);

        if ($result->valid) {
            return $this->showOne($result->item, $result->code);
        }

        return $this->errorResponse($result->message, $result->code);
    }

    public function update(Request $request, int|string $id): JsonResponse
    {
        $data = app($this->updateRequest)->validated();
        $result = $this->mainService->update($data, $id);

        if ($result->valid) {
            return $this->showOne($result->item, $result->code);
        }

        return $this->errorResponse($result->message, $result->code);
    }

    public function destroy(int|string $id): JsonResponse
    {
        $result = $this->mainService->destroy($id);

        return $this->responseMessage($result->message, $result->code, $result->valid);
    }

    public function toggleActiveStatus(int|string $id): JsonResponse
    {
        $result = $this->mainService->toggleActiveStatus($id);

        return $this->responseMessage($result->message, $result->code, $result->valid);
    }

    public function reOrder(Request $request): JsonResponse
    {
        $this->mainService->reOrder($request);

        return $this->successMessage("Order updated successfully", 200);
    }

    public function export(Request $request): BinaryFileResponse
    {
        return $this->mainService->export($request);
    }
}
