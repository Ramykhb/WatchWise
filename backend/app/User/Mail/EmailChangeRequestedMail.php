<?php

namespace App\User\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Sent to the current address when someone asks to move the account to another one. */
class EmailChangeRequestedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string  $maskedNewEmail  the requested address, partly hidden ("j***@example.com")
     * @param  string  $cancelUrl  signed link that drops this pending change without signing in
     */
    public function __construct(public string $name, public string $maskedNewEmail, public string $cancelUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Someone asked to change your WatchWise email');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.email-change-requested',
            text: 'mail.text.email-change-requested',
            with: ['settingsUrl' => rtrim((string) config('services.frontend_url'), '/').'/edit-profile'],
        );
    }
}
