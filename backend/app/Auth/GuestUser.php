<?php

namespace App\Auth;

use App\Contracts\CurrentUser;
use App\Models\User;

/** Null object used when the request is anonymous. */
class GuestUser implements CurrentUser
{
    public function id(): ?int
    {
        return null;
    }

    public function user(): ?User
    {
        return null;
    }

    public function role(): ?string
    {
        return null;
    }

    public function isAdmin(): bool
    {
        return false;
    }

    public function isAuthenticated(): bool
    {
        return false;
    }

    public function isVerified(): bool
    {
        return false;
    }
}
