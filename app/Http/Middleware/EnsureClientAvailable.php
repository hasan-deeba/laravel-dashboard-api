<?php

namespace App\Http\Middleware;

use App\Services\Settings\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClientAvailable
{
    public function __construct(protected SettingsService $settingsService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $result = $this->settingsService->clientAvailability();
        if (!$result->valid) {
            abort($result->code, $result->message);
        }

        return $next($request);
    }
}
