<?php

namespace App\Admin\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Sent to a member's address when the WatchWise team suspends their account; it never says who acted or why. */
class AccountSuspendedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $name) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your WatchWise account was suspended');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.account-suspended', text: 'mail.text.account-suspended');
    }
}
