<?php

namespace App\MovieList\Providers;

use App\MovieList\InterfaceRepository\MovieListRepositoryInterface;
use App\MovieList\InterfaceServices\MovieListServiceInterface;
use App\MovieList\Repository\MovieListRepository;
use App\MovieList\Services\MovieListService;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the MovieList module's interfaces to their implementations.
 */
class MovieListModuleServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        MovieListRepositoryInterface::class => MovieListRepository::class,
        MovieListServiceInterface::class => MovieListService::class,
    ];
}
