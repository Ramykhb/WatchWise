<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\EmailVerificationService;
use Illuminate\Auth\Events\Registered;

class SendEmailVerification
{
    public function __construct(private EmailVerificationService $service) {}

    /** Sends the verification link after registration (auto-discovered by the Registered type-hint). */
    public function handle(Registered $event): void
    {
        if ($event->user instanceof User) {
            $this->service->send($event->user);
        }
    }
}
