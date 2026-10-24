<?php

namespace App\Auth\Repository;

use App\Auth\InterfaceRepository\PasswordResetRepositoryInterface;
use App\Models\PasswordReset;

class PasswordResetRepository implements PasswordResetRepositoryInterface
{
    public function replaceFor(string $email, string $hashedToken): PasswordReset
    {
        PasswordReset::where('email', $email)->delete();

        $record = new PasswordReset(['email' => $email, 'token' => $hashedToken]);
        $record->created_at = now();
        $record->save();

        return $record;
    }

    public function latestFor(string $email): ?PasswordReset
    {
        return PasswordReset::where('email', $email)->latest('id')->first();
    }

    public function deleteFor(string $email): void
    {
        PasswordReset::where('email', $email)->delete();
    }
}
