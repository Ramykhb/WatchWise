<?php

namespace App\Providers;

use App\Auth\Listeners\SendEmailVerification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(Registered::class, SendEmailVerification::class);

        $this->configureAuthRateLimiting();
    }

    /**
     * Each limiter has its own bucket; the plain throttle:N,M middleware shares one per IP across routes.
     */
    private function configureAuthRateLimiting(): void
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
