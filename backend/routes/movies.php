<?php

use App\Genre\Controllers\GenreController;
use App\Movie\Controllers\MovieController;
use App\Person\Controllers\PersonController;
use Illuminate\Support\Facades\Route;

// Loaded from routes/api.php OUTSIDE the verified group (already under the /api prefix). Public TMDB catalogue:
// movies, people and genres.
Route::middleware('throttle:tmdb')->group(function () {
    Route::get('/movies', [MovieController::class, 'index']);
    Route::get('/movies/trending', [MovieController::class, 'trending']);
    Route::get('/movies/search', [MovieController::class, 'search']);
    Route::get('/movies/discover', [MovieController::class, 'discover']);
    Route::get('/movies/{id}', [MovieController::class, 'show'])->whereNumber('id');
    Route::get('/people/{id}', [PersonController::class, 'show'])->whereNumber('id');
    Route::get('/people/{id}/collaborators', [PersonController::class, 'collaborators'])->whereNumber('id');
    Route::get('/genres', [GenreController::class, 'index']);
});
