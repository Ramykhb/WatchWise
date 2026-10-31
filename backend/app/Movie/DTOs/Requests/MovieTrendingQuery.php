<?php

namespace App\Movie\DTOs\Requests;

final readonly class MovieTrendingQuery
{
    public function __construct(public string $type = 'movie') {}

    /** @param array{type?: string} $validated */
    public static function fromValidated(array $validated): self
    {
        return new self($validated['type'] ?? 'movie');
    }
}
