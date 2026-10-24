<?php

namespace App\Auth\Providers;

use App\Auth\InterfaceRepository\PasswordResetRepositoryInterface;
use App\Auth\InterfaceRepository\RefreshTokenRepositoryInterface;
use App\Auth\InterfaceRepository\SocialAccountRepositoryInterface;
use App\Auth\InterfaceRepository\UserRepositoryInterface;
use App\Auth\InterfaceServices\AuthServiceInterface;
use App\Auth\InterfaceServices\EmailVerificationServiceInterface;
use App\Auth\InterfaceServices\PasswordResetServiceInterface;
use App\Auth\InterfaceServices\SocialAuthServiceInterface;
use App\Auth\Listeners\SendEmailVerification;
use App\Auth\Repository\PasswordResetRepository;
use App\Auth\Repository\RefreshTokenRepository;
use App\Auth\Repository\SocialAccountRepository;
use App\Auth\Repository\UserRepository;
use App\Auth\Services\AuthService;
use App\Auth\Services\EmailVerificationService;
use App\Auth\Services\PasswordResetService;
use App\Auth\Services\SocialAuthService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Binds the Auth module's interfaces to their implementations, registers its event listener
 * and defines its rate limiters.
 */
class AuthModuleServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        UserRepositoryInterface::class => UserRepository::class,
        RefreshTokenRepositoryInterface::class => RefreshTokenRepository::class,
        PasswordResetRepositoryInterface::class => PasswordResetRepository::class,
        SocialAccountRepositoryInterface::class => SocialAccountRepository::class,

        AuthServiceInterface::class => AuthService::class,
        EmailVerificationServiceInterface::class => EmailVerificationService::class,
        PasswordResetServiceInterface::class => PasswordResetService::class,
        SocialAuthServiceInterface::class => SocialAuthService::class,
    ];

    public function boot(): void
    {
        Event::listen(Registered::class, SendEmailVerification::class);

        $this->configureRateLimiting();
    }

    /**
     * Each limiter has its own bucket; the plain throttle:N,M middleware shares one per IP across routes.
     */
    private function configureRateLimiting(): void
    {
        $limits = [
            'auth-register' => 5,
            'auth-login' => 5,
            'auth-refresh' => 30,
            'auth-verify-email' => 20,
            'auth-resend-verification' => 3,
            'auth-forgot-password' => 3,
            'auth-reset-password' => 5,
        ];

        foreach ($limits as $name => $perMinute) {
            RateLimiter::for($name, function (Request $request) use ($perMinute): array {
                $limits = [Limit::perMinute($perMinute)->by('ip:'.$request->ip())];

                // Separate per-account bucket: a rotating email value can only tighten limiting, never reset it.
                $email = Str::lower(trim((string) $request->input('email')));

                if ($email !== '') {
                    $limits[] = Limit::perMinute($perMinute)->by('email:'.$email);
                }

                return $limits;
            });
        }
    }
}
