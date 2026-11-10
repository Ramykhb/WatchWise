<?php

namespace App\User\Repository;

use App\Models\User;
use App\User\InterfaceRepository\AccountDeletionRepositoryInterface;

class AccountDeletionRepository implements AccountDeletionRepositoryInterface
{
    public function storeDeletionRequest(User $user, string $nonce): void
    {
        $user->forceFill(['deletion_requested_at' => now(), 'deletion_nonce' => $nonce])->save();
    }

    public function clearDeletionRequest(User $user, ?string $nonce = null): bool
    {
        $cleared = ['deletion_requested_at' => null, 'deletion_nonce' => null];
        $query = User::query()->whereKey($user->id);

        if ($nonce !== null) {
            $query->where('deletion_nonce', $nonce)
                ->where('email', $user->email)
                ->where('password', $user->getAttributes()['password']);
        }

        $updated = $query->update($cleared);

        if ($updated === 1) {
            $user->forceFill($cleared)->syncOriginalAttributes(array_keys($cleared));
        }

        return $updated === 1;
    }
}
