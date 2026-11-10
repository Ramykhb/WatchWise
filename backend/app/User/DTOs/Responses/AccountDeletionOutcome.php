<?php

namespace App\User\DTOs\Responses;

/**
 * What an account-deletion link did: the `outcome` of the confirmation POST (`deleted`, `invalid`, `expired`), or
 * of the cancel link on the frontend result page (`cancelled`, `invalid`, `expired`).
 */
enum AccountDeletionOutcome: string
{
    case Deleted = 'deleted';
    case Cancelled = 'cancelled';
    case Invalid = 'invalid';
    case Expired = 'expired';
}
