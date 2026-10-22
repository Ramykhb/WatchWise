<?php

namespace App\Providers;

use App\Auth\GuestUser;
use App\Contracts\CurrentUser;
use App\Models\Message;
use App\Models\MovieList;
use App\Models\MovieListItem;
use App\Models\MovieNight;
use App\Models\Notification;
use App\Models\Review;
use App\Models\User;
use App\Models\UserPreference;
use App\Models\Watchlist;
use App\Policies\MessagePolicy;
use App\Policies\MovieListItemPolicy;
use App\Policies\MovieListPolicy;
use App\Policies\MovieNightPolicy;
use App\Policies\NotificationPolicy;
use App\Policies\ReviewPolicy;
use App\Policies\UserPolicy;
use App\Policies\UserPreferencePolicy;
use App\Policies\WatchlistPolicy;
use Illuminate\Support\Facades\Auth;
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
    }

    public function boot(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // Gate checks (can(), authorize(), policies) receive the CurrentUser, never Auth::user().
        Auth::resolveUsersUsing(fn () => $this->app->make(CurrentUser::class));
    }
}
