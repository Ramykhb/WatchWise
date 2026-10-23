<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $name, public string $url, public int $expiresInMinutes = 60) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reset your WatchWise password');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.reset-password');
    }
}
