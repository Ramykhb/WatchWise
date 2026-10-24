<?php

namespace App\Auth\CurrentUser;

use App\Models\User;

class JwtCurrentUser implements CurrentUser
{
    public function __construct(private readonly User $user, private readonly object $claims) {}

    public function id(): ?int
    {
        return $this->user->id;
    }

    public function user(): ?User
    {
        return $this->user;
    }

    /** Role comes from the database so demotions apply before the token expires. */
    public function role(): ?string
    {
        return $this->user->role;
    }

    public function isAdmin(): bool
    {
        return $this->role() === 'admin';
    }

    public function isAuthenticated(): bool
    {
        return true;
    }

    public function isVerified(): bool
    {
        return $this->user->email_verified_at !== null;
    }

    public function claims(): object
    {
        return $this->claims;
    }
}
