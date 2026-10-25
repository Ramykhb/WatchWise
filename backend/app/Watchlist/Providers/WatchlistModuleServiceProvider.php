<?php

namespace App\Watchlist\Providers;

use App\Watchlist\InterfaceRepository\WatchlistRepositoryInterface;
use App\Watchlist\InterfaceServices\WatchlistServiceInterface;
use App\Watchlist\Repository\WatchlistRepository;
use App\Watchlist\Services\WatchlistService;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the Watchlist module's interfaces to their implementations.
 */
class WatchlistModuleServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        WatchlistRepositoryInterface::class => WatchlistRepository::class,
        WatchlistServiceInterface::class => WatchlistService::class,
    ];
}
