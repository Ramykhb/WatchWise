<?php

use App\MovieList\Controllers\MovieListController;
use Illuminate\Support\Facades\Route;

// Loaded from routes/api.php inside its `auth.jwt` + `verified.email` group (already under the /api prefix).
Route::apiResource('lists', MovieListController::class)->parameters(['lists' => 'list']);
Route::post('lists/{list}/report', [MovieListController::class, 'report'])->middleware('throttle:report');
Route::post('lists/{list}/items', [MovieListController::class, 'addItem']);
Route::delete('lists/{list}/items/{tmdbId}', [MovieListController::class, 'removeItem'])->whereNumber('tmdbId');
