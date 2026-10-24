<?php

use App\Auth\Controllers\AuthController;
use App\Http\Controllers\MovieRecommendationController;
use App\Http\Controllers\WeatherController;
use App\Http\Controllers\WeatherMoodController;
use App\Tmdb\Controllers\GenreController;
use App\Tmdb\Controllers\MovieController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth-register');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth-login');
    Route::post('refresh', [AuthController::class, 'refresh'])->middleware('throttle:auth-refresh');
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me'])->middleware('auth.jwt');
});

require __DIR__.'/auth_flows.php';

Route::get('/weather', WeatherController::class);
Route::post('/weather-mood', WeatherMoodController::class);
Route::get('/movie-recommendations', MovieRecommendationController::class);
Route::middleware('throttle:tmdb')->group(function () {
    Route::get('/movies', [MovieController::class, 'index']);
    Route::get('/movies/search', [MovieController::class, 'search']);
    Route::get('/movies/discover', [MovieController::class, 'discover']);
    Route::get('/movies/{id}', [MovieController::class, 'show'])->whereNumber('id');
    Route::get('/genres', [GenreController::class, 'index']);
});
