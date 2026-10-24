<?php

use App\Auth\Controllers\AuthController;
use App\Docs\Controllers\DocumentationController;
use App\MovieRecommendation\Controllers\RecommendationController;
use App\Tmdb\Controllers\GenreController;
use App\Tmdb\Controllers\MovieController;
use App\Weather\Controllers\WeatherController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth-register');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth-login');
    Route::post('refresh', [AuthController::class, 'refresh'])->middleware('throttle:auth-refresh');
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me'])->middleware('auth.jwt');
});

require __DIR__.'/auth_flows.php';
require __DIR__.'/social.php';

<<<<<<< HEAD
Route::get('/weather', WeatherController::class);
Route::post('/weather-mood', WeatherMoodController::class);
Route::get('/movie-recommendations', MovieRecommendationController::class);
=======
Route::get('/weather', [WeatherController::class, 'show'])->middleware('throttle:weather');
Route::post('/weather-mood', [WeatherController::class, 'mood'])->middleware('throttle:ai');
Route::get('/movie-recommendations', [RecommendationController::class, 'index'])->middleware('throttle:ai');

>>>>>>> origin/dev
Route::middleware('throttle:tmdb')->group(function () {
    Route::get('/movies', [MovieController::class, 'index']);
    Route::get('/movies/search', [MovieController::class, 'search']);
    Route::get('/movies/discover', [MovieController::class, 'discover']);
<<<<<<< HEAD
    Route::get('/movies/{id}', [MovieController::class, 'show'])->whereNumber('id');
    Route::get('/genres', [GenreController::class, 'index']);
});
=======
    Route::get('/genres', [GenreController::class, 'index']);
});

Route::get('/documentation', [DocumentationController::class, 'ui'])->name('documentation');
Route::get('/documentation/openapi.json', [DocumentationController::class, 'spec'])->name('documentation.spec');
>>>>>>> origin/dev
