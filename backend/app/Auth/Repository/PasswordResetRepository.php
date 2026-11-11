<?php

namespace App\Auth\Repository;

use App\Auth\InterfaceRepository\PasswordResetRepositoryInterface;
use App\Models\PasswordReset;
use App\Support\EmailAddress;
use Illuminate\Support\Facades\DB;

class PasswordResetRepository implements PasswordResetRepositoryInterface
{
    public function replaceFor(string $email, string $hashedToken): PasswordReset
    {
        return DB::transaction(function () use ($email, $hashedToken): PasswordReset {
            $this->deleteFor($email);

            $record = new PasswordReset(['email' => $email, 'token' => $hashedToken]);
            $record->created_at = now();
            $record->save();

            return $record;
        });
    }

    public function latestForUpdate(string $email): ?PasswordReset
    {
        return PasswordReset::where('email', EmailAddress::normalize($email))->latest('id')->lockForUpdate()->first();
    }

    public function deleteFor(string $email): int
    {
        return PasswordReset::where('email', EmailAddress::normalize($email))->delete();
    }
}
