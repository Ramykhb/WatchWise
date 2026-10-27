<?php

namespace App\Cinema\Providers;

use App\Cinema\InterfaceServices\CinemaServiceInterface;
use App\Cinema\Services\CinemaService;
use Illuminate\Support\ServiceProvider;

class CinemaModuleServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        CinemaServiceInterface::class => CinemaService::class,
    ];
}
