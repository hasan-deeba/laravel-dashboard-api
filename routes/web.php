<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'Laravel Permission API',
        'version' => '1.0.0',
        'docs' => '/docs/api',
    ]);
});