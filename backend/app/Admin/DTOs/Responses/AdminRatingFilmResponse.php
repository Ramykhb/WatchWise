<?php

namespace App\Admin\DTOs\Responses;

use App\Models\LocalMovie;
use Carbon\CarbonInterface;
use JsonSerializable;

/** What the star ratings of one film look like: how many, how they spread and when they came. */
final readonly class AdminRatingFilmResponse implements JsonSerializable
{
    /**
     * @param  array<int, int>  $distribution  count keyed by star value, all five present
     */
    public function __construct(
        public int $tmdbId,
        public string $mediaType,
        public ?LocalMovie $film,
        public int $total,
        public ?float $average,
        public array $distribution,
        public int $withTextCount,
        public ?CarbonInterface $firstRatedAt,
        public ?CarbonInterface $lastRatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'tmdb_id' => $this->tmdbId,
            'media_type' => $this->mediaType,
            'film' => $this->film === null ? null : ['tmdb_id' => $this->tmdbId, 'title' => $this->film->title, 'poster_path' => $this->film->poster_path],
            'total' => $this->total,
            'average' => $this->average,
            'distribution' => $this->distribution,
            'with_text_count' => $this->withTextCount,
            'first_rated_at' => $this->firstRatedAt?->toJSON(),
            'last_rated_at' => $this->lastRatedAt?->toJSON(),
        ];
    }
}
