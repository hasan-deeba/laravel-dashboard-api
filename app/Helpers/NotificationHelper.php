<?php

namespace App\Helpers;

use App\Models\AccessManagement\Role;
use App\Models\AccessManagement\User as Admin;
use App\Models\Clients\Client;
use App\Models\Clients\ClientDevice;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Spatie\Permission\Models\Permission;
use Throwable;

class NotificationHelper
{
    public static function sendToAppUser(Client $client, Notification $notification): bool
    {
        try {
            NotificationFacade::send([$client], $notification);

            return true;
        } catch (Throwable $exception) {
            Log::warning('Unable to send notification to app client.', [
                'client_id' => $client->getKey(),
                'exception' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /** @param iterable<Client> $clients */
    public static function sendToMultiUser(iterable $clients, Notification $notification): int
    {
        $sent = 0;
        foreach ($clients as $client) {
            if (self::sendToAppUser($client, clone $notification)) {
                $sent++;
            }
        }

        return $sent;
    }

    public static function sendToAdminsWithPermission(string $permission, Notification $notification): int
    {
        try {
            $admins = Permission::query()
                ->where('name', $permission)
                ->where('guard_name', 'cms')
                ->exists()
                ? Admin::permission($permission)->get()
                : collect();

            $superAdmins = Role::query()
                ->where('name', 'super_admin')
                ->where('guard_name', 'cms')
                ->exists()
                ? Admin::role('super_admin')->get()
                : collect();
            $recipients = $admins->merge($superAdmins)->unique('id');

            if ($recipients->isEmpty()) {
                return 0;
            }

            NotificationFacade::send($recipients, $notification);

            return $recipients->count();
        } catch (Throwable $exception) {
            Log::warning('Unable to notify CMS admins with the requested permission.', [
                'permission' => $permission,
                'exception' => $exception->getMessage(),
            ]);

            return 0;
        }
    }

    public static function setDeviceToken(string $token, Client $client, string $platform): ClientDevice
    {
        return ClientDevice::updateOrCreate(
            ['token' => $token],
            ['client_id' => $client->getKey(), 'platform' => $platform],
        );
    }

    public static function removeDeviceToken(string $token, Client $client): void
    {
        $client->devices()->where('token', $token)->delete();
    }
}
