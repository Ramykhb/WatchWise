<?php

use App\Watchlist\Controllers\WatchlistController;
use Illuminate\Support\Facades\Route;

// Loaded from routes/api.php inside its `auth.jwt` + `verified.email` group (already under the /api prefix).
Route::apiResource('watchlist', WatchlistController::class)->except('show');
