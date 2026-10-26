<?php

namespace App\User\InterfaceRepository;

use App\Models\DiscussionRoom;
use App\Models\MovieNight;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface HomeRepositoryInterface
{
    /**
     * The rooms with the most recent message, newest first: public rooms and the private ones the user is in.
     *
     * @return Collection<int, DiscussionRoom>
     */
    public function busiestRooms(int $limit, int $userId): Collection;

    /** How many members the room has (its creator and everyone who has joined). */
    public function roomMemberCount(DiscussionRoom $room): int;

    /** The soonest upcoming night the user hosts or has not declined, with its guests and votes. */
    public function nextNightFor(User $user): ?MovieNight;
}
