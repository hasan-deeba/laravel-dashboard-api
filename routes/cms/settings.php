<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Cms\AccessManagement\AuthController;
use App\Http\Controllers\Cms\Settings\NotificationController;
use App\Http\Controllers\Cms\Settings\SettingsController;

    Route::middleware(['auth:cms'])->group(function () {
        Route::get('/profile', [AuthController::class, 'me']);

        // One shared settings collection for the dashboard, web and mobile.
        // Read requires settings.view; partial updates require settings.edit.
        Route::get('/settings', [SettingsController::class, 'index']);
        Route::get('/settings/builder', [SettingsController::class, 'builder']);
        Route::post('/settings', [SettingsController::class, 'updateSettings']);

        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    });
