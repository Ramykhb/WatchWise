<?php

namespace App\Auth\Listeners;

use App\Auth\Services\EmailVerificationService;
use App\Models\User;
use Illuminate\Auth\Events\Registered;

class SendEmailVerification
{
    public function __construct(private EmailVerificationService $service) {}

    /** Sends the verification link after registration (registered in AppServiceProvider). */
    public function handle(Registered $event): void
    {
        if ($event->user instanceof User) {
            $this->service->send($event->user);
        }
    }
}
