<?php

namespace App\Services\Api\General;

use App\Models\Clients\Client;
use App\Models\General\Notification;
use App\Services\ResultService;

class NotificationService
{
    public function index(Client $client, int $perPage = 15): ResultService
    {
        $paginator = $client->notifications()->paginate($perPage);

        return new ResultService(
            valid: true,
            code: 200,
            message: __('messages.Success'),
            item: [
                'notifications' => (new Notification())->transformList($paginator->getCollection()),
                'unread_count' => $client->unreadNotifications()->count(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
            ],
        );
    }
}
