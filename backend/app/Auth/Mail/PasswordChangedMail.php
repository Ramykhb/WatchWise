<?php

namespace App\Auth\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Sent to the account after a password reset through the emailed link, so the owner hears of one they did not do. */
class PasswordChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $name) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your WatchWise password was changed');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.password-changed',
            text: 'mail.text.password-changed',
            with: ['url' => rtrim((string) config('services.frontend_url'), '/').'/forgot-password'],
        );
    }
}
