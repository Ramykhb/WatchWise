<?php

use App\Weather\Controllers\WeatherController;
use Illuminate\Support\Facades\Route;

// Loaded from routes/api.php OUTSIDE the verified group (already under the /api prefix). Public.
Route::get('/weather', [WeatherController::class, 'show'])->middleware('throttle:weather');
Route::post('/weather-mood', [WeatherController::class, 'mood'])->middleware('throttle:ai');
