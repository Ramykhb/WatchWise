<?php

namespace App\User\Providers;

use App\Auth\CurrentUser\CurrentUser;
use App\User\InterfaceRepository\AccountDeletionRepositoryInterface;
use App\User\InterfaceRepository\AccountRepositoryInterface;
use App\User\InterfaceRepository\HomeRepositoryInterface;
use App\User\InterfaceRepository\ProfileRepositoryInterface;
use App\User\InterfaceServices\AccountDeletionServiceInterface;
use App\User\InterfaceServices\AccountServiceInterface;
use App\User\InterfaceServices\EmailChangeServiceInterface;
use App\User\InterfaceServices\HomeServiceInterface;
use App\User\InterfaceServices\ProfileServiceInterface;
use App\User\Repository\AccountDeletionRepository;
use App\User\Repository\AccountRepository;
use App\User\Repository\HomeRepository;
use App\User\Repository\ProfileRepository;
use App\User\Services\AccountDeletionService;
use App\User\Services\AccountService;
use App\User\Services\EmailChangeService;
use App\User\Services\HomeService;
use App\User\Services\ProfileService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the User module's interfaces to their implementations and defines its rate limiters.
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
        EmailChangeServiceInterface::class => EmailChangeService::class,

        AccountDeletionRepositoryInterface::class => AccountDeletionRepository::class,
        AccountDeletionServiceInterface::class => AccountDeletionService::class,
    ];

    public function boot(): void
    {
        // Each request mails an address the member typed, so it is kept low per account (auth.jwt runs first).
        RateLimiter::for('me-email-change', function (): array {
            $userId = app(CurrentUser::class)->id();

            return [
                Limit::perMinute(3)->by('me-email-change:'.$userId),
                Limit::perHour(10)->by('me-email-change-hour:'.$userId),
            ];
        });

        // The confirmation link is opened without a session, so it counts per IP.
        RateLimiter::for('me-email-confirm', fn (Request $request) => Limit::perMinute(20)->by('ip:'.$request->ip()));

        // DELETE /api/me checks a password or mails the account's own address, so it is kept low per account.
        RateLimiter::for('me-account-deletion', function (): array {
            $userId = app(CurrentUser::class)->id();

            return [
                Limit::perMinute(5)->by('me-account-deletion:'.$userId),
                Limit::perHour(10)->by('me-account-deletion-hour:'.$userId),
            ];
        });

        // The emailed deletion links are opened without a session, so they count per IP.
        RateLimiter::for('me-delete-confirm', fn (Request $request) => Limit::perMinute(20)->by('ip:'.$request->ip()));
    }
}
