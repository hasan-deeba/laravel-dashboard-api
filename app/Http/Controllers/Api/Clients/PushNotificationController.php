<?php

namespace App\Http\Controllers\Api\Clients;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Clients\TestPushNotificationRequest;
use App\Models\Clients\Client;
use App\Services\Api\Clients\ClientPushNotificationService;
use App\Traits\ClientApiResponse;
use Illuminate\Http\JsonResponse;

class PushNotificationController extends Controller
{
    use ClientApiResponse;

    public function __construct(protected ClientPushNotificationService $pushService)
    {
    }

    public function sendTest(TestPushNotificationRequest $request): JsonResponse
    {
        /** @var Client $client */
        $client = $request->user();
        $data = $request->validated();

        return $this->respond($this->pushService->sendTest($client, $data['title'], $data['body']));
    }
}
