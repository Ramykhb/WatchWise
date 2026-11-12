<?php

namespace App\Report\Providers;

use App\Auth\CurrentUser\CurrentUser;
use App\Report\InterfaceRepository\ReportRepositoryInterface;
use App\Report\InterfaceServices\ReportServiceInterface;
use App\Report\Repository\ReportRepository;
use App\Report\Services\ReportService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the Report module's interfaces to their implementations and defines its rate limiter.
 */
class ReportModuleServiceProvider extends ServiceProvider
{
    /** Reports a member may file in an hour on messages, lists, rooms and profiles (review reports keep their own `throttle:10,1`). */
    private const REPORTS_PER_HOUR = 20;

    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        ReportRepositoryInterface::class => ReportRepository::class,
        ReportServiceInterface::class => ReportService::class,
    ];

    public function boot(): void
    {
        // Per signed-in user (auth.jwt runs before throttling), shared by the message, list, room and profile report routes.
        RateLimiter::for('report', fn () => Limit::perHour(self::REPORTS_PER_HOUR)->by('report:'.app(CurrentUser::class)->id()));
    }
}
