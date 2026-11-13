<?php

use App\Http\Controllers\Api\Clients\ClientAuthController;
use App\Http\Controllers\Api\Clients\PushNotificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Client Auth Routes
|--------------------------------------------------------------------------
|
| Public registration / login / OTP / password-reset endpoints for clients,
| plus the authenticated client profile endpoints (auth:clients guard).
|
*/

// Availability applies to public and authenticated client routes, never CMS.
Route::middleware('client.available')->group(function () {
    // Registration page builder (translated enum options)
    Route::get('register/builder', [ClientAuthController::class, 'registrationBuilder']);

    // Public auth endpoints
    Route::post('register', [ClientAuthController::class, 'register']);
    Route::post('verify-otp', [ClientAuthController::class, 'verifyOtp']);
    Route::post('resend-otp', [ClientAuthController::class, 'resendOtp']);
    Route::post('login', [ClientAuthController::class, 'login']);
    Route::post('forget-password', [ClientAuthController::class, 'forgetPassword']);
    Route::post('reset-password', [ClientAuthController::class, 'resetPassword']);

    // Authenticated client endpoints
    Route::middleware('auth:clients')->group(function () {
        Route::get('me', [ClientAuthController::class, 'me']);
        Route::post('logout', [ClientAuthController::class, 'logout']);

        Route::post('notifications/test', [PushNotificationController::class, 'sendTest'])
            ->middleware('throttle:3,1');
    });
});
