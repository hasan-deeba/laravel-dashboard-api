<?php

namespace App\Services\Cms\Settings;

use App\Models\Settings\Notification;
use App\Services\Cms\CrudService;
use App\Services\ResultService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class NotificationService extends CrudService
{
    public function __construct()
    {
        parent::__construct(Notification::class);

        $this->searchColumns(
            normal: ['type', 'data'],
            advanced: ['type', 'data', 'read_at', 'created_at']
        )
            ->orderBy(['created_at'], 'desc')
            ->report(
                headings: ['#ID', 'Type', 'Read At', 'Date'],
                fields: ['notifications.id', 'notifications.type', 'notifications.read_at', 'notifications.created_at']
            );
    }

    public function index(Request $request, int $perPage = 10): ResultService
    {
        $user = $request->user();

        if (! $user) {
            return new ResultService(
                valid: false,
                code: 401,
                message: 'Unauthenticated'
            );
        }

        return new ResultService(
            valid: true,
            code: 200,
            message: 'Success',
            item: [
                'unread_count' => $user->unreadNotifications()->count(),
                'notifications' => $user->notifications()->take($perPage)->get(),
            ]
        );
    }

    public function markAsRead(int|string $id, Authenticatable $user): ResultService
    {
        try {
            $notification = $user->notifications()->findOrFail($id);
            $notification->markAsRead();

            return new ResultService(
                valid: true,
                code: 200,
                message: 'Notification marked as read successfully'
            );
        } catch (ModelNotFoundException $e) {
            return new ResultService(
                valid: false,
                code: 404,
                message: 'Notification not found'
            );
        }
    }

    public function markAllRead(Authenticatable $user): ResultService
    {
        $user->unreadNotifications->markAsRead();

        return new ResultService(
            valid: true,
            code: 200,
            message: 'All notifications marked as read successfully'
        );
    }
}
