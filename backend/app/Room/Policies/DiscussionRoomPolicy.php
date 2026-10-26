<?php

namespace App\Room\Policies;

use App\Auth\CurrentUser\CurrentUser;
use App\Models\DiscussionRoom;

/**
 * Anyone signed in can read a public room and join it. A private room is seen only by its members and the
 * people they invited; an invitation lets you read it and join. Only members post, and a message waits for
 * every member to read it, so the people who are just looking never hold one up.
 */
class DiscussionRoomPolicy
{
    public function viewAny(CurrentUser $current): bool
    {
        return $current->isAuthenticated();
    }

    public function view(CurrentUser $current, DiscussionRoom $room): bool
    {
        return $current->isAuthenticated()
            && (! $room->is_private || $this->isMember($current, $room) || $this->isInvited($current, $room));
    }

    public function create(CurrentUser $current): bool
    {
        return $current->isAuthenticated();
    }

    /** Whoever can see a room can join it: a public room is open, a private one needs an invitation. */
    public function join(CurrentUser $current, DiscussionRoom $room): bool
    {
        return $this->view($current, $room);
    }

    public function post(CurrentUser $current, DiscussionRoom $room): bool
    {
        return $current->isAuthenticated() && $this->isMember($current, $room);
    }

    /** Members of a private room invite people; a public room is joined, not invited to. */
    public function invite(CurrentUser $current, DiscussionRoom $room): bool
    {
        return $room->is_private && $current->isAuthenticated() && $this->isMember($current, $room);
    }

    public function decline(CurrentUser $current, DiscussionRoom $room): bool
    {
        return $current->isAuthenticated() && $this->isInvited($current, $room);
    }

    private function isMember(CurrentUser $current, DiscussionRoom $room): bool
    {
        return $room->members()->whereKey($current->id())->exists();
    }

    private function isInvited(CurrentUser $current, DiscussionRoom $room): bool
    {
        return $room->invitees()->whereKey($current->id())->exists();
    }
}
