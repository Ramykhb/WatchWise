<?php

namespace App\User\DTOs\Responses;

/** What opening an account-deletion link did; the value is the `account_deletion` query flag sent to the frontend. */
enum AccountDeletionOutcome: string
{
    case Deleted = 'deleted';
    case Cancelled = 'cancelled';
    case Invalid = 'invalid';
    case Expired = 'expired';

    /** Where the link lands on the frontend: Sign in once the account is gone, Settings otherwise. */
    public function frontendPath(): string
    {
        $page = $this === self::Deleted ? '/signin' : '/settings';

        return $page.'?account_deletion='.$this->value;
    }
}
