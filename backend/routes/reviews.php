<?php

use App\Review\Controllers\MovieReviewController;
use Illuminate\Support\Facades\Route;

// Loaded from routes/api.php inside its `auth.jwt` + `verified.email` group (already under the /api prefix).
// The Movie page's Reviews section.
Route::get('reviews', [MovieReviewController::class, 'index']);
Route::put('reviews/mine', [MovieReviewController::class, 'save'])->middleware('throttle:20,1');
Route::delete('reviews/mine', [MovieReviewController::class, 'destroy']);
Route::post('reviews/{review}/helpful', [MovieReviewController::class, 'helpful'])->whereNumber('review')->middleware('throttle:60,1');
Route::delete('reviews/{review}/helpful', [MovieReviewController::class, 'unhelpful'])->whereNumber('review');
Route::post('reviews/{review}/report', [MovieReviewController::class, 'report'])->whereNumber('review')->middleware('throttle:10,1');
