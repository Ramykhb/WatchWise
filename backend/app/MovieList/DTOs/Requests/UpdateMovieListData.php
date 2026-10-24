<?php

namespace App\MovieList\DTOs\Requests;

final readonly class UpdateMovieListData
{
    /**
     * @param  array<string, mixed>  $attributes  only the fields the client sent (title, description, is_public)
     * @param  list<int>|null  $tmdbIds  null leaves the films alone; an array replaces them in that order
     */
    public function __construct(
        public array $attributes,
        public ?array $tmdbIds,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            collect($validated)->only(['title', 'description', 'is_public'])->all(),
            array_key_exists('tmdb_ids', $validated) ? $validated['tmdb_ids'] : null,
        );
    }
}
