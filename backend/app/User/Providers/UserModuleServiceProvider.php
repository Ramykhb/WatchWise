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
use App\User\InterfaceServices\PendingAccountCleanupServiceInterface;
use App\User\InterfaceServices\ProfileServiceInterface;
use App\User\Repository\AccountDeletionRepository;
use App\User\Repository\AccountRepository;
use App\User\Repository\HomeRepository;
use App\User\Repository\ProfileRepository;
use App\User\Services\AccountDeletionService;
use App\User\Services\AccountService;
use App\User\Services\EmailChangeService;
use App\User\Services\HomeService;
use App\User\Services\PendingAccountCleanupService;
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

        PendingAccountCleanupServiceInterface::class => PendingAccountCleanupService::class,
    ];

    public function boot(): void
    {
        // Per signed-in user (auth.jwt runs before throttling).
        $limits = ['user-search' => 60, 'user-follows' => 60, 'user-avatar' => 10, 'user-profile-update' => 30];

        foreach ($limits as $name => $perMinute) {
            RateLimiter::for($name, fn () => Limit::perMinute($perMinute)->by($name.':'.app(CurrentUser::class)->id()));
        }

        // Each request mails an address the member typed, so it is kept low per account (auth.jwt runs first).
        RateLimiter::for('me-email-change', function (): array {
            $userId = app(CurrentUser::class)->id();

            return [
                Limit::perMinute(3)->by('me-email-change:'.$userId),
                Limit::perHour(10)->by('me-email-change-hour:'.$userId),
            ];
        });

        // The emailed links are opened without a session, so they count per IP; opening a link (GET) and the
        // confirming click on its public page (POST) share the bucket, so one confirmation spends two.
        RateLimiter::for('me-email-confirm', fn (Request $request) => Limit::perMinute(20)->by('ip:'.$request->ip()));

        // DELETE /api/me checks a password or mails the account's own address, so it is kept low per account.
        RateLimiter::for('me-account-deletion', function (): array {
            $userId = app(CurrentUser::class)->id();

            return [
                Limit::perMinute(5)->by('me-account-deletion:'.$userId),
                Limit::perHour(10)->by('me-account-deletion-hour:'.$userId),
            ];
        });

        // The emailed deletion links are opened without a session, so they count per IP (GET and POST share it).
        RateLimiter::for('me-delete-confirm', fn (Request $request) => Limit::perMinute(20)->by('ip:'.$request->ip()));
    }
}
