<?php

namespace App\Http\Middleware;

use App\Helpers\AppHelper;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class LocalizeRoutes
{
    public function handle(Request $request, Closure $next): Response
    {
        // The default language comes from config/app.php (APP_LOCALE), not
        // from database settings. A supported Accept-Language header can still
        // override it for the current request.
        $preferred = $request->headers->has('Accept-Language')
            ? $request->getPreferredLanguage(AppHelper::languages())
            : null;

        App::setLocale($preferred ?: config('app.locale'));

        return $next($request);
    }
}
