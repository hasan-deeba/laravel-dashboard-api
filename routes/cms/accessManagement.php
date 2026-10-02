<?php

use App\Http\Controllers\Cms\AccessManagement\RoleController;
use App\Http\Controllers\Cms\AccessManagement\UserController;
use App\Http\Controllers\Cms\AccessManagement\ActivityLogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
| Permissions are enforced in controllers via the AuthorizesPermissions trait
| (see $permissionPrefix on each controller). Routes only require auth here.
|
*/

Route::middleware(['auth:sanctum'])->group(function () {
    // Users
    Route::get('/users/builder', [UserController::class, 'builder']);
    Route::resource('users', UserController::class)->except(['create', 'edit']);
    Route::put('/users/{id}/toggle-active', [UserController::class, 'toggleActiveStatus']);
    Route::post('/users/theme', [UserController::class, 'setTheme']);
    Route::get('/activity-logs', [ActivityLogController::class, 'index']);
    Route::get('/activity-logs/{id}', [ActivityLogController::class, 'show']);

    // Roles
    Route::get('/roles/builder', [RoleController::class, 'builder']);
    Route::resource('roles', RoleController::class)->except(['create', 'edit']);
});
