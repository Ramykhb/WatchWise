<?php

namespace App\User\Services;

use App\Auth\InterfaceServices\AuthServiceInterface;
use App\Models\User;
use App\Support\SocialPlaceholderPassword;
use App\User\DTOs\Requests\ConfirmEmailChangeData;
use App\User\DTOs\Requests\RequestEmailChangeData;
use App\User\DTOs\Responses\AccountResponse;
use App\User\DTOs\Responses\EmailChangeOutcome;
use App\User\Exceptions\IncorrectPassword;
use App\User\Exceptions\NoPendingEmailChange;
use App\User\InterfaceRepository\AccountRepositoryInterface;
use App\User\InterfaceServices\EmailChangeServiceInterface;
use App\User\Mail\ConfirmEmailChangeMail;
use App\User\Mail\EmailChangedMail;
use App\User\Mail\EmailChangeRequestedMail;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class EmailChangeService implements EmailChangeServiceInterface
{
    public const EXPIRES_MINUTES = 60;

    public function __construct(
        private readonly AccountRepositoryInterface $accounts,
        private readonly AuthServiceInterface $auth,
    ) {}

    public function request(User $user, RequestEmailChangeData $data): AccountResponse
    {
        // Accounts made by social sign-in hold the placeholder, so they have no password to type; the link still proves the address.
        if (! SocialPlaceholderPassword::isStoredFor($user)) {
            if ($data->currentPassword === null) {
                throw IncorrectPassword::forMissingCurrentPassword();
            }
            if (! Hash::check($data->currentPassword, $user->password)) {
                throw IncorrectPassword::forCurrentPassword();
            }
        }

        $this->accounts->updatePendingEmail($user, $data->email);
        $this->sendConfirmationLink($user);
        $this->mailLater($user->email, new EmailChangeRequestedMail($user->name, self::mask($data->email)));

        return AccountResponse::fromModel($user);
    }

    public function resend(User $user): AccountResponse
    {
        if ($user->pending_email === null) {
            throw new NoPendingEmailChange;
        }

        $this->sendConfirmationLink($user);

        return AccountResponse::fromModel($user);
    }

    public function cancel(User $user): AccountResponse
    {
        if ($user->pending_email !== null) {
            $this->accounts->updatePendingEmail($user, null);
        }

        return AccountResponse::fromModel($user);
    }

    public function confirm(ConfirmEmailChangeData $data): EmailChangeOutcome
    {
        $user = $this->accounts->findById($data->userId);

        // The hash binds the link to one pending address: replacing or cancelling it voids the link.
        if (! $user || $user->pending_email === null || ! hash_equals(sha1($user->pending_email), $data->hash)) {
            return EmailChangeOutcome::Invalid;
        }

        // Someone may have signed up with the address since the request.
        if ($this->accounts->emailBelongsToAnotherUser($user->pending_email, $user->id)) {
            $this->accounts->updatePendingEmail($user, null);

            return EmailChangeOutcome::Taken;
        }

        $previousEmail = $user->email;

        try {
            $this->accounts->applyPendingEmail($user);
        } catch (UniqueConstraintViolationException) {
            $user->refresh();
            $this->accounts->updatePendingEmail($user, null);

            return EmailChangeOutcome::Taken;
        }

        // Whoever held a session under the old address has to sign in again.
        $this->auth->revokeSessions($user);
        $this->mailLater($previousEmail, new EmailChangedMail($user->name, self::mask($user->email)));

        return EmailChangeOutcome::Success;
    }

    private function sendConfirmationLink(User $user): void
    {
        $url = URL::temporarySignedRoute(
            'me.email.confirm',
            now()->addMinutes(self::EXPIRES_MINUTES),
            ['id' => $user->id, 'hash' => sha1($user->pending_email)],
        );

        $this->mailLater($user->pending_email, new ConfirmEmailChangeMail($user->name, $url, self::EXPIRES_MINUTES));
    }

    /** After the response is sent, so a slow or broken mail server is logged without failing the request. */
    private function mailLater(string $to, Mailable $mail): void
    {
        defer(fn () => rescue(fn () => Mail::to($to)->send($mail)));
    }

    /** "jane.doe@example.com" becomes "j***@example.com", enough to recognise without revealing it. */
    private static function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 1).'***@'.$domain;
    }
}
