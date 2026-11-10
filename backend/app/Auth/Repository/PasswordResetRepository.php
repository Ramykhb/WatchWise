<?php

namespace App\Auth\Repository;

use App\Auth\InterfaceRepository\PasswordResetRepositoryInterface;
use App\Models\PasswordReset;
use App\Support\EmailAddress;

class PasswordResetRepository implements PasswordResetRepositoryInterface
{
    public function replaceFor(string $email, string $hashedToken): PasswordReset
    {
        $this->deleteFor($email);

        $record = new PasswordReset(['email' => $email, 'token' => $hashedToken]);
        $record->created_at = now();
        $record->save();

        return $record;
    }

    public function latestFor(string $email): ?PasswordReset
    {
        return PasswordReset::where('email', EmailAddress::normalize($email))->latest('id')->first();
    }

    public function deleteFor(string $email): void
    {
        PasswordReset::where('email', EmailAddress::normalize($email))->delete();
    }
}
