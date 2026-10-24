<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * App-wide setup only. Each module (Auth, Tmdb, Gemini, Weather, MovieRecommendation)
 * registers its own bindings, listeners and rate limiters in its own provider.
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
<<<<<<< HEAD
        Event::listen(Registered::class, SendEmailVerification::class);

        $this->configureAuthRateLimiting();
        $this->configureTmdbRateLimiting();
    }

    /**
     * The movie and genre routes are public and each request can reach TMDB, so one per-IP
     * bucket keeps a single client from using up the shared TMDB quota.
     */
    private function configureTmdbRateLimiting(): void
    {
        RateLimiter::for('tmdb', fn (Request $request): Limit => Limit::perMinute(60)->by('ip:'.$request->ip()));
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
=======
        //
>>>>>>> origin/dev
    }
}
