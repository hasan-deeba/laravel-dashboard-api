<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$permissions
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Admin bypass - admin has all permissions
        if ($user->hasRole('super_admin')) {
            return $next($request);
        }

        // Check if user has any of the required permissions
        if (!$user->hasAnyPermission($permissions)) {
            return response()->json([
                'message' => 'Forbidden. You do not have the required permission.',
                'required_permissions' => $permissions,
            ], 403);
        }

        return $next($request);
    }
}
