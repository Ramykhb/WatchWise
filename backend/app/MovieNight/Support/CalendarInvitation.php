<?php

namespace App\MovieNight\Support;

use App\Models\MovieNight;
use App\Models\User;

final class CalendarInvitation
{
    public static function render(MovieNight $night, User $recipient, string $movieTitle, bool $cancelled): string
    {
        $text = fn (string $value): string => str_replace(['\\', "\r\n", "\r", "\n", ';', ','], ['\\\\', '\\n', '\\n', '\\n', '\\;', '\\,'], $value);
        $parameter = fn (string $value): string => str_replace(["\r", "\n", '"'], ['', '', "'"], $value);
        $method = $cancelled ? 'CANCEL' : 'REQUEST';
        $url = rtrim(config('services.frontend_url'), '/').'/movie-nights/'.$night->id;
        $lines = [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//WatchWise//Movie Nights//EN', 'CALSCALE:GREGORIAN', 'METHOD:'.$method,
            'BEGIN:VEVENT', 'UID:movie-night-'.$night->id.'@watchwise.app', 'SEQUENCE:'.$night->calendar_revision,
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:'.$night->scheduled_at->copy()->utc()->format('Ymd\THis\Z'),
            'DTEND:'.$night->scheduled_at->copy()->addHours(3)->utc()->format('Ymd\THis\Z'),
            'SUMMARY:'.$text($night->title.' — '.$movieTitle),
            'DESCRIPTION:'.$text(implode("\n", array_filter(['Movie: '.$movieTitle, $night->note, $night->meeting_link, $url]))),
            'LOCATION:'.$text($night->location ?? $night->meeting_link ?? 'To be announced'), 'URL:'.$url,
            'ORGANIZER;CN="WatchWise":mailto:'.config('mail.from.address'),
            'ATTENDEE;CN="'.$parameter($recipient->name).'";RSVP=FALSE:mailto:'.$recipient->email,
            'STATUS:'.($cancelled ? 'CANCELLED' : 'CONFIRMED'), 'END:VEVENT', 'END:VCALENDAR',
        ];
        // RFC 5545 lines are folded by UTF-8 octets, without splitting a character.
        $folded = [];
        foreach ($lines as $line) {
            $limit = 75;
            while (strlen($line) > $limit) {
                $part = mb_strcut($line, 0, $limit, 'UTF-8');
                $folded[] = ($limit === 74 ? ' ' : '').$part;
                $line = substr($line, strlen($part));
                $limit = 74;
            }
            $folded[] = ($limit === 74 ? ' ' : '').$line;
        }

        return implode("\r\n", $folded)."\r\n";
    }
}
