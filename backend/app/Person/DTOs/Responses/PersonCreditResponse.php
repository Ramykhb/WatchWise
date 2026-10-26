<?php

namespace App\Person\DTOs\Responses;

use JsonSerializable;

/** One film a person worked on, with every role they had on it. */
final readonly class PersonCreditResponse implements JsonSerializable
{
    /**
     * @param  list<string>  $roles  e.g. ["Director", "Screenplay"] or ["Actor"]
     */
    public function __construct(
        public int $id,
        public string $title,
        public ?int $year,
        public ?string $posterUrl,
        public float $rating,
        public int $voteCount,
        public ?string $character,
        public array $roles,
    ) {}

    /**
     * @return array{id: int, title: string, year: ?int, poster_url: ?string, rating: float, vote_count: int, character: ?string, roles: list<string>}
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'year' => $this->year,
            'poster_url' => $this->posterUrl,
            'rating' => $this->rating,
            'vote_count' => $this->voteCount,
            'character' => $this->character,
            'roles' => $this->roles,
        ];
    }
}
