<?php

namespace App\User\DTOs\Responses;

/**
 * What an email-change link did: the `outcome` of the confirmation POSTs (`success` or `check-new-inbox`, else
 * `invalid`, `expired`, `taken`), or of the cancel link on the frontend result page (`cancelled`, `invalid`, `expired`).
 */
enum EmailChangeOutcome: string
{
    case Success = 'success';
    case CheckNewInbox = 'check-new-inbox';
    case Cancelled = 'cancelled';
    case Invalid = 'invalid';
    case Expired = 'expired';
    case Taken = 'taken';
}
