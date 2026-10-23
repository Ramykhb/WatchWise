<?php

use App\Http\Controllers\MovieRecommendationController;
use App\Http\Controllers\WeatherController;
use App\Http\Controllers\WeatherMoodController;
use Illuminate\Support\Facades\Route;

Route::get('/weather', WeatherController::class);
Route::post('/weather-mood', WeatherMoodController::class);
Route::get('/movie-recommendations', MovieRecommendationController::class);