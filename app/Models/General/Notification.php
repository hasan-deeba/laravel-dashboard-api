<?php
namespace App\Models\General;

use App\Traits\ModelTrait;
use Illuminate\Notifications\DatabaseNotification as BaseNotification;

class Notification extends BaseNotification
{
    use ModelTrait;

    protected array $extraFields = [];
    protected array $specialFields = [];

    protected $minimumAllowedKey = ['id', 'type', 'data', 'read_at', 'created_at'];
    protected $notAllowedKey = ['notifiable_id', 'notifiable_type', 'updated_at'];
}
