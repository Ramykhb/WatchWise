<?php

namespace App\MovieNight\DTOs\Requests;

final readonly class InviteGuestsData
{
    /**
     * @param  list<string>  $invitees  usernames
     */
    public function __construct(public array $invitees) {}

    /**
     * @param  array{invitees: list<string>}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self($validated['invitees']);
    }
}
