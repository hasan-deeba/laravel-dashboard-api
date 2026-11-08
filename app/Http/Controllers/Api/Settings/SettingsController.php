<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Settings\PublicSettingsRequest;
use App\Services\Settings\SettingsService;
use App\Traits\ClientApiResponse;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    use ClientApiResponse;

    public function __construct(protected SettingsService $settingsService)
    {
    }

    public function index(PublicSettingsRequest $request): JsonResponse
    {
        $result = $this->settingsService->publicSettings($request->validated());

        // Startup gates must see lock/maintenance changes on their next check.
        return $this->respond($result)->header('Cache-Control', 'no-store');
    }
}
