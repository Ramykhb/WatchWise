<?php

namespace App\Movie\Providers;

use App\Movie\InterfaceRepository\MovieRepositoryInterface;
use App\Movie\InterfaceServices\MovieServiceInterface;
use App\Movie\Repository\MovieRepository;
use App\Movie\Services\MovieService;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the Movie module's interfaces to their implementations.
 */
class MovieModuleServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        MovieRepositoryInterface::class => MovieRepository::class,
        MovieServiceInterface::class => MovieService::class,
    ];
}
