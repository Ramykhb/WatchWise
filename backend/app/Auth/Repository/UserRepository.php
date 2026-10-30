<?php

namespace App\Auth\Repository;

use App\Auth\InterfaceRepository\UserRepositoryInterface;
use App\Models\User;

class UserRepository implements UserRepositoryInterface
{
    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function create(array $attributes): User
    {
        return User::create($attributes)->refresh();
    }

    public function markEmailVerified(User $user): void
    {
        $user->forceFill(['email_verified_at' => now()])->save();
    }

    public function markActive(User $user): void
    {
        $now = now();
        User::whereKey($user->id)->update(['last_active_at' => $now]);
        $user->setAttribute('last_active_at', $now)->syncOriginalAttribute('last_active_at');
    }

    public function clearAvatar(User $user): void
    {
        $user->forceFill(['avatar' => null])->save();
    }

    public function updatePassword(User $user, string $plainPassword): void
    {
        $user->password = $plainPassword; // 'hashed' cast
        $user->save();
    }
}
