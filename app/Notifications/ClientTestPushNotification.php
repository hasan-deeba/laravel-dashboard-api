<?php

namespace App\Notifications;

use App\Models\Clients\Client;
use App\Notifications\Channels\FcmChannel;
use App\Notifications\Messages\FcmMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ClientTestPushNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $title,
        public readonly string $body,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toFcm(Client $notifiable): FcmMessage
    {
        return new FcmMessage($this->title, $this->body, ['kind' => 'test_push']);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'kind' => 'test_push',
        ];
    }
}
