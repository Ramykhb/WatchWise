<?php

namespace App\User\DTOs\Responses;

use JsonSerializable;

/** What the Profile page shows, already filtered by the owner's privacy settings and the viewer's relationship. */
final readonly class ProfileResponse implements JsonSerializable
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(private array $payload) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->payload;
    }
}
