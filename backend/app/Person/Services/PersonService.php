<?php

namespace App\Person\Services;

use App\Person\DTOs\Responses\CollaboratorResponse;
use App\Person\DTOs\Responses\CollaboratorsResponse;
use App\Person\DTOs\Responses\PersonCreditResponse;
use App\Person\DTOs\Responses\PersonDetailsResponse;
use App\Person\InterfaceRepository\PersonRepositoryInterface;
use App\Person\InterfaceServices\PersonServiceInterface;
use App\Support\Exceptions\TmdbUnavailable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PersonService implements PersonServiceInterface
{
    private const CACHE_PREFIX = 'tmdb.person.';

    private const CACHE_HOURS = 6;

    /** How many films the "Known for" row shows. */
    private const KNOWN_FOR_LIMIT = 6;

    /** How many best-known films the collaborators are counted from (one TMDB call each). */
    private const COLLABORATOR_FILMS = 10;

    /** How many of each film's billed actors count; the rest are smaller parts. */
    private const COLLABORATOR_CAST = 15;

    /** The card shows the three people who share the most films with this person. */
    private const COLLABORATOR_LIMIT = 3;

    /** Crew jobs that make someone a collaborator (not assistants, drivers, …). */
    private const COLLABORATOR_JOBS = ['Director', 'Screenplay', 'Writer', 'Producer', 'Original Music Composer', 'Director of Photography', 'Editor'];

    public function __construct(private readonly PersonRepositoryInterface $people) {}

    public function find(int $id): PersonDetailsResponse
    {
        // A person's details rarely change, so repeat visits don't spend the TMDB quota.
        // Only successful answers are cached: PersonNotFound and TmdbUnavailable pass straight through.
        $person = Cache::remember(self::CACHE_PREFIX.$id, now()->addHours(self::CACHE_HOURS), fn () => $this->people->find($id));

        // One entry per film, even when the person acted in it and also directed or wrote it.
        $credits = collect([...($person['movie_credits']['cast'] ?? []), ...($person['movie_credits']['crew'] ?? [])])
            ->groupBy('id')
            ->map(fn (Collection $entries): PersonCreditResponse => $this->toCredit($entries))
            ->sortByDesc(fn (PersonCreditResponse $credit): int => $credit->year ?? 0)
            ->values();

        return new PersonDetailsResponse(
            id: $person['id'],
            name: $person['name'] ?? '',
            biography: $person['biography'] ?? '',
            birthday: ($person['birthday'] ?? null) ?: null,
            deathday: ($person['deathday'] ?? null) ?: null,
            placeOfBirth: ($person['place_of_birth'] ?? null) ?: null,
            knownForDepartment: ($person['known_for_department'] ?? null) ?: null,
            profileUrl: $this->imageUrl($person['profile_path'] ?? null, 'h632'),
            // The most-voted films are the ones people know them for.
            knownFor: $credits
                ->sortByDesc(fn (PersonCreditResponse $credit): int => $credit->voteCount)
                ->take(self::KNOWN_FOR_LIMIT)
                ->values()
                ->all(),
            credits: $credits->all(),
        );
    }

    public function collaborators(int $id): CollaboratorsResponse
    {
        try {
            // Up to ten TMDB calls the first time, so the answer is cached like the person
            // (as plain arrays: the cache doesn't store objects).
            $collaborators = Cache::remember(self::CACHE_PREFIX.$id.'.collaborators', now()->addHours(self::CACHE_HOURS), fn (): array => $this->findCollaborators($id));
        } catch (TmdbUnavailable $e) {
            // The card is optional: without TMDB it stays empty (and isn't cached), and the page still works.
            report($e);

            return new CollaboratorsResponse([]);
        }

        return new CollaboratorsResponse(array_map(fn (array $collaborator): CollaboratorResponse => new CollaboratorResponse(
            id: $collaborator['id'],
            name: $collaborator['name'],
            profileUrl: $this->imageUrl($collaborator['profile_path'], 'w185'),
            role: $collaborator['role'],
            films: $collaborator['films'],
        ), $collaborators));
    }

    /**
     * @return list<array{id: int, name: string, profile_path: ?string, role: string, films: int}>
     */
    private function findCollaborators(int $id): array
    {
        $films = collect($this->find($id)->credits)
            ->sortByDesc(fn (PersonCreditResponse $credit): int => $credit->voteCount)
            ->take(self::COLLABORATOR_FILMS);

        // Per collaborator: their TMDB entry, the films they share with this person, and the roles they had.
        $people = [];
        foreach ($films as $film) {
            $credits = $this->people->movieCredits($film->id);
            $entries = [
                ...array_map(
                    fn (array $actor): array => [...$actor, 'role' => 'Actor'],
                    array_slice($credits['cast'] ?? [], 0, self::COLLABORATOR_CAST),
                ),
                ...array_map(
                    fn (array $crew): array => [...$crew, 'role' => $crew['job']],
                    array_filter($credits['crew'] ?? [], fn (array $crew): bool => in_array($crew['job'] ?? null, self::COLLABORATOR_JOBS, true)),
                ),
            ];

            foreach ($entries as $entry) {
                if ($entry['id'] === $id) {
                    continue;
                }
                $people[$entry['id']] ??= ['person' => $entry, 'films' => [], 'roles' => []];
                $people[$entry['id']]['films'][$film->id] = true;
                $people[$entry['id']]['roles'][] = $entry['role'];
            }
        }

        return collect($people)
            ->filter(fn (array $person): bool => count($person['films']) >= 2)
            ->sortByDesc(fn (array $person): int => count($person['films']))
            ->take(self::COLLABORATOR_LIMIT)
            ->map(fn (array $person): array => [
                'id' => $person['person']['id'],
                'name' => $person['person']['name'] ?? '',
                'profile_path' => $person['person']['profile_path'] ?? null,
                // Their most frequent role across the shared films.
                'role' => collect($person['roles'])->countBy()->sortDesc()->keys()->first(),
                'films' => count($person['films']),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $entries  every cast and crew entry for one film
     */
    private function toCredit(Collection $entries): PersonCreditResponse
    {
        $movie = $entries->first();
        $releaseDate = ($movie['release_date'] ?? null) ?: null;

        return new PersonCreditResponse(
            id: $movie['id'],
            title: $movie['title'] ?? '',
            year: $releaseDate ? (int) substr($releaseDate, 0, 4) : null,
            posterUrl: $this->imageUrl($movie['poster_path'] ?? null, 'w342'),
            rating: round(($movie['vote_average'] ?? 0) / 2, 1),
            voteCount: $movie['vote_count'] ?? 0,
            character: $entries->pluck('character')->first(fn (?string $character): bool => filled($character)),
            // Cast entries have a character, crew entries a job ("Director", "Screenplay", …).
            roles: $entries
                ->map(fn (array $entry): string => array_key_exists('character', $entry) ? 'Actor' : ($entry['job'] ?? ''))
                ->filter()
                ->unique()
                ->values()
                ->all(),
        );
    }

    private function imageUrl(?string $path, string $size): ?string
    {
        return $path ? config('services.tmdb.image_url')."/{$size}{$path}" : null;
    }
}
