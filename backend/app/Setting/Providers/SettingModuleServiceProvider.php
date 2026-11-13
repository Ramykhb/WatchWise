<?php

namespace App\Setting\Providers;

use App\Setting\InterfaceRepository\SettingRepositoryInterface;
use App\Setting\InterfaceServices\SettingServiceInterface;
use App\Setting\Repository\SettingRepository;
use App\Setting\Services\SettingService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the Setting module's interfaces to their implementations and defines its rate limiter.
 */
class SettingModuleServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        SettingRepositoryInterface::class => SettingRepository::class,
        SettingServiceInterface::class => SettingService::class,
    ];

    public function boot(): void
    {
        RateLimiter::for('announcement', fn (Request $request) => Limit::perMinute(60)->by('ip:'.$request->ip()));
    }
}
