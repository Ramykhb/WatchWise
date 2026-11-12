<?php

namespace App\User\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the current address of an account without a password of its own: the change only goes
 * ahead (and the new address only gets its link) once this address approves it.
 */
class AuthorizeEmailChangeMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string  $maskedNewEmail  the requested address, partly hidden ("j***@example.com")
     * @param  string  $cancelUrl  signed link that drops this pending change without signing in
     */
    public function __construct(
        public string $name,
        public string $maskedNewEmail,
        public string $url,
        public string $cancelUrl,
        public int $expiresInMinutes = 60,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Approve your WatchWise email change');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.authorize-email-change', text: 'mail.text.authorize-email-change');
    }
}
