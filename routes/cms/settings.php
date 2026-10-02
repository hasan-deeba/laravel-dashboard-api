<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Cms\AccessManagement\AuthController;
use App\Http\Controllers\Cms\Settings\NotificationController;

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/profile', [AuthController::class, 'me']);

        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    });
