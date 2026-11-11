<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Process\Process;

/**
 * App-wide setup only. Each module (Auth, Genre, Movie, Gemini, Weather, MovieRecommendation)
 * registers its own bindings, listeners and rate limiters in its own provider. The `tmdb`
 * limiter lives here because the Genre and Movie routes share it.
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureUrlGeneration();

        RateLimiter::for('tmdb', fn (Request $request) => Limit::perMinute(60)->by('ip:'.$request->ip()));

        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            if ($event->command !== 'serve'
                || $event->input->hasParameterOption(['--help', '-h', '--version', '-V'])
                || app()->runningUnitTests()
                || config('app.env') !== 'local') {
                return;
            }

            $reverb = new Process([PHP_BINARY, 'artisan', 'reverb:start'], base_path());
            $reverb->setTimeout(null);

            try {
                $reverb->start(fn (string $type, string $output) => $event->output->write($output));
            } catch (\Throwable $error) {
                report($error);
                $event->output->writeln('<comment>Reverb could not start automatically. Run php artisan reverb:start in another terminal.</comment>');

                return;
            }

            $event->output->writeln('<info>Starting Reverb for the chat socket...</info>');
            register_shutdown_function(static function () use ($reverb): void {
                if ($reverb->isRunning()) {
                    $reverb->stop(3);
                }
            });
        });
    }

    /**
     * In production every generated URL, the signed email links included, is built on APP_URL over https instead
     * of the request's Host header, so no request can choose the host of a link mailed to a member. Incoming
     * links are still checked against the request URL, so APP_URL must be the public backend address the links
     * are opened on (the proxy in front forwards that Host and `X-Forwarded-Proto: https`).
     */
    public function configureUrlGeneration(): void
    {
        if (! $this->app->environment('production')) {
            return;
        }

        URL::forceRootUrl(config('app.url'));
        URL::forceScheme('https');
    }
}
