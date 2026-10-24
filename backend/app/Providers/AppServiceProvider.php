<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * App-wide setup only. Each module (Auth, Tmdb, Gemini, Weather, MovieRecommendation)
 * registers its own bindings, listeners and rate limiters in its own provider.
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        //
    }
}
