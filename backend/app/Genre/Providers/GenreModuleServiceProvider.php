<?php

namespace App\Genre\Providers;

use App\Genre\InterfaceRepository\GenreRepositoryInterface;
use App\Genre\InterfaceServices\GenreServiceInterface;
use App\Genre\Repository\GenreRepository;
use App\Genre\Services\GenreService;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the Genre module's interfaces to their implementations.
 */
class GenreModuleServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        GenreRepositoryInterface::class => GenreRepository::class,
        GenreServiceInterface::class => GenreService::class,
    ];
}
