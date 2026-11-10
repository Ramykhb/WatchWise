<?php

use App\MovieNight\Controllers\MovieNightController;
use Illuminate\Support\Facades\Route;

// Loaded from routes/api.php inside its `auth.jwt` + `verified.email` group (already under the /api prefix).
Route::get('movie-nights', [MovieNightController::class, 'index']);
Route::post('movie-nights', [MovieNightController::class, 'store']);
Route::get('movie-nights/{night}', [MovieNightController::class, 'show']);
Route::patch('movie-nights/{night}', [MovieNightController::class, 'update']);
Route::post('movie-nights/{night}/invites', [MovieNightController::class, 'invite']);
Route::put('movie-nights/{night}/rsvp', [MovieNightController::class, 'rsvp']);
Route::put('movie-nights/{night}/vote', [MovieNightController::class, 'vote']);
