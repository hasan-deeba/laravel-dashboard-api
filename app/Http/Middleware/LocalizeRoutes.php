<?php

namespace App\Http\Middleware;

use App\Helpers\AppHelper;
use App\Services\Settings\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class LocalizeRoutes
{
    public function __construct(protected SettingsService $settingsService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        // An explicit supported language wins; otherwise use the shared default.
        // Without an Accept-Language header, do not implicitly choose the first
        // supported language (which would ignore the saved default).
        $preferred = $request->headers->has('Accept-Language')
            ? $request->getPreferredLanguage(AppHelper::languages())
            : null;
        App::setLocale($preferred ?: $this->settingsService->value('localization.default_locale'));

        return $next($request);
    }
}
