<?php

namespace App\MovieNight\Mail;

use App\Support\MailText;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class MovieNightCalendarMail extends Mailable
{
    public function __construct(public string $title, public string $movieTitle, public string $when,
        public string $location, public ?string $meetingLink, public ?string $note, public string $url,
        public string $ics, public bool $cancelled) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: ($this->cancelled ? 'Cancelled: ' : 'Movie night: ').MailText::line($this->title));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.movie-night-calendar', text: 'mail.text.movie-night-calendar');
    }

    public function attachments(): array
    {
        return [Attachment::fromData(fn (): string => $this->ics, 'movie-night.ics')
            ->withMime('text/calendar; charset=UTF-8; method='.($this->cancelled ? 'CANCEL' : 'REQUEST'))];
    }
}
