<?php

use App\Auth\Providers\AuthModuleServiceProvider;
use App\Gemini\Providers\GeminiModuleServiceProvider;
use App\Genre\Providers\GenreModuleServiceProvider;
use App\Groq\Providers\GroqModuleServiceProvider;
use App\Movie\Providers\MovieModuleServiceProvider;
use App\MovieList\Providers\MovieListModuleServiceProvider;
use App\MovieNight\Providers\MovieNightModuleServiceProvider;
use App\MovieRecommendation\Providers\MovieRecommendationModuleServiceProvider;
use App\Person\Providers\PersonModuleServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\AuthorizationServiceProvider;
use App\Room\Providers\RoomModuleServiceProvider;
use App\User\Providers\UserModuleServiceProvider;
use App\Watchlist\Providers\WatchlistModuleServiceProvider;
use App\Weather\Providers\WeatherModuleServiceProvider;

return [
    AppServiceProvider::class,
    AuthorizationServiceProvider::class,
    AuthModuleServiceProvider::class,
    GenreModuleServiceProvider::class,
    MovieModuleServiceProvider::class,
    PersonModuleServiceProvider::class,
    MovieListModuleServiceProvider::class,
    MovieNightModuleServiceProvider::class,
    RoomModuleServiceProvider::class,
    UserModuleServiceProvider::class,
    WatchlistModuleServiceProvider::class,
    GeminiModuleServiceProvider::class,
    GroqModuleServiceProvider::class,
    WeatherModuleServiceProvider::class,
    MovieRecommendationModuleServiceProvider::class,
];
