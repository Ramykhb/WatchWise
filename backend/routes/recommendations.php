<?php

use App\MovieRecommendation\Controllers\RecommendationController;
use Illuminate\Support\Facades\Route;

// Loaded from routes/api.php inside its `auth.jwt` + `verified.email` group (already under the /api prefix).
// The public `GET /movie-recommendations` lives in routes/recommendations_public.php.
Route::get('/watched-recommendations', [RecommendationController::class, 'watched'])->middleware('throttle:ai');
Route::get('/people-recommendations', [RecommendationController::class, 'people'])->middleware('throttle:ai');
Route::get('/following-recommendations', [RecommendationController::class, 'following'])->middleware('throttle:60,1');
Route::get('/taste-profile', [RecommendationController::class, 'taste'])->middleware('throttle:60,1');
