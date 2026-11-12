<?php

namespace App\Admin\Mail;

use App\Support\MailText;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to a member's address when the WatchWise team suspends their account. It carries the reason and the end of the
 * suspension when staff gave them; it never says who acted.
 */
class AccountSuspendedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $name, public ?string $reason = null, public ?CarbonInterface $until = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your WatchWise account was suspended');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.account-suspended',
            text: 'mail.text.account-suspended',
            with: ['instruction' => $this->instruction()],
        );
    }

    /** What the suspension means for the member: the reason when there is one, that they can't sign in, and when it ends. */
    private function instruction(): string
    {
        $reason = $this->reason === null ? '' : 'Reason given: '.MailText::line($this->reason).' ';
        $ending = $this->until === null
            ? 'If the suspension is lifted, we’ll let you know by email.'
            : 'The suspension is set to end on '.$this->until->copy()->utc()->format('j F Y, H:i').' UTC, and we’ll let you know by email when your account is active again.';

        return $reason.'While it is suspended you can’t sign in. '.$ending;
    }
}
