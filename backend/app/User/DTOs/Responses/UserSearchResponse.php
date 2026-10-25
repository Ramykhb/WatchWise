<?php

namespace App\User\DTOs\Responses;

use JsonSerializable;

/** Member search results; each user says whether the viewer follows them and they follow the viewer. */
final readonly class UserSearchResponse implements JsonSerializable
{
    /**
     * @param  list<array<string, mixed>>  $users
     */
    public function __construct(
        public int $total,
        public array $users,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'total' => $this->total,
            'users' => $this->users,
        ];
    }
}
