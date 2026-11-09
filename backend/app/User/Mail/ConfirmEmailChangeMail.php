<?php

namespace App\User\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Sent to the new address: the signed link that makes it the sign-in email. */
class ConfirmEmailChangeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $name, public string $url, public int $expiresInMinutes = 60) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirm your new WatchWise email');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.confirm-email-change', text: 'mail.text.confirm-email-change');
    }
}
