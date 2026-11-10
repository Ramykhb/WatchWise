<?php

namespace App\User\Services;

use App\Models\User;
use App\Support\SocialPlaceholderPassword;
use App\User\DTOs\Requests\AccountDeletionLinkData;
use App\User\DTOs\Requests\DeleteAccountData;
use App\User\DTOs\Responses\AccountDeletionOutcome;
use App\User\DTOs\Responses\AccountResponse;
use App\User\DTOs\Responses\DeleteAccountResponse;
use App\User\Exceptions\IncorrectPassword;
use App\User\InterfaceRepository\AccountDeletionRepositoryInterface;
use App\User\InterfaceRepository\AccountRepositoryInterface;
use App\User\InterfaceServices\AccountDeletionServiceInterface;
use App\User\InterfaceServices\AccountServiceInterface;
use App\User\Mail\AccountDeletedMail;
use App\User\Mail\ConfirmAccountDeletionMail;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class AccountDeletionService implements AccountDeletionServiceInterface
{
    private const LINK_CONFIRM = 'account-deletion-confirm';

    private const LINK_CANCEL = 'account-deletion-cancel';

    public function __construct(
        private readonly AccountRepositoryInterface $accounts,
        private readonly AccountDeletionRepositoryInterface $deletions,
        private readonly AccountServiceInterface $account,
    ) {}

    public function request(User $user, DeleteAccountData $data): DeleteAccountResponse
    {
        if ($this->passwordProvesOwnership($user)) {
            if ($data->password === null) {
                throw IncorrectPassword::forMissingAccountDeletionPassword();
            }
            if (! Hash::check($data->password, $user->password)) {
                throw IncorrectPassword::forAccountDeletion();
            }

            $this->delete($user);

            return DeleteAccountResponse::deleted();
        }

        $this->deletions->storeDeletionRequest($user, bin2hex(random_bytes(32)));

        $this->mailLater($user->email, new ConfirmAccountDeletionMail(
            $user->name,
            $this->signedLink(self::LINK_CONFIRM, 'me.delete.confirm', $user),
            $this->signedLink(self::LINK_CANCEL, 'me.delete.cancel', $user),
            self::EXPIRES_MINUTES,
        ));

        return DeleteAccountResponse::confirmationSent($user->email);
    }

    public function cancel(User $user): AccountResponse
    {
        if ($user->deletion_nonce !== null || $user->deletion_requested_at !== null) {
            $this->deletions->clearDeletionRequest($user);
        }

        return AccountResponse::fromModel($user);
    }

    public function confirmFromLink(AccountDeletionLinkData $data): AccountDeletionOutcome
    {
        $user = $this->accounts->findById($data->userId);

        if (! $this->linkMatches(self::LINK_CONFIRM, $user, $data->hash)) {
            return AccountDeletionOutcome::Invalid;
        }

        // Compare-and-set: consumes this exact request once, so a double click, a replaced or cancelled request,
        // or a password or email change that landed meanwhile never deletes anything.
        if (! $this->deletions->clearDeletionRequest($user, $user->deletion_nonce)) {
            return AccountDeletionOutcome::Invalid;
        }

        $this->delete($user);

        return AccountDeletionOutcome::Deleted;
    }

    public function cancelFromLink(AccountDeletionLinkData $data): AccountDeletionOutcome
    {
        $user = $this->accounts->findById($data->userId);

        if (! $this->linkMatches(self::LINK_CANCEL, $user, $data->hash)) {
            return AccountDeletionOutcome::Invalid;
        }

        // Only the request this link was mailed for, even if a newer one replaced it a moment ago.
        return $this->deletions->clearDeletionRequest($user, $user->deletion_nonce)
            ? AccountDeletionOutcome::Cancelled
            : AccountDeletionOutcome::Invalid;
    }

    /**
     * Without a password of its own, or with one chosen in Settings without knowing any earlier one (which a
     * stolen session could do), the password proves nothing: the inbox confirms instead. The mark is checked
     * first because the placeholder check costs a bcrypt round.
     */
    private function passwordProvesOwnership(User $user): bool
    {
        return $user->password_set_without_proof_at === null && ! SocialPlaceholderPassword::isStoredFor($user);
    }

    /** The one deletion path (avatar file, account and everything it owns), then a notice to the old address. */
    private function delete(User $user): void
    {
        [$name, $email] = [$user->name, $user->email];

        $this->account->deleteAccount($user);

        $this->mailLater($email, new AccountDeletedMail($name));
    }

    private function signedLink(string $purpose, string $route, User $user): string
    {
        return URL::temporarySignedRoute(
            $route,
            now()->addMinutes(self::EXPIRES_MINUTES),
            ['id' => $user->id, 'hash' => $this->linkHash($purpose, $user)],
        );
    }

    /**
     * Binds a link to one purpose, one pending request (the nonce, so a newer request or a cancellation voids
     * it), the account's email and the current password hash (so an email change or any new password voids
     * it). Keyed with APP_KEY, so it cannot be computed from the database alone.
     */
    private function linkHash(string $purpose, User $user): string
    {
        return hash_hmac(
            'sha256',
            implode('|', [$purpose, $user->id, $user->deletion_nonce, $user->email, $user->password]),
            (string) config('app.key'),
        );
    }

    /** Whether the link was made for this purpose and the request still pending for this email and password. */
    private function linkMatches(string $purpose, ?User $user, string $hash): bool
    {
        return $user !== null
            && $user->deletion_nonce !== null
            && $user->deletion_requested_at?->isAfter(now()->subMinutes(self::EXPIRES_MINUTES)) === true
            && hash_equals($this->linkHash($purpose, $user), $hash);
    }

    /** After the response is sent, so a slow or broken mail server is logged without failing the request. */
    private function mailLater(string $to, Mailable $mail): void
    {
        defer(fn () => rescue(fn () => Mail::to($to)->send($mail)));
    }
}
