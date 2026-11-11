<?php

namespace App\Auth\Repository;

use App\Auth\InterfaceRepository\UserRepositoryInterface;
use App\Models\User;
use App\Support\EmailAddress;
use Illuminate\Support\Facades\Hash;

class UserRepository implements UserRepositoryInterface
{
    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    public function findByEmail(string $email): ?User
    {
        // Emails are stored lowercase (the users_email_unique index serves this lookup).
        return User::where('email', EmailAddress::normalize($email))->first();
    }

    public function create(array $attributes): User
    {
        return User::create($attributes)->refresh();
    }

    public function markEmailVerified(User $user): void
    {
        $user->forceFill(['email_verified_at' => now()])->save();
    }

    public function markEmailVerifiedIfUnverified(User $user): bool
    {
        $now = now();
        $updated = User::query()->whereKey($user->id)->whereNull('email_verified_at')->update(['email_verified_at' => $now]);

        if ($updated === 1) {
            $user->setAttribute('email_verified_at', $now)->syncOriginalAttribute('email_verified_at');
        }

        return $updated === 1;
    }

    public function rehashPassword(User $user, string $verifiedHash, string $plainPassword): bool
    {
        $hash = Hash::make($plainPassword);
        $updated = User::query()->whereKey($user->id)->where('password', $verifiedHash)->toBase()->update(['password' => $hash]);

        if ($updated === 1) {
            $user->setAttribute('password', $hash)->syncOriginalAttribute('password');
        }

        return $updated === 1;
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

    public function clearPassword(User $user): void
    {
        $user->forceFill(['password' => null, 'password_set_without_proof_at' => null])->save();
    }
}
