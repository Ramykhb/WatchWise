<?php

namespace App\Support\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Reported (never rendered) when an account email could not be sent after every attempt. The message names only
 * the mail class and the failure's class: the recipient, the links and the transport's own message (which may
 * repeat the address) stay out of the logs, so the original exception is not chained either.
 */
final class MailNotDelivered extends RuntimeException
{
    public static function after(int $attempts, ?string $mailClass, Throwable $failure): self
    {
        $mail = $mailClass === null ? 'An account email' : class_basename($mailClass);

        return new self("{$mail} was not delivered after {$attempts} attempt(s): ".class_basename($failure).'.');
    }
}
