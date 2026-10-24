<?php

use App\Auth\Providers\AuthModuleServiceProvider;
use App\Gemini\Providers\GeminiModuleServiceProvider;
use App\Genre\Providers\GenreModuleServiceProvider;
use App\Groq\Providers\GroqModuleServiceProvider;
use App\Movie\Providers\MovieModuleServiceProvider;
use App\MovieRecommendation\Providers\MovieRecommendationModuleServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\AuthorizationServiceProvider;
use App\Weather\Providers\WeatherModuleServiceProvider;

return [
    AppServiceProvider::class,
    AuthorizationServiceProvider::class,
    AuthModuleServiceProvider::class,
    GenreModuleServiceProvider::class,
    MovieModuleServiceProvider::class,
    GeminiModuleServiceProvider::class,
    GroqModuleServiceProvider::class,
    WeatherModuleServiceProvider::class,
    MovieRecommendationModuleServiceProvider::class,
];
