<?php

namespace App\Auth\Services;

use App\Auth\Exceptions\InvalidResetToken;
use App\Auth\InterfaceRepository\PasswordResetRepositoryInterface;
use App\Auth\InterfaceRepository\RefreshTokenRepositoryInterface;
use App\Auth\InterfaceRepository\UserRepositoryInterface;
use App\Auth\InterfaceServices\PasswordResetServiceInterface;
use App\Auth\Mail\ResetPasswordMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PasswordResetService implements PasswordResetServiceInterface
{
    private const EXPIRES_MINUTES = 60;

    public function __construct(
        private UserRepositoryInterface $users,
        private PasswordResetRepositoryInterface $resets,
        private RefreshTokenRepositoryInterface $refreshTokens,
    ) {}

    public function sendResetLink(string $email): void
    {
        $user = $this->users->findByEmail($email);

        if (! $user) {
            return;
        }

        // After the response is sent, so the cost of hashing and mailing never leaks whether the account exists.
        defer(fn () => rescue(function () use ($user, $email): void {
            $plain = Str::random(64);
            $this->resets->replaceFor($email, Hash::make($plain));

            $url = rtrim(config('services.frontend_url'), '/').'/reset-password?'
                .http_build_query(['token' => $plain, 'email' => $email]);

            Mail::to($email)->send(new ResetPasswordMail($user->name, $url, self::EXPIRES_MINUTES));
        }));
    }

    public function reset(string $email, string $token, string $password): void
    {
        $record = $this->resets->latestFor($email);

        if (! $record
            || ! $record->created_at
            || $record->created_at->lt(now()->subMinutes(self::EXPIRES_MINUTES))
            || ! Hash::check($token, $record->token)) {
            throw new InvalidResetToken;
        }

        $user = $this->users->findByEmail($email);
        if (! $user) {
            throw new InvalidResetToken;
        }

        DB::transaction(function () use ($user, $email, $password): void {
            $this->users->updatePassword($user, $password);
            $this->resets->deleteFor($email);
            $this->refreshTokens->revokeAllForUser($user->id);
        });
    }
}
