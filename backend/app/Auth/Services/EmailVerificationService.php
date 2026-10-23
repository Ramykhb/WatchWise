<?php

namespace App\Auth\Services;

use App\Auth\DTOs\Requests\ResendVerificationData;
use App\Auth\DTOs\Requests\VerifyEmailData;
use App\Auth\InterfaceRepository\UserRepositoryInterface;
use App\Auth\InterfaceServices\EmailVerificationServiceInterface;
use App\Auth\Mail\VerifyEmailMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class EmailVerificationService implements EmailVerificationServiceInterface
{
    public const EXPIRES_MINUTES = 60;

    public function __construct(private UserRepositoryInterface $users) {}

    public function send(User $user): void
    {
        if ($user->email_verified_at) {
            return;
        }

        // After the response is sent: SMTP latency must not reveal whether the account exists, and
        // a mail transport failure must not break registration.
        defer(fn () => rescue(function () use ($user): void {
            $url = URL::temporarySignedRoute(
                'auth.verify-email',
                now()->addMinutes(self::EXPIRES_MINUTES),
                ['id' => $user->id, 'hash' => sha1($user->email)],
            );

            Mail::to($user->email)->send(new VerifyEmailMail($user, $url, self::EXPIRES_MINUTES));
        }));
    }

    public function resend(ResendVerificationData $data): void
    {
        $user = $this->users->findByEmail($data->email);

        if ($user && ! $user->email_verified_at) {
            $this->send($user);
        }
    }

    public function verify(VerifyEmailData $data): bool
    {
        $user = $this->users->findById($data->id);

        if (! $user || ! hash_equals(sha1($user->email), $data->hash)) {
            return false;
        }

        if (! $user->email_verified_at) {
            $this->users->markEmailVerified($user);
        }

        return true;
    }
}
