<?php

namespace App\User\DTOs\Responses;

/** What opening an email-change link did; the value is the `email_change` query flag sent to the frontend. */
enum EmailChangeOutcome: string
{
    case Success = 'success';
    case Invalid = 'invalid';
    case Expired = 'expired';
    case Taken = 'taken';
}
