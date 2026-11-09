<?php

use App\Rating\Controllers\RatingController;
use Illuminate\Support\Facades\Route;

// Loaded from routes/api.php inside its `auth.jwt` + `verified.email` group (already under the /api prefix).
Route::get('ratings/{tmdbId}', [RatingController::class, 'show'])->whereNumber('tmdbId');
Route::put('ratings/{tmdbId}', [RatingController::class, 'update'])->whereNumber('tmdbId')->middleware('throttle:60,1');
