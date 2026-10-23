<?php

namespace App\Services;

use App\Mail\VerifyEmailMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class EmailVerificationService
{
    public const EXPIRES_MINUTES = 60;

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
}
