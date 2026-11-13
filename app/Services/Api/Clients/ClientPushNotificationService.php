<?php

namespace App\Services\Api\Clients;

use App\Helpers\NotificationHelper;
use App\Models\Clients\Client;
use App\Notifications\ClientTestPushNotification;
use App\Services\ResultService;

class ClientPushNotificationService
{
    public function sendTest(Client $client, string $title, string $body): ResultService
    {
        $devices = $client->devices()->get();
        if ($devices->isEmpty()) {
            return new ResultService(false, 409, __('messages.Register this device for notifications first'));
        }

        $sent = NotificationHelper::sendToAppUser($client, new ClientTestPushNotification($title, $body));
        if (!$sent) {
            return new ResultService(false, 503, __('messages.Unable to deliver the test notification'));
        }

        return new ResultService(true, 200, __('messages.Test notification sent'), [
            'devices_targeted' => $devices->count(),
        ]);
    }
}
