<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * App-wide setup only. Each module (Auth, Genre, Movie, Gemini, Weather, MovieRecommendation)
 * registers its own bindings, listeners and rate limiters in its own provider. The `tmdb`
 * limiter lives here because the Genre and Movie routes share it.
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('tmdb', fn (Request $request) => Limit::perMinute(60)->by('ip:'.$request->ip()));
    }
}
