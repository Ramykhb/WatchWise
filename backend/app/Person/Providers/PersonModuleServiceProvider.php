<?php

namespace App\Person\Providers;

use App\Person\InterfaceRepository\PersonRepositoryInterface;
use App\Person\InterfaceServices\PersonServiceInterface;
use App\Person\Repository\PersonRepository;
use App\Person\Services\PersonService;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the Person module's interfaces to their implementations.
 */
class PersonModuleServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        PersonRepositoryInterface::class => PersonRepository::class,
        PersonServiceInterface::class => PersonService::class,
    ];
}
