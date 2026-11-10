<?php

namespace App\User\InterfaceServices;

use App\Models\User;
use App\User\DTOs\Requests\EmailChangeLinkData;
use App\User\DTOs\Requests\RequestEmailChangeData;
use App\User\DTOs\Responses\AccountResponse;
use App\User\DTOs\Responses\EmailChangeOutcome;
use App\User\Exceptions\IncorrectPassword;
use App\User\Exceptions\NoPendingEmailChange;

/**
 * Changing the sign-in email: the new address is kept as pending until its owner opens the signed
 * link mailed to it, so a typo or a stolen session cannot hand the account to someone else.
 *
 * Every link carries an HMAC over the pending address, a per-request nonce and the account's
 * password hash, so a newer request, a cancellation or any password change voids it. Accounts
 * that never chose a password must first approve the change from their current address.
 */
interface EmailChangeServiceInterface
{
    /** How long the approval and confirmation links (and the pending change) stay valid. */
    public const EXPIRES_MINUTES = 60;

    /**
     * Stores the address as pending (replacing an earlier one, whose links stop working). With a
     * password of its own, the account must type it; the new address then gets its confirmation link
     * and the current one a notice with a cancel link. Without one, or when the password was chosen in
     * Settings without proving anything (`password_set_without_proof_at`), only the current address is
     * mailed: a link that approves the change (or cancels it) before the new address hears anything.
     *
     * @throws IncorrectPassword
     */
    public function request(User $user, RequestEmailChangeData $data): AccountResponse;

    /**
     * Mails the link the pending change is waiting for again: the approval link to the current
     * address, or the confirmation link to the new one.
     *
     * @throws NoPendingEmailChange
     */
    public function resend(User $user): AccountResponse;

    /** Drops the pending address, so its links stop working. Does nothing when there is none. */
    public function cancel(User $user): AccountResponse;

    /**
     * Drops any pending change without a response; called whenever the password changes or is reset
     * (the links already stop working then, since they are bound to the password hash).
     */
    public function discardPendingChange(User $user): void;

    /**
     * The signed link mailed to the current address when the password proves nothing, once its public
     * page POSTs it (opening the link changes nothing): approves the change (once, and only for the
     * request it was made for) and mails the confirmation link to the new address.
     */
    public function authorize(EmailChangeLinkData $data): EmailChangeOutcome;

    /**
     * The signed link mailed to the new address, once its public page POSTs it (opening the link
     * changes nothing): swaps it in, marks it verified, signs out every session and tells the previous
     * address. Refused until the current address has approved.
     */
    public function confirm(EmailChangeLinkData $data): EmailChangeOutcome;

    /**
     * The signed "this wasn't me" link mailed to the current address: drops that pending change. When nothing
     * is pending any more (already cancelled, applied or pruned) the outcome is `Expired`, not `Invalid`.
     */
    public function cancelFromLink(EmailChangeLinkData $data): EmailChangeOutcome;
}
