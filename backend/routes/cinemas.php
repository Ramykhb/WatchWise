<?php

use App\Cinema\Controllers\NearbyCinemaController;
use Illuminate\Support\Facades\Route;

// Loaded from routes/api.php OUTSIDE the verified group (already under the /api prefix). Public.
Route::get('/nearby-cinemas', [NearbyCinemaController::class, 'index'])->middleware('throttle:30,1');
