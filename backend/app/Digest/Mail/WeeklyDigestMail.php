<?php

namespace App\Digest\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class WeeklyDigestMail extends Mailable
{
    public function __construct(public array $picks, public array $activity, public string $homeUrl, public string $settingsUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your weekly WatchWise picks and friends’ activity');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.weekly-digest', text: 'mail.text.weekly-digest');
    }
}
