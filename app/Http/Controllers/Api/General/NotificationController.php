<?php

namespace App\Http\Controllers\Api\General;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\General\NotificationRequest;
use App\Models\Clients\Client;
use App\Services\Api\General\NotificationService;
use App\Traits\ClientApiResponse;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    use ClientApiResponse;

    public function __construct(protected NotificationService $notificationService)
    {
    }

    public function index(NotificationRequest $request): JsonResponse
    {
        /** @var Client $client */
        $client = $request->user();

        return $this->respond($this->notificationService->index(
            $client,
            (int) $request->validated('per_page', 15),
        ));
    }
}
