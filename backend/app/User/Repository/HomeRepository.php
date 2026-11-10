<?php

namespace App\User\Repository;

use App\Models\DiscussionRoom;
use App\Models\MovieList;
use App\Models\MovieNight;
use App\Models\Review;
use App\Models\User;
use App\Models\Watchlist;
use App\User\InterfaceRepository\HomeRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class HomeRepository implements HomeRepositoryInterface
{
    public function recentActivity(User $viewer, int $limit): array
    {
        $reviews = Review::with('user.preferences')
            ->where('is_approved', true)->whereNull('removed_at')
            ->where(function (Builder $activity) use ($viewer) {
                $activity->where(fn (Builder $reviews) => $reviews->withText()
                    ->whereHas('user', fn (Builder $authors) => $this->activityAuthors($authors, $viewer, 'reviews')))
                    ->orWhere(fn (Builder $ratings) => $ratings
                        ->where(fn (Builder $text) => $text->whereNull('content')->orWhere('content', ''))
                        ->whereHas('user', fn (Builder $authors) => $this->activityAuthors($authors, $viewer, 'ratings')));
            })
            ->orderByDesc('updated_at')->orderByDesc('id')->limit($limit)->get();

        $lists = MovieList::with(['user', 'items' => fn ($items) => $items->limit(1)])
            ->where('is_public', true)
            ->whereHas('user', fn (Builder $authors) => $this->activityAuthors($authors, $viewer, 'lists'))
            ->orderByDesc('updated_at')->orderByDesc('id')->limit($limit)->get();

        $watchlist = Watchlist::with('user')
            ->where(fn (Builder $activity) => $activity
                ->where(fn (Builder $watched) => $watched->where('status', 'watched')
                    ->whereHas('user', fn (Builder $authors) => $this->activityAuthors($authors, $viewer, 'history')))
                ->orWhere(fn (Builder $queued) => $queued->whereIn('status', ['to_watch', 'watching'])
                    ->whereHas('user', fn (Builder $authors) => $this->activityAuthors($authors, $viewer, 'watchlist', false))))
            ->orderByRaw('CASE WHEN status = ? THEN COALESCE(watched_at, created_at) ELSE created_at END DESC', ['watched'])
            ->orderByDesc('id')->limit($limit)->get();

        return compact('reviews', 'lists', 'watchlist');
    }

    /** @param Builder<User> $authors */
    private function activityAuthors(Builder $authors, User $viewer, string $section, bool $sharedByDefault = true): void
    {
        $authors->where('is_active', true)->where('id', '!=', $viewer->id)
            ->whereIn('profile_visibility', ['public', 'followers'])
            ->whereHas('followers', fn (Builder $followers) => $followers->whereKey($viewer->id));

        if ($sharedByDefault) {
            $authors->whereDoesntHave('preferences', fn (Builder $preferences) => $preferences->where("settings->privacy->{$section}", false));
        } else {
            $authors->whereHas('preferences', fn (Builder $preferences) => $preferences->where("settings->privacy->{$section}", true));
        }
    }

    public function busiestRooms(int $limit, int $userId): Collection
    {
        return DiscussionRoom::withCount(['messages', 'members'])
            ->where(fn ($rooms) => $rooms->where('is_private', false)->orWhereHas('members', fn ($members) => $members->whereKey($userId)))
            ->withMax('messages', 'created_at')
            ->orderByDesc('messages_max_created_at')
            ->limit($limit)
            ->get();
    }

    public function nextNightFor(User $user): ?MovieNight
    {
        return MovieNight::with(['invites.user', 'votes'])
            ->where('scheduled_at', '>=', now())
            ->where('status', '!=', 'cancelled')
            ->where(fn ($query) => $query->where('organizer_id', $user->id)->orWhereHas('invites', fn ($invites) => $invites->where('user_id', $user->id)->where('status', '!=', 'declined')))
            ->orderBy('scheduled_at')
            ->first();
    }
}
