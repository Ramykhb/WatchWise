<?php

namespace App\Admin\DTOs\Requests;

/** Which of TMDB's two genre lists the genres page shows. */
final readonly class AdminGenreQuery
{
    public const TYPES = ['movie', 'tv'];

    /**
     * @param  'movie'|'tv'  $type
     */
    public function __construct(public string $type = 'movie') {}

    /**
     * @param  array{type?: string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self($validated['type'] ?? 'movie');
    }
}
