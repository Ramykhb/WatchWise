<?php

namespace App\User\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

trait SendsMailAfterResponse
{
    /** After the response is sent, so a slow or broken mail server is logged without failing the request. */
    private function mailLater(string $to, Mailable $mail): void
    {
        defer(fn () => rescue(fn () => Mail::to($to)->send($mail)));
    }
}
