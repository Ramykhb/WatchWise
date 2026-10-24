<?php

namespace App\Gemini\Providers;

use App\Gemini\InterfaceServices\GeminiServiceInterface;
use App\Gemini\Services\GeminiService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Binds Gemini services and defines the shared rate limit for external AI endpoints.
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
        // External AI calls cost money, so they share a strict per-IP limit.
        RateLimiter::for('ai', fn (Request $request) => Limit::perMinute(10)->by('ip:'.$request->ip()));
    }
}
