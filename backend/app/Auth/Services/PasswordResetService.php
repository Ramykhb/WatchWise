<?php

namespace App\Auth\Services;

use App\Auth\DTOs\Requests\ForgotPasswordData;
use App\Auth\DTOs\Requests\ResetPasswordData;
use App\Auth\Exceptions\InvalidResetToken;
use App\Auth\InterfaceRepository\PasswordResetRepositoryInterface;
use App\Auth\InterfaceRepository\RefreshTokenRepositoryInterface;
use App\Auth\InterfaceRepository\UserRepositoryInterface;
use App\Auth\InterfaceServices\PasswordResetServiceInterface;
use App\Auth\Mail\PasswordChangedMail;
use App\Auth\Mail\ResetPasswordMail;
use App\Models\PasswordReset;
use App\Models\User;
use App\Support\SendsMailAfterResponse;
use App\User\InterfaceServices\AccountServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PasswordResetService implements PasswordResetServiceInterface
{
    use SendsMailAfterResponse;

    private const EXPIRES_MINUTES = 60;

    public function __construct(
        private UserRepositoryInterface $users,
        private PasswordResetRepositoryInterface $resets,
        private RefreshTokenRepositoryInterface $refreshTokens,
        private AccountServiceInterface $accounts,
    ) {}

    public function sendResetLink(ForgotPasswordData $data): void
    {
        $email = $data->email;
        $user = $this->users->findByEmail($email);

        if (! $user) {
            return;
        }

        // After the response is sent, so the cost of storing the token and mailing never leaks whether the account
        // exists. The token is stored once; only the sending is retried.
        $this->mailLater($email, function () use ($user, $email): ResetPasswordMail {
            $plain = Str::random(64);
            $this->resets->replaceFor($email, self::hashToken($plain));

            $url = rtrim(config('services.frontend_url'), '/').'/reset-password?'
                .http_build_query(['token' => $plain, 'email' => $email]);

            return new ResetPasswordMail($user->name, $url, self::EXPIRES_MINUTES);
        });
    }

    public function reset(ResetPasswordData $data): void
    {
        $email = $data->email;
        $presented = self::hashToken($data->token);

        $user = DB::transaction(function () use ($data, $email, $presented): User {
            // Locked until commit: a second request with the same token waits here, then finds no row.
            $record = $this->resets->latestForUpdate($email);

            if (! $record || ! $this->isUsable($record, $presented)) {
                throw new InvalidResetToken;
            }

            // Consumed before anything else changes: no deleted row means another request used it meanwhile.
            if ($this->resets->deleteFor($email) === 0) {
                throw new InvalidResetToken;
            }

            $user = $this->users->findByEmail($email) ?? throw new InvalidResetToken;

            $this->users->updatePassword($user, $data->password);
            // The emailed token proves the inbox: the address counts as verified, a pending email change goes, and
            // a first password set without proof now counts.
            $this->accounts->passwordResetThroughEmail($user);
            $this->refreshTokens->revokeAllForUser($user);

            return $user;
        });

        $this->mailLater($user->email, new PasswordChangedMail($user->name));
    }

    /**
     * New tokens are 64 random characters, so a fast SHA-256 is enough to store them (bcrypt would only cost CPU on
     * every attempt). Every reset attempt hashes the presented token once, whether a row exists or not, so the
     * response time does not tell whether a reset was requested.
     */
    private static function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /** @param  string  $presented  the SHA-256 of the token from the link */
    private function isUsable(PasswordReset $record, string $presented): bool
    {
        if (! $record->created_at || $record->created_at->lt(now()->subMinutes(self::EXPIRES_MINUTES))) {
            return false;
        }

        return hash_equals($record->token, $presented);
    }
}
