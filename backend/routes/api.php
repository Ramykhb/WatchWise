<?php

use App\Http\Controllers\MovieRecommendationController;
use App\Http\Controllers\WeatherController;
use Illuminate\Support\Facades\Route;

Route::get('/weather', WeatherController::class);
Route::get('/movie-recommendations', MovieRecommendationController::class);