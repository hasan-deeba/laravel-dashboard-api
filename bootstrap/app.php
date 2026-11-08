<?php

use App\Http\Middleware\EnsureClientAvailable;
use App\Http\Middleware\EnsureHasPermission;
use App\Http\Middleware\EnsureHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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

            Route::prefix('api')
                ->group(base_path('routes/api/settings.php'))
                ->group(base_path('routes/api/clients.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'permission' => EnsureHasPermission::class,
            'role' => EnsureHasRole::class,
            'client.available' => EnsureClientAvailable::class,
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
        // Keep client-facing HTTP errors in the {message} envelope even when
        // APP_DEBUG is enabled. CMS/shared exception rendering is unchanged.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(
                    ['message' => $e->getMessage() ?: __('messages.Server error')],
                    $e->getStatusCode(),
                    $e->getHeaders(),
                );
            }
        });
    })->create();
