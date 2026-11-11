<?php

namespace App\Auth\Providers;

use App\Auth\InterfaceRepository\PasswordResetRepositoryInterface;
use App\Auth\InterfaceRepository\RefreshTokenRepositoryInterface;
use App\Auth\InterfaceRepository\SocialAccountRepositoryInterface;
use App\Auth\InterfaceRepository\UserRepositoryInterface;
use App\Auth\InterfaceServices\AuthServiceInterface;
use App\Auth\InterfaceServices\DiscordOAuthClientInterface;
use App\Auth\InterfaceServices\EmailVerificationServiceInterface;
use App\Auth\InterfaceServices\GooglePhotoInterface;
use App\Auth\InterfaceServices\PasswordResetServiceInterface;
use App\Auth\InterfaceServices\RefreshTokenCleanupServiceInterface;
use App\Auth\InterfaceServices\SocialAuthServiceInterface;
use App\Auth\Listeners\SendEmailVerification;
use App\Auth\Mail\BrevoTransport;
use App\Auth\Repository\PasswordResetRepository;
use App\Auth\Repository\RefreshTokenRepository;
use App\Auth\Repository\SocialAccountRepository;
use App\Auth\Repository\UserRepository;
use App\Auth\Services\AuthService;
use App\Auth\Services\EmailVerificationService;
use App\Auth\Services\PasswordResetService;
use App\Auth\Services\RefreshTokenCleanupService;
use App\Auth\Services\SocialAuthService;
use App\Auth\Support\DiscordOAuthClient;
use App\Auth\Support\GooglePhoto;
use App\Auth\Support\RateLimitAddress;
use App\Auth\Support\SocialLoginRedirect;
use App\Support\EmailAddress;
use Illuminate\Auth\Events\Registered;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        RefreshTokenCleanupServiceInterface::class => RefreshTokenCleanupService::class,
        SocialAuthServiceInterface::class => SocialAuthService::class,
        DiscordOAuthClientInterface::class => DiscordOAuthClient::class,
        GooglePhotoInterface::class => GooglePhoto::class,
    ];

    public function boot(): void
    {
        Mail::extend('brevo', fn (array $config): BrevoTransport => new BrevoTransport($config['key'] ?? ''));

        Event::listen(Registered::class, SendEmailVerification::class);

        $this->configureRateLimiting();
    }

    /**
     * Each limiter has its own bucket; the plain throttle:N,M middleware shares one per IP across routes.
     *
     * No bucket is keyed by the email alone: anyone could fill it from many addresses and lock the owner out of
     * signing in or recovering the account. Every request counts in a per-IP bucket (`$request->ip()` is the client
     * behind a trusted proxy; an IPv6 address counts as its /64, see `RateLimitAddress`), and the routes that take an email also count in a per-(email + IP) bucket, so one
     * address can be hit fewer times than the IP may make requests in all. Login's per-(email + IP) bucket counts
     * failed attempts only and lives in `AuthService::login()` (`LOGIN_FAILURES_PER_MINUTE`).
     */
    private function configureRateLimiting(): void
    {
        // name => [per IP, per email + IP] (per minute); null: the route takes no email.
        $limits = [
            'auth-register' => [10, 3],
            'auth-login' => [20, null],
            'auth-refresh' => [30, null],
            'auth-verify-email' => [20, null],
            'auth-resend-verification' => [10, 3],
            'auth-forgot-password' => [10, 3],
            'auth-reset-password' => [10, 5],
        ];

        foreach ($limits as $name => [$perIp, $perEmailAndIp]) {
            RateLimiter::for($name, function (Request $request) use ($perIp, $perEmailAndIp): array {
                $ip = RateLimitAddress::of($request->ip());
                $limits = [Limit::perMinute($perIp)->by('ip:'.$ip)];

                $value = $request->input('email');
                $email = EmailAddress::normalize(is_string($value) ? $value : '');

                if ($perEmailAndIp !== null && $email !== '') {
                    $limits[] = Limit::perMinute($perEmailAndIp)->by('email-ip:'.$email.'|'.$ip);
                }

                return $limits;
            });
        }

        // The social routes take no email, so they are limited per IP only. They are browser navigations in the
        // middle of an OAuth round trip, so a refusal is a redirect to the frontend failure page, not a JSON 429.
        $socialLimits = [
            'auth-social-redirect' => 30,
            'auth-social-callback' => 30,
        ];

        foreach ($socialLimits as $name => $perMinute) {
            RateLimiter::for($name, fn (Request $request): Limit => Limit::perMinute($perMinute)
                ->by('ip:'.RateLimitAddress::of($request->ip()))
                ->response(fn (Request $request, array $headers): RedirectResponse => SocialLoginRedirect::failure('too_many_attempts')->withHeaders($headers)));
        }
    }
}
