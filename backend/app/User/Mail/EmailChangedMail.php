<?php

namespace App\User\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Sent to the previous address once the new one is confirmed. */
class EmailChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string  $maskedNewEmail  the new address, partly hidden ("j***@example.com")
     */
    public function __construct(public string $name, public string $maskedNewEmail) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your WatchWise email was changed');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.email-changed',
            text: 'mail.text.email-changed',
            with: ['url' => rtrim((string) config('services.frontend_url'), '/').'/signin'],
        );
    }
}
