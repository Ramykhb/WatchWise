<?php

namespace App\User\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Sent when an account that signed in through a provider gets its first password. */
class PasswordAddedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $name) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'A password was added to your WatchWise account');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.password-added',
            text: 'mail.text.password-added',
            with: ['url' => rtrim((string) config('services.frontend_url'), '/').'/forgot-password'],
        );
    }
}
