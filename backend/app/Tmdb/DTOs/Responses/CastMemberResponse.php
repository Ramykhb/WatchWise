<?php

namespace App\Tmdb\DTOs\Responses;

use JsonSerializable;

/** An actor and the character they play. */
final readonly class CastMemberResponse implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $name,
        public string $character,
        public ?string $profileUrl,
    ) {}

    /**
     * @return array{id: int, name: string, character: string, profile_url: ?string}
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'character' => $this->character,
            'profile_url' => $this->profileUrl,
        ];
    }
}
