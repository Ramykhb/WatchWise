<?php

namespace App\Gemini\Providers;

use App\Gemini\InterfaceServices\GeminiServiceInterface;
use App\Gemini\Services\GeminiService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the Gemini module and defines the limiter shared by every route that calls Gemini.
 */
class GeminiModuleServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        GeminiServiceInterface::class => GeminiService::class,
    ];

    public function boot(): void
    {
        // Gemini calls cost money, so they get the strictest limit.
        RateLimiter::for('ai', fn (Request $request) => Limit::perMinute(10)->by('ip:'.$request->ip()));
    }
}
