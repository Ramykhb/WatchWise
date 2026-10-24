<?php

namespace App\Auth\Listeners;

use App\Auth\InterfaceServices\EmailVerificationServiceInterface;
use App\Models\User;
use Illuminate\Auth\Events\Registered;

class SendEmailVerification
{
    public function __construct(private EmailVerificationServiceInterface $service) {}

    /** Sends the verification link after registration (registered in AppServiceProvider). */
    public function handle(Registered $event): void
    {
        if ($event->user instanceof User) {
            $this->service->send($event->user);
        }
    }
}
