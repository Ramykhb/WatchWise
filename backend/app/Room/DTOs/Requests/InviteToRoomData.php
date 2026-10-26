<?php

namespace App\Room\DTOs\Requests;

final readonly class InviteToRoomData
{
    /**
     * @param  list<string>  $usernames  people to invite; members and people already invited are skipped
     */
    public function __construct(public array $usernames) {}

    /**
     * @param  array{invitees: list<string>}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(array_values($validated['invitees']));
    }
}
