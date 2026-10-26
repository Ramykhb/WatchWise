<?php

use App\MovieList\Controllers\MovieListController;
use App\MovieNight\Controllers\MovieNightController;
use App\Room\Controllers\RoomController;
use App\Room\Controllers\RoomSocketController;
use App\User\Controllers\UserController;
use App\Watchlist\Controllers\WatchlistController;
use Illuminate\Support\Facades\Route;

// Loaded from routes/api.php (already under the /api prefix). Everything here needs a signed-in user.
Route::middleware('auth.jwt')->group(function () {
    Route::get('home', [UserController::class, 'home']);

    Route::prefix('me')->controller(UserController::class)->group(function () {
        Route::get('/', 'showMe');
        Route::patch('/', 'updateMe');
        Route::delete('/', 'destroyMe');
        Route::patch('preferences', 'updatePreferences');
        Route::put('password', 'updatePassword')->middleware('throttle:6,1');
        Route::post('avatar', 'uploadAvatar');
        Route::delete('avatar', 'deleteAvatar');
        Route::delete('social/{provider}', 'disconnectSocial')->where('provider', 'google|github|discord');
    });

    Route::get('users', [UserController::class, 'index']);
    Route::prefix('users/{user:username}')->controller(UserController::class)->group(function () {
        Route::get('/', 'show');
        Route::get('followers', 'followers');
        Route::get('following', 'following');
        Route::post('follow', 'follow');
        Route::delete('follow', 'unfollow');
    });

    Route::apiResource('lists', MovieListController::class)->parameters(['lists' => 'list']);
    Route::post('lists/{list}/items', [MovieListController::class, 'addItem']);
    Route::delete('lists/{list}/items/{tmdbId}', [MovieListController::class, 'removeItem'])->whereNumber('tmdbId');
    Route::apiResource('watchlist', WatchlistController::class)->except('show');

    Route::get('movie-nights', [MovieNightController::class, 'index']);
    Route::post('movie-nights', [MovieNightController::class, 'store']);
    Route::get('movie-nights/{night}', [MovieNightController::class, 'show']);
    Route::patch('movie-nights/{night}', [MovieNightController::class, 'update']);
    Route::post('movie-nights/{night}/invites', [MovieNightController::class, 'invite']);
    Route::put('movie-nights/{night}/rsvp', [MovieNightController::class, 'rsvp']);
    Route::put('movie-nights/{night}/vote', [MovieNightController::class, 'vote']);

    Route::get('rooms', [RoomController::class, 'index']);
    Route::post('rooms', [RoomController::class, 'store']);
    Route::post('rooms/socket-auth', [RoomSocketController::class, 'store'])->middleware('throttle:room-socket');
    Route::prefix('rooms/{room}')->whereNumber('room')->controller(RoomController::class)->group(function () {
        Route::get('messages', 'messages');
        Route::post('messages', 'send')->middleware('throttle:room-messages');
        Route::post('read', 'read')->middleware('throttle:room-reads');
    });
});
