<?php

use App\Groq\Controllers\ChatController;
use App\Groq\Controllers\ChatSocketController;
use Illuminate\Support\Facades\Route;

// Loaded from routes/api.php inside its `auth.jwt` + `verified.email` group (already under the /api prefix).
Route::prefix('chat')->group(function () {
    Route::get('connection', [ChatSocketController::class, 'show'])->middleware('throttle:60,1');
    Route::post('auth', [ChatSocketController::class, 'store'])->middleware('throttle:60,1');
    Route::post('messages', [ChatController::class, 'store'])->middleware('throttle:chat');
});
