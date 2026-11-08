<?php

namespace App\Http\Controllers\Cms\Settings;

use App\Http\Controllers\CrudController;
use App\Http\Requests\Cms\Settings\UpdateSettingsRequest;
use App\Services\Settings\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends CrudController
{
    protected string $permissionPrefix = 'settings';

    public function __construct(SettingsService $settingsService)
    {
        $this->permissionMap['updateSettings'] = 'edit';
        parent::__construct($settingsService);
    }

    public function index(Request $request): JsonResponse
    {
        $result = $this->mainService->index($request);

        return $this->showAll($result->item, $result->code);
    }

    public function updateSettings(UpdateSettingsRequest $request): JsonResponse
    {
        $result = $this->mainService->saveSettings($request->validated(), $request->allFiles());

        return $this->responseMessage($result->message, $result->code, $result->valid);
    }
}
