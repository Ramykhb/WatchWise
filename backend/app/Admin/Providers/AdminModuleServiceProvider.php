<?php

namespace App\Admin\Providers;

use App\Admin\InterfaceRepository\AdminAuditRepositoryInterface;
use App\Admin\InterfaceRepository\AdminStatsRepositoryInterface;
use App\Admin\InterfaceRepository\AdminUserRepositoryInterface;
use App\Admin\InterfaceServices\AdminAuditServiceInterface;
use App\Admin\InterfaceServices\AdminGenreServiceInterface;
use App\Admin\InterfaceServices\AdminRatingServiceInterface;
use App\Admin\InterfaceServices\AdminReportServiceInterface;
use App\Admin\InterfaceServices\AdminServiceInterface;
use App\Admin\InterfaceServices\AdminSettingServiceInterface;
use App\Admin\Repository\AdminAuditRepository;
use App\Admin\Repository\AdminStatsRepository;
use App\Admin\Repository\AdminUserRepository;
use App\Admin\Services\AdminAuditService;
use App\Admin\Services\AdminGenreService;
use App\Admin\Services\AdminRatingService;
use App\Admin\Services\AdminReportService;
use App\Admin\Services\AdminService;
use App\Admin\Services\AdminSettingService;
use App\Auth\CurrentUser\CurrentUser;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the Admin module's interfaces to their implementations and defines its rate limiter.
 */
class AdminModuleServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        AdminStatsRepositoryInterface::class => AdminStatsRepository::class,
        AdminUserRepositoryInterface::class => AdminUserRepository::class,
        AdminAuditRepositoryInterface::class => AdminAuditRepository::class,
        AdminServiceInterface::class => AdminService::class,
        AdminAuditServiceInterface::class => AdminAuditService::class,
        AdminGenreServiceInterface::class => AdminGenreService::class,
        AdminSettingServiceInterface::class => AdminSettingService::class,
        AdminReportServiceInterface::class => AdminReportService::class,
        AdminRatingServiceInterface::class => AdminRatingService::class,
    ];

    public function boot(): void
    {
        // Per signed-in staff member: auth.jwt runs before throttling, so a guest never reaches the limiter.
        RateLimiter::for('admin', fn () => Limit::perMinute(120)->by('admin:'.app(CurrentUser::class)->id()));
    }
}
