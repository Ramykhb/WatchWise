<?php

namespace App\User\InterfaceRepository;

use App\Models\DiscussionRoom;
use App\Models\MovieNight;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface HomeRepositoryInterface
{
    /**
     * Current, shared activity by followed members, bounded per source.
     *
     * @return array{reviews: Collection, lists: Collection, watchlist: Collection}
     */
    public function recentActivity(User $viewer, int $limit): array;

    /**
     * The rooms with the most recent message, newest first: public rooms and the private ones the user is in, each with its `members_count`.
     *
     * @return Collection<int, DiscussionRoom>
     */
    public function busiestRooms(int $limit, int $userId): Collection;

    /** The soonest upcoming night the user hosts or has not declined, with its guests and votes. */
    public function nextNightFor(User $user): ?MovieNight;
}
