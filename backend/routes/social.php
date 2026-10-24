<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\WatchlistController;
use App\MovieList\Controllers\MovieListController;
use App\MovieNight\Controllers\MovieNightController;
use Illuminate\Support\Facades\Route;

// Loaded from routes/api.php (already under the /api prefix). Everything here needs a signed-in user.
Route::middleware('auth.jwt')->group(function () {
    Route::get('home', HomeController::class);

    Route::prefix('me')->controller(MeController::class)->group(function () {
        Route::get('/', 'show');
        Route::patch('/', 'update');
        Route::delete('/', 'destroy');
        Route::patch('preferences', 'updatePreferences');
        Route::put('password', 'updatePassword')->middleware('throttle:6,1');
        Route::post('avatar', 'uploadAvatar');
        Route::delete('avatar', 'deleteAvatar');
        Route::delete('social/{provider}', 'disconnectSocial')->where('provider', 'google|github|discord');
    });

    Route::get('users', [ProfileController::class, 'index']);
    Route::prefix('users/{user:username}')->controller(ProfileController::class)->group(function () {
        Route::get('/', 'show');
        Route::get('followers', 'followers');
        Route::get('following', 'following');
        Route::post('follow', 'follow');
        Route::delete('follow', 'unfollow');
    });

    Route::apiResource('lists', MovieListController::class)->parameters(['lists' => 'list']);
    Route::apiResource('watchlist', WatchlistController::class)->except('show');

    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/read-all', [NotificationController::class, 'readAll']);
    Route::patch('notifications/{notification}', [NotificationController::class, 'update']);
    Route::delete('notifications/{notification}', [NotificationController::class, 'destroy']);

    Route::get('rooms', [RoomController::class, 'index']);
    Route::post('rooms', [RoomController::class, 'store']);
    Route::get('rooms/{room}/messages', [RoomController::class, 'messages']);
    Route::post('rooms/{room}/messages', [RoomController::class, 'send'])->middleware('throttle:30,1');

    Route::get('movie-nights', [MovieNightController::class, 'index']);
    Route::post('movie-nights', [MovieNightController::class, 'store']);
    Route::get('movie-nights/{night}', [MovieNightController::class, 'show']);
    Route::patch('movie-nights/{night}', [MovieNightController::class, 'update']);
    Route::post('movie-nights/{night}/invites', [MovieNightController::class, 'invite']);
    Route::put('movie-nights/{night}/rsvp', [MovieNightController::class, 'rsvp']);
    Route::put('movie-nights/{night}/vote', [MovieNightController::class, 'vote']);
});
