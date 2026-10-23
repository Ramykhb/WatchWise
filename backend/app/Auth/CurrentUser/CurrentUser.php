<?php

namespace App\Auth\CurrentUser;

use App\Models\User;

interface CurrentUser
{
    public function id(): ?int;

    public function user(): ?User;

    public function role(): ?string;

    public function isAdmin(): bool;

    public function isAuthenticated(): bool;

    public function isVerified(): bool;
}
