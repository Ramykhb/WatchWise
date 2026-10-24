<?php

namespace App\Weather\Providers;

use App\Weather\InterfaceRepository\WeatherRepositoryInterface;
use App\Weather\InterfaceServices\WeatherMoodServiceInterface;
use App\Weather\InterfaceServices\WeatherServiceInterface;
use App\Weather\Repository\WeatherRepository;
use App\Weather\Services\WeatherMoodService;
use App\Weather\Services\WeatherService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the Weather module's interfaces to their implementations.
 */
class WeatherModuleServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        WeatherRepositoryInterface::class => WeatherRepository::class,

        WeatherServiceInterface::class => WeatherService::class,
        WeatherMoodServiceInterface::class => WeatherMoodService::class,
    ];

    public function boot(): void
    {
        RateLimiter::for('weather', fn (Request $request) => Limit::perMinute(30)->by('ip:'.$request->ip()));
    }
}
