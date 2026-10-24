<?php

namespace App\MovieNight\Providers;

use App\MovieNight\InterfaceRepository\MovieNightRepositoryInterface;
use App\MovieNight\InterfaceServices\MovieNightServiceInterface;
use App\MovieNight\Repository\MovieNightRepository;
use App\MovieNight\Services\MovieNightService;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the MovieNight module's interfaces to their implementations.
 */
class MovieNightModuleServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        MovieNightRepositoryInterface::class => MovieNightRepository::class,
        MovieNightServiceInterface::class => MovieNightService::class,
    ];
}
