<?php

namespace App\User\Providers;

use App\User\InterfaceRepository\AccountRepositoryInterface;
use App\User\InterfaceRepository\HomeRepositoryInterface;
use App\User\InterfaceRepository\ProfileRepositoryInterface;
use App\User\InterfaceServices\AccountServiceInterface;
use App\User\InterfaceServices\HomeServiceInterface;
use App\User\InterfaceServices\ProfileServiceInterface;
use App\User\Repository\AccountRepository;
use App\User\Repository\HomeRepository;
use App\User\Repository\ProfileRepository;
use App\User\Services\AccountService;
use App\User\Services\HomeService;
use App\User\Services\ProfileService;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the User module's interfaces to their implementations.
 */
class UserModuleServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        ProfileRepositoryInterface::class => ProfileRepository::class,
        AccountRepositoryInterface::class => AccountRepository::class,
        HomeRepositoryInterface::class => HomeRepository::class,

        ProfileServiceInterface::class => ProfileService::class,
        AccountServiceInterface::class => AccountService::class,
        HomeServiceInterface::class => HomeService::class,
    ];
}
