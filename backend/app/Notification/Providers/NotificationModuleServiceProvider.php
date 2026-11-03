<?php

namespace App\Notification\Providers;

use App\Auth\CurrentUser\CurrentUser;
use App\Notification\InterfaceRepository\NotificationRepositoryInterface;
use App\Notification\InterfaceServices\NotificationFeedServiceInterface;
use App\Notification\InterfaceServices\NotificationServiceInterface;
use App\Notification\Repository\NotificationRepository;
use App\Notification\Services\NotificationFeedService;
use App\Notification\Services\NotificationService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the Notification module's interfaces to their implementations and defines its socket rate limiter.
 */
class NotificationModuleServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        NotificationRepositoryInterface::class => NotificationRepository::class,
        NotificationServiceInterface::class => NotificationService::class,
        NotificationFeedServiceInterface::class => NotificationFeedService::class,
    ];

    public function boot(): void
    {
        // Per signed-in user (auth.jwt runs before throttling).
        RateLimiter::for('notification-socket', fn () => Limit::perMinute(30)->by('notification-socket:'.app(CurrentUser::class)->id()));
    }
}
