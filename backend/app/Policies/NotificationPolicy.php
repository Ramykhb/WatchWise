<?php

namespace App\Policies;

use App\Auth\CurrentUser\CurrentUser;
use App\Models\Notification;

class NotificationPolicy
{
    public function viewAny(CurrentUser $current): bool
    {
        return $current->isAuthenticated();
    }

    public function view(CurrentUser $current, Notification $notification): bool
    {
        return $this->owns($current, $notification);
    }

    public function update(CurrentUser $current, Notification $notification): bool
    {
        return $this->owns($current, $notification);
    }

    public function delete(CurrentUser $current, Notification $notification): bool
    {
        return $this->owns($current, $notification);
    }

    private function owns(CurrentUser $current, Notification $notification): bool
    {
        return $current->isAuthenticated() && $current->id() === $notification->user_id;
    }
}
