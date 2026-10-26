<?php

namespace App\Policies;

use App\Auth\CurrentUser\CurrentUser;
use App\Models\Message;
use Illuminate\Support\Facades\Gate;

/**
 * Messages live in discussion rooms: whoever can see the room can read them (a public room is open to every
 * authenticated user, a private one to its members and invitees). Room membership (room_members) decides
 * who has to read a message before it is deleted.
 */
class MessagePolicy
{
    public function viewAny(CurrentUser $current): bool
    {
        return $current->isAuthenticated();
    }

    public function view(CurrentUser $current, Message $message): bool
    {
        return Gate::forUser($current)->allows('view', $message->room);
    }

    public function create(CurrentUser $current): bool
    {
        return $current->isAuthenticated();
    }

    public function update(CurrentUser $current, Message $message): bool
    {
        return $this->owns($current, $message);
    }

    public function delete(CurrentUser $current, Message $message): bool
    {
        return $this->owns($current, $message) || $current->isAdmin();
    }

    private function owns(CurrentUser $current, Message $message): bool
    {
        return $current->isAuthenticated() && $current->id() === $message->user_id;
    }
}
