<?php

namespace App\User\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the address of an account whose password proves nothing when it asks to be deleted: the account
 * is only deleted once this link is opened.
 */
class ConfirmAccountDeletionMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string  $cancelUrl  signed link that drops this pending deletion without signing in
     */
    public function __construct(
        public string $name,
        public string $url,
        public string $cancelUrl,
        public int $expiresInMinutes = 60,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirm deleting your WatchWise account');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.confirm-account-deletion', text: 'mail.text.confirm-account-deletion');
    }
}
