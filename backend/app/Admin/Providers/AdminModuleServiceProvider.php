<?php

namespace App\Admin\Providers;

use App\Admin\InterfaceRepository\AdminStatsRepositoryInterface;
use App\Admin\InterfaceRepository\AdminUserRepositoryInterface;
use App\Admin\InterfaceServices\AdminServiceInterface;
use App\Admin\Repository\AdminStatsRepository;
use App\Admin\Repository\AdminUserRepository;
use App\Admin\Services\AdminService;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the Admin module's interfaces to their implementations.
 */
class AdminModuleServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        AdminStatsRepositoryInterface::class => AdminStatsRepository::class,
        AdminUserRepositoryInterface::class => AdminUserRepository::class,
        AdminServiceInterface::class => AdminService::class,
    ];
}
