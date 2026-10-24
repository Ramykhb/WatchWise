<?php

namespace App\Providers;

use App\Auth\CurrentUser\CurrentUser;
use App\Auth\CurrentUser\GuestUser;
use App\Models\Message;
use App\Models\MovieList;
use App\Models\MovieListItem;
use App\Models\MovieNight;
use App\Models\Notification;
use App\Models\Review;
use App\Models\User;
use App\Models\UserPreference;
use App\Models\Watchlist;
use App\MovieList\Policies\MovieListItemPolicy;
use App\MovieList\Policies\MovieListPolicy;
use App\MovieNight\Policies\MovieNightPolicy;
use App\Policies\MessagePolicy;
use App\Policies\NotificationPolicy;
use App\Policies\ReviewPolicy;
use App\Policies\UserPolicy;
use App\Policies\UserPreferencePolicy;
use App\Policies\WatchlistPolicy;
use Illuminate\Auth\Access\Gate as AccessGate;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthorizationServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected array $policies = [
        Review::class => ReviewPolicy::class,
        Watchlist::class => WatchlistPolicy::class,
        MovieList::class => MovieListPolicy::class,
        MovieListItem::class => MovieListItemPolicy::class,
        MovieNight::class => MovieNightPolicy::class,
        Message::class => MessagePolicy::class,
        Notification::class => NotificationPolicy::class,
        UserPreference::class => UserPreferencePolicy::class,
        User::class => UserPolicy::class,
    ];

    public function register(): void
    {
        // Anonymous by default; the auth middleware replaces this per request.
        $this->app->bind(CurrentUser::class, fn () => new GuestUser);

        // Gate checks (can(), authorize(), policies) receive the CurrentUser. Only the Gate is
        // rebound, so $request->user() and Auth::user() keep their framework meaning (rate limiting).
        $this->app->singleton(GateContract::class, fn ($app) => new AccessGate(
            $app,
            fn () => $app->make(CurrentUser::class),
        ));
    }

    public function boot(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }
}
