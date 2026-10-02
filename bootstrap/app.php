<?php

use App\Http\Middleware\EnsureHasPermission;
use App\Http\Middleware\EnsureHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function () {
            Route::prefix('cms')
                ->group(base_path('routes/cms/auth.php'))
                ->group(base_path('routes/cms/accessManagement.php'))
                ->group(base_path('routes/cms/settings.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'permission' => EnsureHasPermission::class,
            'role' => EnsureHasRole::class,
        ]);

        // API middleware
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // CORS
        $middleware->validateCsrfTokens(except: [
            'cms/*',
            'api/*',
        ]);

        //Languages
        $middleware->append(\App\Http\Middleware\LocalizeRoutes::class);
        $middleware->append(\Illuminate\Http\Middleware\HandleCors::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
