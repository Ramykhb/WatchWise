<?php

use App\Auth\Providers\AuthModuleServiceProvider;
use App\Gemini\Providers\GeminiModuleServiceProvider;
use App\Groq\Providers\GroqModuleServiceProvider;
use App\MovieRecommendation\Providers\MovieRecommendationModuleServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\AuthorizationServiceProvider;
use App\Tmdb\Providers\TmdbModuleServiceProvider;
use App\Weather\Providers\WeatherModuleServiceProvider;

return [
    AppServiceProvider::class,
    AuthorizationServiceProvider::class,
    AuthModuleServiceProvider::class,
    TmdbModuleServiceProvider::class,
    GeminiModuleServiceProvider::class,
    GroqModuleServiceProvider::class,
    WeatherModuleServiceProvider::class,
    MovieRecommendationModuleServiceProvider::class,
];
