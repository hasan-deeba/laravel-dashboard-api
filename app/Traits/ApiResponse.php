<?php

namespace App\Traits;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function successMessage($message, $code = 200): JsonResponse
    {
        return response()->json(['message' => $message, 'code' => $code], $code);
    }

    protected function errorResponse($message, $code = 400): JsonResponse
    {
        return response()->json(['message' => $message, 'code' => $code], $code);
    }
    protected function errorResponseWithItem($message, $item, $code = 400): JsonResponse
    {
        return response()->json(['message' => $message, 'code' => $code, 'item' => $item], $code);
    }

    public function responseMessage($message, $code = 200, $valid = true): JsonResponse
    {
        return $valid ? $this->successMessage($message, $code) : $this->errorResponse($message, $code);
    }

    protected function paginatedResponse(LengthAwarePaginator $lengthAwarePaginator, $collection, $code = 200): JsonResponse
    {
        return response()->json([
            'data' => $collection,
            'pagination' => [
                'total' => $lengthAwarePaginator->total(),
                'count' => $lengthAwarePaginator->count(),
                'per_page' => $lengthAwarePaginator->perPage(),
                'current_page' => $lengthAwarePaginator->currentPage(),
                'total_pages' => $lengthAwarePaginator->lastPage(),
            ],
        ]);
    }


    protected function showAll($collection, $code = 200): JsonResponse
    {
        return response()->json($collection, $code);

    }

    protected function showOne($model, $code = 200): JsonResponse
    {
        return response()->json($model, $code);
    }

}
