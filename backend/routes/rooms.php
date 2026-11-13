<?php

use App\Room\Controllers\RoomController;
use App\Room\Controllers\RoomSocketController;
use Illuminate\Support\Facades\Route;

// Loaded from routes/api.php inside its `auth.jwt` + `verified.email` group (already under the /api prefix).
Route::get('rooms', [RoomController::class, 'index']);
Route::post('rooms', [RoomController::class, 'store']);
Route::post('rooms/socket-auth', [RoomSocketController::class, 'store'])->middleware('throttle:room-socket');
Route::prefix('rooms/{room}')->whereNumber('room')->controller(RoomController::class)->group(function () {
    Route::delete('/', 'destroy');
    Route::post('join', 'join');
    Route::post('invites', 'invite')->middleware('throttle:room-invites');
    Route::delete('invite', 'decline');
    Route::get('messages', 'messages');
    Route::post('messages', 'send')->middleware('throttle:room-messages');
    Route::post('read', 'read')->middleware('throttle:room-reads');
    Route::post('report', 'report')->middleware('throttle:report');
    Route::post('messages/{message}/report', 'reportMessage')->whereNumber('message')->scopeBindings()->middleware('throttle:report');
});
