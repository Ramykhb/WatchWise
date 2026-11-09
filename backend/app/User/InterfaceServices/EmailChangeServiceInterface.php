<?php

namespace App\User\InterfaceServices;

use App\Models\User;
use App\User\DTOs\Requests\ConfirmEmailChangeData;
use App\User\DTOs\Requests\RequestEmailChangeData;
use App\User\DTOs\Responses\AccountResponse;
use App\User\DTOs\Responses\EmailChangeOutcome;
use App\User\Exceptions\IncorrectPassword;
use App\User\Exceptions\NoPendingEmailChange;

/**
 * Changing the sign-in email: the new address is kept as pending until its owner opens the signed
 * link mailed to it, so a typo or a stolen session cannot hand the account to someone else.
 */
interface EmailChangeServiceInterface
{
    /**
     * Checks the current password (skipped for accounts that never chose one), stores the address as
     * pending (replacing an earlier one, whose link stops working), mails the confirmation link to it
     * and warns the current address.
     *
     * @throws IncorrectPassword
     */
    public function request(User $user, RequestEmailChangeData $data): AccountResponse;

    /**
     * Mails a fresh confirmation link to the pending address.
     *
     * @throws NoPendingEmailChange
     */
    public function resend(User $user): AccountResponse;

    /** Drops the pending address, so its links stop working. Does nothing when there is none. */
    public function cancel(User $user): AccountResponse;

    /**
     * The signed link target: swaps in the pending address, marks it verified, signs out every
     * session and tells the previous address.
     */
    public function confirm(ConfirmEmailChangeData $data): EmailChangeOutcome;
}
