<?php

namespace App\Admin\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Sent to a member's address when the WatchWise team lifts the suspension of their account. */
class AccountReinstatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $name) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your WatchWise account is active again');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.account-reinstated',
            text: 'mail.text.account-reinstated',
            with: ['url' => rtrim((string) config('services.frontend_url'), '/').'/signin'],
        );
    }
}
