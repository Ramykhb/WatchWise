<?php

namespace App\Person\DTOs\Responses;

use JsonSerializable;

/** Everything the Person page needs: the profile, the best-known films and the full filmography. */
final readonly class PersonDetailsResponse implements JsonSerializable
{
    /**
     * @param  list<PersonCreditResponse>  $knownFor
     * @param  list<PersonCreditResponse>  $credits
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $biography,
        public ?string $birthday,
        public ?string $deathday,
        public ?string $placeOfBirth,
        public ?string $knownForDepartment,
        public ?string $profileUrl,
        public array $knownFor,
        public array $credits,
    ) {}

    /**
     * @return array{id: int, name: string, biography: string, birthday: ?string, deathday: ?string, place_of_birth: ?string, known_for_department: ?string, profile_url: ?string, known_for: list<PersonCreditResponse>, credits: list<PersonCreditResponse>}
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'biography' => $this->biography,
            'birthday' => $this->birthday,
            'deathday' => $this->deathday,
            'place_of_birth' => $this->placeOfBirth,
            'known_for_department' => $this->knownForDepartment,
            'profile_url' => $this->profileUrl,
            'known_for' => $this->knownFor,
            'credits' => $this->credits,
        ];
    }
}
