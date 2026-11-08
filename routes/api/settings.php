<?php

use App\Http\Controllers\Api\Settings\SettingsController;
use Illuminate\Support\Facades\Route;

// Deliberately public and outside client.available: clients need branding,
// maintenance status and update instructions even when other APIs are locked.
Route::get('settings', [SettingsController::class, 'index'])
    ->middleware('throttle:60,1')
    ->name('api.settings');
