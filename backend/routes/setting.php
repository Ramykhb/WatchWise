<?php

use App\Setting\Controllers\AnnouncementController;
use Illuminate\Support\Facades\Route;

// Loaded from routes/api.php OUTSIDE the verified group (already under the /api prefix). Public.
Route::get('/announcement', [AnnouncementController::class, 'show'])->middleware('throttle:announcement');
