<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerifyEmailMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $url, public int $expiresInMinutes = 60) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Verify your WatchWise email');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.verify-email');
    }
}
