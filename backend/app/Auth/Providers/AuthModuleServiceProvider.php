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
use App\Auth\Services\SocialAuthService;
use App\Auth\Support\DiscordOAuthClient;
use App\Auth\Support\GooglePhoto;
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
                $value = $request->input('email');
                $email = EmailAddress::normalize(is_string($value) ? $value : '');

                if ($email !== '') {
                    $limits[] = Limit::perMinute($perMinute)->by('email:'.$email);
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
                ->by('ip:'.$request->ip())
                ->response(fn (Request $request, array $headers): RedirectResponse => SocialLoginRedirect::failure('too_many_attempts')->withHeaders($headers)));
        }
    }
}
