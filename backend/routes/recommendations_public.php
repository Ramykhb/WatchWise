<?php

use App\MovieRecommendation\Controllers\RecommendationController;
use Illuminate\Support\Facades\Route;

// Loaded from routes/api.php OUTSIDE the verified group (already under the /api prefix). Public: guests may use it.
Route::get('/movie-recommendations', [RecommendationController::class, 'index'])->middleware('throttle:ai');
