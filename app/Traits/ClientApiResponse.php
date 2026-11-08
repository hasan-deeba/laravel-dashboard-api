<?php

namespace App\Traits;

use App\Services\ResultService;
use Illuminate\Http\JsonResponse;

/**
 * Response helpers for the client-facing API.
 *
 * The HTTP status code carries the result code, so it is NOT repeated in the
 * body. A success body contains a human-readable `message` and, when there is
 * a payload, the payload under `data`. An error body contains only `message`.
 */
trait ClientApiResponse
{
    /**
     * Success response: { message, data? } with the HTTP status as the code.
     */
    protected function successResponse($message, $data = null, int $code = 200): JsonResponse
    {
        $body = ['message' => $message];

        if (!is_null($data)) {
            $body['data'] = $data;
        }

        return response()->json($body, $code);
    }

    /**
     * Error response: { message } with the HTTP status as the code.
     */
    protected function errorResponse($message, int $code = 400): JsonResponse
    {
        return response()->json(['message' => $message], $code);
    }

    /**
     * Map a ResultService to the appropriate client API response.
     */
    protected function respond(ResultService $result): JsonResponse
    {
        return $result->valid
            ? $this->successResponse($result->message, $result->item, $result->code)
            : $this->errorResponse($result->message, $result->code);
    }
}
