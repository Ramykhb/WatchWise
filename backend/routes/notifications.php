<?php

use App\Notification\Controllers\NotificationController;
use App\Notification\Controllers\NotificationSocketController;
use Illuminate\Support\Facades\Route;

// Loaded from routes/api.php inside its `auth.jwt` + `verified.email` group (already under the /api prefix).
Route::get('notifications', [NotificationController::class, 'index']);
Route::post('notifications/read-all', [NotificationController::class, 'readAll']);
Route::post('notifications/socket-auth', [NotificationSocketController::class, 'store'])->middleware('throttle:notification-socket');
Route::patch('notifications/{notification}', [NotificationController::class, 'update'])->whereNumber('notification');
Route::delete('notifications/{notification}', [NotificationController::class, 'destroy'])->whereNumber('notification');
