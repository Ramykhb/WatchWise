<?php

namespace App\Person\DTOs\Responses;

use JsonSerializable;

/** Someone who often works with a person, and on how many of their best-known films. */
final readonly class CollaboratorResponse implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $profileUrl,
        /** Their usual role on those films, e.g. "Actor" or "Original Music Composer". */
        public string $role,
        public int $films,
    ) {}

    /**
     * @return array{id: int, name: string, profile_url: ?string, role: string, films: int}
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'profile_url' => $this->profileUrl,
            'role' => $this->role,
            'films' => $this->films,
        ];
    }
}
