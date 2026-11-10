<?php

namespace App\User\InterfaceServices;

use App\Models\User;
use App\User\DTOs\Requests\AccountDeletionLinkData;
use App\User\DTOs\Requests\DeleteAccountData;
use App\User\DTOs\Responses\AccountDeletionOutcome;
use App\User\DTOs\Responses\AccountResponse;
use App\User\DTOs\Responses\DeleteAccountResponse;
use App\User\Exceptions\IncorrectPassword;

/**
 * Deleting the signed-in account. A password of the member's own proves ownership and deletes straight away.
 * Accounts without one (the social placeholder), or whose first password was set in Settings without proving
 * anything (`password_set_without_proof_at`, which a stolen session could do), confirm from their inbox: a
 * signed link mailed to the current address deletes the account, so a stolen session alone cannot.
 *
 * Every link carries an HMAC over its purpose, a per-request nonce, the account's email and its password
 * hash, so a newer request, a cancellation, an email change or any new password voids it.
 */
interface AccountDeletionServiceInterface
{
    /** How long a confirmation link (and the pending request shown in Settings) stays valid. */
    public const EXPIRES_MINUTES = 60;

    /**
     * With a password that proves ownership, checks it and deletes the account (`deleted` is true). Otherwise
     * stores a pending deletion (replacing an earlier one, whose links stop working) and mails the confirmation
     * link, with a cancel link, to the account's current address; nothing is deleted yet.
     *
     * @throws IncorrectPassword
     */
    public function request(User $user, DeleteAccountData $data): DeleteAccountResponse;

    /** Drops the pending deletion, so its links stop working. Does nothing when there is none. */
    public function cancel(User $user): AccountResponse;

    /**
     * The signed link mailed to the account: deletes it (once, and only for the request it was mailed for),
     * through the same path as a password-confirmed deletion.
     */
    public function confirmFromLink(AccountDeletionLinkData $data): AccountDeletionOutcome;

    /** The signed "this wasn't me" link in the same email: drops that pending deletion. */
    public function cancelFromLink(AccountDeletionLinkData $data): AccountDeletionOutcome;
}
