<?php

namespace App\Policies;

use App\Contracts\CurrentUser;
use App\Models\Message;

class MessagePolicy
{
    public function viewAny(CurrentUser $current): bool
    {
        return $current->isAuthenticated();
    }

    public function view(CurrentUser $current, Message $message): bool
    {
        return $current->isAuthenticated();
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
