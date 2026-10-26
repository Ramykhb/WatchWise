<?php

namespace App\Room\Policies;

use App\Auth\CurrentUser\CurrentUser;
use App\Models\DiscussionRoom;

/**
 * Rooms are open to every signed-in member: anyone can read one, start one or post in one (posting makes
 * them a member, which is who a message waits for before it is deleted).
 */
class DiscussionRoomPolicy
{
    public function viewAny(CurrentUser $current): bool
    {
        return $current->isAuthenticated();
    }

    public function view(CurrentUser $current, DiscussionRoom $room): bool
    {
        return $current->isAuthenticated();
    }

    public function create(CurrentUser $current): bool
    {
        return $current->isAuthenticated();
    }

    public function post(CurrentUser $current, DiscussionRoom $room): bool
    {
        return $current->isAuthenticated();
    }
}
