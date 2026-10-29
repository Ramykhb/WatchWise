<?php

namespace App\Movie\DTOs\Requests;

/**
 * Fields an admin changes on a local movie. A null title, flag or genre list is left as it is. The overview,
 * director, runtime and release date can be cleared, so whether each was supplied is tracked apart from its value.
 */
final readonly class UpdateLocalMovieData
{
    /**
     * @param  list<int>|null  $genreIds  null keeps the genres, an empty list clears them
     */
    public function __construct(
        public ?string $title = null,
        public ?string $overview = null,
        public ?bool $isHidden = null,
        public ?bool $isFeatured = null,
        public bool $overviewSupplied = false,
        public ?string $director = null,
        public ?int $runtime = null,
        public ?string $releaseDate = null,
        public ?array $genreIds = null,
        public bool $directorSupplied = false,
        public bool $runtimeSupplied = false,
        public bool $releaseDateSupplied = false,
    ) {}

    /**
     * @param  array{title?: string, overview?: string|null, is_hidden?: bool, is_featured?: bool, director?: string|null, runtime?: int|string|null, release_date?: string|null, genre_ids?: list<int|string>}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            title: $validated['title'] ?? null,
            overview: $validated['overview'] ?? null,
            isHidden: isset($validated['is_hidden']) ? (bool) $validated['is_hidden'] : null,
            isFeatured: isset($validated['is_featured']) ? (bool) $validated['is_featured'] : null,
            overviewSupplied: array_key_exists('overview', $validated),
            director: isset($validated['director']) ? trim($validated['director']) : null,
            runtime: isset($validated['runtime']) ? (int) $validated['runtime'] : null,
            releaseDate: $validated['release_date'] ?? null,
            genreIds: isset($validated['genre_ids']) ? array_values(array_map('intval', $validated['genre_ids'])) : null,
            directorSupplied: array_key_exists('director', $validated),
            runtimeSupplied: array_key_exists('runtime', $validated),
            releaseDateSupplied: array_key_exists('release_date', $validated),
        );
    }

    /** Whether any of the film's content (not just its hidden or featured flag) is being changed. */
    public function editsContent(): bool
    {
        return $this->title !== null
            || $this->overviewSupplied
            || $this->directorSupplied
            || $this->runtimeSupplied
            || $this->releaseDateSupplied
            || $this->genreIds !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function changes(): array
    {
        $changes = array_filter([
            'title' => $this->title,
            'is_hidden' => $this->isHidden,
            'is_featured' => $this->isFeatured,
            'genre_ids' => $this->genreIds,
        ], fn (mixed $value): bool => $value !== null);

        if ($this->overviewSupplied) {
            $changes['overview'] = $this->overview;
        }

        if ($this->directorSupplied) {
            $changes['director'] = $this->director === '' ? null : $this->director;
        }

        if ($this->runtimeSupplied) {
            $changes['runtime'] = $this->runtime;
        }

        if ($this->releaseDateSupplied) {
            $changes['release_date'] = $this->releaseDate;
        }

        return $changes;
    }
}
