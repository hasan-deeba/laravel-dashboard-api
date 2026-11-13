<?php

namespace App\Notifications\Channels;

use App\Models\Clients\Client;
use App\Notifications\Messages\FcmMessage;
use App\Services\Api\Clients\FirebaseCloudMessaging;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class FcmChannel
{
    public function __construct(protected FirebaseCloudMessaging $firebase)
    {
    }

    public function send(object $notifiable, Notification $notification): void
    {
        if (!$notifiable instanceof Client) {
            return;
        }

        $message = $notification->toFcm($notifiable);
        if (!$message instanceof FcmMessage) {
            throw new RuntimeException('The toFcm method must return an FcmMessage.');
        }

        $devices = $notifiable->devices()->get();
        if ($devices->isEmpty()) {
            throw new RuntimeException('No FCM devices are registered for this client.');
        }

        $sent = 0;
        foreach ($devices as $device) {
            try {
                $this->firebase->send($device->token, $message->title, $message->body, $message->data);
                $sent++;
            } catch (Throwable $exception) {
                Log::warning('FCM notification delivery failed.', [
                    'client_id' => $notifiable->getKey(),
                    'device_id' => $device->getKey(),
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        if ($sent === 0) {
            throw new RuntimeException('FCM delivery failed for all registered client devices.');
        }
    }
}
