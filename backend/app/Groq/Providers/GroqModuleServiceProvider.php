<?php

namespace App\Groq\Providers;

use App\Auth\CurrentUser\CurrentUser;
use App\Groq\InterfaceServices\ChatServiceInterface;
use App\Groq\InterfaceServices\GroqChatServiceInterface;
use App\Groq\InterfaceServices\GroqServiceInterface;
use App\Groq\InterfaceServices\GroqStructuredOutputInterface;
use App\Groq\Services\ChatService;
use App\Groq\Services\GroqService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
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
        GroqChatServiceInterface::class => GroqService::class,
        ChatServiceInterface::class => ChatService::class,
    ];

    public function boot(): void
    {
        RateLimiter::for('ai', fn (Request $request) => Limit::perMinute(10)->by('ip:'.$request->ip()));
        RateLimiter::for('chat', fn () => [
            Limit::perMinute(10)->by('chat-minute:'.app(CurrentUser::class)->id()),
            Limit::perHour(100)->by('chat-hour:'.app(CurrentUser::class)->id()),
        ]);
        Broadcast::resolved(function (): void {
            require base_path('routes/channels.php');
        });
    }
}
