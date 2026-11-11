<?php

namespace App\Support;

use App\Support\Exceptions\MailNotDelivered;
use Closure;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends account emails after the response, so mail latency never shows in a response time (which could reveal
 * whether an account exists) and a broken mail server never fails the request. Mail is sent directly, not queued:
 * production runs no queue worker. Shared by the Auth and User services.
 */
trait SendsMailAfterResponse
{
    /**
     * Milliseconds to wait before the second (and last) attempt. Kept short: with Apache mod_php the deferred work
     * runs before the connection closes and holds the worker; with the 8-second transport timeouts the worst case
     * is about 17 seconds.
     */
    private const MAIL_RETRY_BACKOFF_MS = [1000];

    /**
     * Queues `$mail` for after the response. A closure is called once, before the first attempt, to build the mail
     * (for example after storing the token its link carries); when it returns null nothing is sent. Sending is tried
     * twice, 1 second apart; a final failure is reported once, without the address, the link or the transport's message.
     *
     * @param  Mailable|Closure(): ?Mailable  $mail
     */
    private function mailLater(string $to, Mailable|Closure $mail): void
    {
        defer(fn () => $this->sendWithRetries($to, $mail));
    }

    /** @param  Mailable|Closure(): ?Mailable  $mail */
    private function sendWithRetries(string $to, Mailable|Closure $mail): void
    {
        $mailable = $mail instanceof Mailable ? $mail : null;
        $attempts = 0;

        try {
            $mailable ??= $mail();

            if ($mailable === null) {
                return;
            }

            retry(self::MAIL_RETRY_BACKOFF_MS, function (int $attempt) use ($to, $mailable, &$attempts): void {
                $attempts = $attempt;
                Mail::to($to)->send($mailable);
            });
        } catch (Throwable $failure) {
            report(MailNotDelivered::after($attempts, $mailable === null ? null : $mailable::class, $failure));
        }
    }
}
