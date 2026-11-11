<?php

namespace App\Auth\Services;

use App\Auth\DTOs\Requests\ResendVerificationData;
use App\Auth\DTOs\Requests\VerifyEmailData;
use App\Auth\DTOs\Responses\VerifyEmailOutcome;
use App\Auth\InterfaceRepository\UserRepositoryInterface;
use App\Auth\InterfaceServices\EmailVerificationServiceInterface;
use App\Auth\Mail\VerifyEmailMail;
use App\Models\User;
use App\Support\SendsMailAfterResponse;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\URL;

class EmailVerificationService implements EmailVerificationServiceInterface
{
    use SendsMailAfterResponse;

    public const EXPIRES_MINUTES = 60;

    public function __construct(private UserRepositoryInterface $users) {}

    public function send(User $user): void
    {
        if ($user->email_verified_at) {
            return;
        }

        // After the response is sent: Mail delivery latency must not reveal whether the account exists, and
        // a mail transport failure must not break registration.
        $this->mailLater($user->email, fn (): VerifyEmailMail => new VerifyEmailMail(
            $user,
            URL::temporarySignedRoute(
                'auth.verify-email',
                now()->addMinutes(self::EXPIRES_MINUTES),
                ['id' => $user->id, 'hash' => sha1($user->email)],
            ),
            self::EXPIRES_MINUTES,
        ));
    }

    public function resend(ResendVerificationData $data): void
    {
        $user = $this->users->findByEmail($data->email);

        if ($user && ! $user->email_verified_at) {
            $this->send($user);
        }
    }

    public function verify(VerifyEmailData $data): VerifyEmailOutcome
    {
        $user = $this->users->findById($data->id);

        if (! $user || ! hash_equals(sha1($user->email), $data->hash)) {
            return VerifyEmailOutcome::Invalid;
        }

        if (! $this->users->markEmailVerifiedIfUnverified($user)) {
            return VerifyEmailOutcome::AlreadyVerified;
        }

        event(new Verified($user));

        return VerifyEmailOutcome::Success;
    }
}
