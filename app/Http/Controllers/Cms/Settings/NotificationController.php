<?php

namespace App\Http\Controllers\Cms\Settings;

use App\Http\Controllers\CrudController;
use App\Services\Cms\Settings\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends CrudController
{
    protected string $permissionPrefix = 'notifications';

    public function __construct(NotificationService $notificationService)
    {
        parent::__construct($notificationService);
    }

    public function index(Request $request): JsonResponse
    {
        $result = $this->mainService->index($request);

        if ($result->valid) {
            return $this->showAll($result->item, $result->code);
        }

        return $this->errorResponse($result->message, $result->code);
    }

    public function markAsRead(Request $request, int|string $id): JsonResponse
    {
        $result = $this->mainService->markAsRead($id, $request->user());

        return $this->responseMessage($result->message, $result->code, $result->valid);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $result = $this->mainService->markAllRead($request->user());

        return $this->responseMessage($result->message, $result->code, $result->valid);
    }
}
