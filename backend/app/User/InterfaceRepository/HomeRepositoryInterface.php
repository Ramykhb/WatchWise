<?php

namespace App\User\InterfaceRepository;

use App\Models\DiscussionRoom;
use App\Models\MovieNight;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface HomeRepositoryInterface
{
    /**
     * The rooms with the most recent message, newest first.
     *
     * @return Collection<int, DiscussionRoom>
     */
    public function busiestRooms(int $limit): Collection;

    /** How many members the room has (its creator and everyone who has posted in it). */
    public function roomMemberCount(DiscussionRoom $room): int;

    /** The soonest upcoming night the user hosts or has not declined, with its guests and votes. */
    public function nextNightFor(User $user): ?MovieNight;
}
