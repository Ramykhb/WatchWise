<?php

namespace App\Groq\Providers;

use App\Groq\InterfaceServices\GroqServiceInterface;
use App\Groq\InterfaceServices\GroqStructuredOutputInterface;
use App\Groq\Services\GroqService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/** Binds the Groq module's service contract. */
class GroqModuleServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        GroqServiceInterface::class => GroqService::class,
        GroqStructuredOutputInterface::class => GroqService::class,
    ];

    public function boot(): void
    {
        RateLimiter::for('ai', fn (Request $request) => Limit::perMinute(10)->by('ip:'.$request->ip()));
    }
}
