<?php

use App\Admin\Providers\AdminModuleServiceProvider;
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
use App\Rating\Providers\RatingModuleServiceProvider;
use App\Review\Providers\ReviewModuleServiceProvider;
use App\Room\Providers\RoomModuleServiceProvider;
use App\User\Providers\UserModuleServiceProvider;
use App\Watchlist\Providers\WatchlistModuleServiceProvider;
use App\Weather\Providers\WeatherModuleServiceProvider;

return [
    AppServiceProvider::class,
    AuthorizationServiceProvider::class,
    AdminModuleServiceProvider::class,
    AuthModuleServiceProvider::class,
    GenreModuleServiceProvider::class,
    MovieModuleServiceProvider::class,
    PersonModuleServiceProvider::class,
    MovieListModuleServiceProvider::class,
    MovieNightModuleServiceProvider::class,
    RatingModuleServiceProvider::class,
    ReviewModuleServiceProvider::class,
    RoomModuleServiceProvider::class,
    UserModuleServiceProvider::class,
    WatchlistModuleServiceProvider::class,
    GeminiModuleServiceProvider::class,
    GroqModuleServiceProvider::class,
    WeatherModuleServiceProvider::class,
    MovieRecommendationModuleServiceProvider::class,
];
