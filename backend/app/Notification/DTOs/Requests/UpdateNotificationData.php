<?php

namespace App\Notification\DTOs\Requests;

final readonly class UpdateNotificationData
{
    public function __construct(public bool $read) {}

    /**
     * @param  array{read: bool|int|string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self((bool) $validated['read']);
    }
}
