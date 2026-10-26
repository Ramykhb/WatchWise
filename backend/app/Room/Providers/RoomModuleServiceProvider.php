<?php

namespace App\Room\Providers;

use App\Auth\CurrentUser\CurrentUser;
use App\Room\InterfaceRepository\RoomRepositoryInterface;
use App\Room\InterfaceServices\RoomServiceInterface;
use App\Room\Repository\RoomRepository;
use App\Room\Services\RoomService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the Room module's interfaces to their implementations and defines its rate limiters.
 */
class RoomModuleServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        RoomRepositoryInterface::class => RoomRepository::class,
        RoomServiceInterface::class => RoomService::class,
    ];

    public function boot(): void
    {
        // Per signed-in user (auth.jwt runs before throttling).
        $limits = ['room-messages' => 30, 'room-reads' => 120, 'room-socket' => 60];

        foreach ($limits as $name => $perMinute) {
            RateLimiter::for($name, fn () => Limit::perMinute($perMinute)->by($name.':'.app(CurrentUser::class)->id()));
        }
    }
}
