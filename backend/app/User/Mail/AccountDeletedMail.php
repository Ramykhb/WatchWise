<?php

namespace App\User\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Sent to the account's address once it has been deleted, whichever way the deletion was confirmed. */
class AccountDeletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $name) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your WatchWise account was deleted');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.account-deleted',
            text: 'mail.text.account-deleted',
            with: ['url' => rtrim((string) config('services.frontend_url'), '/').'/signup'],
        );
    }
}
