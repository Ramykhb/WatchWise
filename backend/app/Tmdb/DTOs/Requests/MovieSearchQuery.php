<?php

namespace App\Tmdb\DTOs\Requests;

final readonly class MovieSearchQuery
{
    public function __construct(
        public string $query,
        public int $page = 1,
        public ?int $year = null,
    ) {}

    /**
     * @param  array{q: string, page?: int|string, year?: int|string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            $validated['q'],
            (int) ($validated['page'] ?? 1),
            isset($validated['year']) ? (int) $validated['year'] : null,
        );
    }
}
