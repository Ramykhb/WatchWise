<?php

namespace App\Tmdb\Providers;

use App\Tmdb\InterfaceRepository\GenreRepositoryInterface;
use App\Tmdb\InterfaceRepository\MovieRepositoryInterface;
use App\Tmdb\InterfaceServices\GenreServiceInterface;
use App\Tmdb\InterfaceServices\MovieServiceInterface;
use App\Tmdb\Repository\GenreRepository;
use App\Tmdb\Repository\MovieRepository;
use App\Tmdb\Services\GenreService;
use App\Tmdb\Services\MovieService;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the Tmdb module's interfaces to their implementations.
 */
class TmdbModuleServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        MovieRepositoryInterface::class => MovieRepository::class,
        GenreRepositoryInterface::class => GenreRepository::class,

        MovieServiceInterface::class => MovieService::class,
        GenreServiceInterface::class => GenreService::class,
    ];
}
