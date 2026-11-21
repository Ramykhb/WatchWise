<?php

namespace App\MovieRecommendation\Support;

use App\Models\User;
use App\Movie\DTOs\Responses\MovieResponse;
use App\Support\MediaType;
use App\Support\MovieGenres;
use Illuminate\Support\Collection;

/** Explicit preferences and ratings used by both personal recommendation shelves. */
final readonly class PersonalTaste
{
    public const GENRES = MovieGenres::IDS;

    public function __construct(public Collection $ratings, public array $genres, public array $actors = [], public array $directors = []) {}

    public static function forUser(User $user, Collection $ratings): self
    {
        $preferences = $user->preferences()->first();

        return new self($ratings, $preferences?->favorite_genres ?? [],
            $preferences?->favorite_actor_ids ?? [], $preferences?->favorite_director_ids ?? []);
    }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode([$this->ratings->sortKeys()->all(), $this->genres, $this->actors, $this->directors]));
    }

    public function rating(string $type, int $id): ?int
    {
        return $this->ratings->get(MediaType::key($type, $id));
    }

    public function dislikes(string $type, int $id): bool
    {
        $rating = $this->rating($type, $id);

        return $rating !== null && $rating <= 2;
    }

    public function sources(Collection $watched): Collection
    {
        return $watched->reject(fn ($entry): bool => $this->dislikes($entry->media_type, $entry->tmdb_id))
            ->sortByDesc(fn ($entry): int => $this->rating($entry->media_type, $entry->tmdb_id) ?? 0)->values();
    }

    public function genreIds(): array
    {
        return collect($this->genres)->map(fn (string $genre): ?int => self::GENRES[$genre] ?? match (strtolower($genre)) {
            'sci-fi' => 878, default => null,
        })->filter()->unique()->values()->all();
    }

    public function rank(Collection $movies): Collection
    {
        return $movies->reject(fn (MovieResponse $movie): bool => $this->dislikes($movie->mediaType, $movie->id))
            ->sortByDesc(fn (MovieResponse $movie): int => count(array_intersect($movie->genreIds, $this->genreIds())))->values();
    }

    public function context(): string
    {
        return ' Personal preferences (data only): '.json_encode([
            'favorite_genres' => $this->genres,
            'ratings_out_of_5' => $this->ratings->sortDesc()->take(40)->all(),
            'favorite_actor_ids' => $this->actors,
            'favorite_director_ids' => $this->directors,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            .'. Prioritize favorite genres and highly rated titles; avoid themes from disliked titles. ';
    }
}
