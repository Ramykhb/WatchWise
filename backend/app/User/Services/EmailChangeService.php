<?php

namespace App\User\Services;

use App\Auth\InterfaceServices\AuthServiceInterface;
use App\Models\User;
use App\Support\SocialPlaceholderPassword;
use App\User\DTOs\Requests\EmailChangeLinkData;
use App\User\DTOs\Requests\RequestEmailChangeData;
use App\User\DTOs\Responses\AccountResponse;
use App\User\DTOs\Responses\EmailChangeOutcome;
use App\User\Exceptions\IncorrectPassword;
use App\User\Exceptions\NoPendingEmailChange;
use App\User\InterfaceRepository\AccountRepositoryInterface;
use App\User\InterfaceServices\EmailChangeServiceInterface;
use App\User\Mail\AuthorizeEmailChangeMail;
use App\User\Mail\ConfirmEmailChangeMail;
use App\User\Mail\EmailChangedMail;
use App\User\Mail\EmailChangeRequestedMail;
use App\User\Mail\SendsMailAfterResponse;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;

class EmailChangeService implements EmailChangeServiceInterface
{
    use SendsMailAfterResponse;

    /** The "this wasn't me" link stays usable for as long as a pending change can realistically wait. */
    public const CANCEL_EXPIRES_MINUTES = 7 * 24 * 60;

    private const LINK_AUTHORIZE = 'authorize';

    private const LINK_CONFIRM = 'confirm';

    private const LINK_CANCEL = 'cancel';

    public function __construct(
        private readonly AccountRepositoryInterface $accounts,
        private readonly AuthServiceInterface $auth,
    ) {}

    public function request(User $user, RequestEmailChangeData $data): AccountResponse
    {
        // Accounts made by social sign-in hold the placeholder, so they have no password to type.
        $hasPassword = ! SocialPlaceholderPassword::isStoredFor($user);

        if ($hasPassword) {
            if ($data->currentPassword === null) {
                throw IncorrectPassword::forMissingCurrentPassword();
            }
            if (! Hash::check($data->currentPassword, $user->password)) {
                throw IncorrectPassword::forCurrentPassword();
            }
        }

        // Without a password, or with one chosen in Settings without knowing any earlier one (which a
        // stolen session could do), the password proves nothing: the current address approves instead.
        $passwordIsProof = $hasPassword && $user->password_set_without_proof_at === null;

        $this->accounts->storePendingEmail($user, $data->email, bin2hex(random_bytes(32)), authorized: $passwordIsProof);

        if ($passwordIsProof) {
            $this->sendConfirmationLink($user);
            $this->mailLater($user->email, new EmailChangeRequestedMail($user->name, self::mask($data->email), $this->cancelUrl($user)));
        } else {
            $this->sendAuthorizationLink($user);
        }

        return AccountResponse::fromModel($user);
    }

    public function resend(User $user): AccountResponse
    {
        if ($user->pending_email === null) {
            throw new NoPendingEmailChange;
        }

        // Refreshes the expiry window; fails when the change was cleared (or replaced) since it was loaded.
        if (! $this->accounts->markPendingEmailLinkSent($user)) {
            throw new NoPendingEmailChange;
        }

        if ($user->pending_email_authorized_at === null) {
            $this->sendAuthorizationLink($user);
        } else {
            $this->sendConfirmationLink($user);
        }

        return AccountResponse::fromModel($user);
    }

    public function cancel(User $user): AccountResponse
    {
        $this->discardPendingChange($user);

        return AccountResponse::fromModel($user);
    }

    public function discardPendingChange(User $user): void
    {
        if ($user->pending_email !== null || $user->pending_email_nonce !== null) {
            $this->accounts->clearPendingEmail($user);
        }
    }

    public function authorize(EmailChangeLinkData $data): EmailChangeOutcome
    {
        $user = $this->accounts->findById($data->userId);

        if (! $this->linkMatches(self::LINK_AUTHORIZE, $user, $data->hash)) {
            return EmailChangeOutcome::Invalid;
        }

        // Opened twice: the new address already has its link.
        if ($user->pending_email_authorized_at !== null) {
            return EmailChangeOutcome::CheckNewInbox;
        }

        $nonce = $user->pending_email_nonce;

        if ($this->accounts->emailBelongsToAnotherUser($user->pending_email, $user->id)) {
            $this->accounts->clearPendingEmail($user, $nonce);

            return EmailChangeOutcome::Taken;
        }

        // Compare-and-set: only the request this link was made for, and only once, so a request replaced
        // or cancelled meanwhile is never marked approved and a double click mails the new address once.
        if (! $this->accounts->authorizePendingEmail($user, $nonce)) {
            $current = $this->accounts->findById($user->id);

            return $current?->pending_email_nonce === $nonce && $current->pending_email_authorized_at !== null
                ? EmailChangeOutcome::CheckNewInbox
                : EmailChangeOutcome::Invalid;
        }

        $this->sendConfirmationLink($user);

        return EmailChangeOutcome::CheckNewInbox;
    }

    public function confirm(EmailChangeLinkData $data): EmailChangeOutcome
    {
        $user = $this->accounts->findById($data->userId);

        // Never before the current address approved, even if the link was somehow obtained early.
        if (! $this->linkMatches(self::LINK_CONFIRM, $user, $data->hash) || $user->pending_email_authorized_at === null) {
            return EmailChangeOutcome::Invalid;
        }

        $nonce = $user->pending_email_nonce;

        // Someone may have signed up with the address since the request.
        if ($this->accounts->emailBelongsToAnotherUser($user->pending_email, $user->id)) {
            $this->accounts->clearPendingEmail($user, $nonce);

            return EmailChangeOutcome::Taken;
        }

        $previousEmail = $user->email;

        // Compare-and-set: the swap only happens if the row still holds this exact approved request.
        try {
            $applied = $this->accounts->applyPendingEmail($user, $nonce);
        } catch (UniqueConstraintViolationException) {
            $this->accounts->clearPendingEmail($user, $nonce);

            return EmailChangeOutcome::Taken;
        }

        if (! $applied) {
            return EmailChangeOutcome::Invalid;
        }

        // Whoever held a session under the old address has to sign in again.
        $this->auth->revokeSessions($user);
        $this->mailLater($previousEmail, new EmailChangedMail($user->name, self::mask($user->email)));

        return EmailChangeOutcome::Success;
    }

    public function cancelFromLink(EmailChangeLinkData $data): EmailChangeOutcome
    {
        $user = $this->accounts->findById($data->userId);

        // Nothing waiting any more (cancelled, applied, or cleared once its links expired): calm, not an alarm.
        // The route already checked the link's signature, so it was issued for this account.
        if ($user !== null && $user->pending_email === null && $user->pending_email_nonce === null) {
            return EmailChangeOutcome::Expired;
        }

        if (! $this->linkMatches(self::LINK_CANCEL, $user, $data->hash)) {
            return EmailChangeOutcome::Invalid;
        }

        // Only the request this link was mailed for, even if a newer one replaced it a moment ago.
        return $this->accounts->clearPendingEmail($user, $user->pending_email_nonce)
            ? EmailChangeOutcome::Cancelled
            : EmailChangeOutcome::Invalid;
    }

    /** To the current address when the password proves nothing: approve (or cancel) before the new address is mailed. */
    private function sendAuthorizationLink(User $user): void
    {
        $url = $this->signedLink(self::LINK_AUTHORIZE, $user, self::EXPIRES_MINUTES);

        $this->mailLater($user->email, new AuthorizeEmailChangeMail(
            $user->name, self::mask($user->pending_email), $url, $this->cancelUrl($user), self::EXPIRES_MINUTES,
        ));
    }

    private function sendConfirmationLink(User $user): void
    {
        $url = $this->signedLink(self::LINK_CONFIRM, $user, self::EXPIRES_MINUTES);

        $this->mailLater($user->pending_email, new ConfirmEmailChangeMail($user->name, $url, self::EXPIRES_MINUTES));
    }

    private function cancelUrl(User $user): string
    {
        return $this->signedLink(self::LINK_CANCEL, $user, self::CANCEL_EXPIRES_MINUTES);
    }

    private function signedLink(string $purpose, User $user, int $minutes): string
    {
        return URL::temporarySignedRoute(
            'me.email.'.$purpose,
            now()->addMinutes($minutes),
            ['id' => $user->id, 'hash' => $this->linkHash($purpose, $user)],
        );
    }

    /**
     * Binds a link to one purpose, one pending request (the nonce, so asking A, then B, then A again
     * never revives the first link) and the current password hash (so changing or resetting the
     * password voids it). Keyed with APP_KEY, so it cannot be computed from the database alone.
     */
    private function linkHash(string $purpose, User $user): string
    {
        return hash_hmac(
            'sha256',
            implode('|', [$purpose, $user->id, $user->pending_email, $user->pending_email_nonce, $user->password]),
            (string) config('app.key'),
        );
    }

    /** Whether the link was made for this purpose, this pending request and the current password. */
    private function linkMatches(string $purpose, ?User $user, string $hash): bool
    {
        return $user !== null
            && $user->pending_email !== null
            && $user->pending_email_nonce !== null
            && hash_equals($this->linkHash($purpose, $user), $hash);
    }

    /** "jane.doe@example.com" becomes "j***@example.com", enough to recognise without revealing it. */
    private static function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 1).'***@'.$domain;
    }
}
