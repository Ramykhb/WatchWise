<?php

namespace App\Genre\Services;

use App\Genre\DTOs\Responses\GenreResponse;
use App\Genre\InterfaceRepository\GenreOverrideRepositoryInterface;
use App\Genre\InterfaceRepository\GenreRepositoryInterface;
use App\Genre\InterfaceServices\GenreServiceInterface;
use App\Models\GenreOverride;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class GenreService implements GenreServiceInterface
{
    public function __construct(
        private readonly GenreRepositoryInterface $genres,
        private readonly GenreOverrideRepositoryInterface $overrides,
    ) {}

    public function all(string $type = 'movie'): array
    {
        $overrides = $this->overrides->forType($type);

        return array_values(array_filter(
            $this->ordered($this->tmdb($type), $overrides),
            fn (GenreResponse $genre): bool => ! ($overrides->get($genre->id)?->is_hidden ?? false),
        ));
    }

    public function allIncludingHidden(string $type = 'movie'): array
    {
        return $this->ordered($this->tmdb($type), $this->overrides->forType($type));
    }

    public function tmdb(string $type = 'movie'): array
    {
        $genres = Cache::remember("tmdb.{$type}_genres", now()->addDay(), fn () => $this->genres->all($type));

        return array_map(
            fn (array $genre) => new GenreResponse($genre['id'], $genre['name']),
            array_values($genres),
        );
    }

    public function overrides(string $type): Collection
    {
        return $this->overrides->forType($type);
    }

    public function override(string $type, int $tmdbId, bool $lockForUpdate = false): ?GenreOverride
    {
        return $this->overrides->find($type, $tmdbId, $lockForUpdate);
    }

    public function saveOverride(string $type, int $tmdbId, array $values, ?int $updatedBy): ?GenreOverride
    {
        if ($values['name'] === null && ! $values['is_hidden'] && $values['position'] === null) {
            $existing = $this->overrides->find($type, $tmdbId);

            if ($existing !== null) {
                $this->overrides->delete($existing);
            }

            return null;
        }

        return $this->overrides->save($type, $tmdbId, $values, $updatedBy);
    }

    public function removeOverride(GenreOverride $override): void
    {
        $this->overrides->delete($override);
    }

    /**
     * Renames each genre that has a display name, then puts the ones with a position first (by position, ties in
     * TMDB's order) and the rest after them in TMDB's order. A rename never moves a genre, so with no positions
     * the list comes out as TMDB sent it.
     *
     * @param  list<GenreResponse>  $genres
     * @param  Collection<int, GenreOverride>  $overrides
     * @return list<GenreResponse>
     */
    private function ordered(array $genres, Collection $overrides): array
    {
        $rows = [];

        foreach ($genres as $index => $genre) {
            $override = $overrides->get($genre->id);

            $rows[] = [
                'genre' => new GenreResponse($genre->id, $override?->name ?? $genre->name),
                'position' => $override?->position,
                'index' => $index,
            ];
        }

        usort($rows, fn (array $a, array $b): int => match (true) {
            $a['position'] !== null && $b['position'] !== null => [$a['position'], $a['index']] <=> [$b['position'], $b['index']],
            $a['position'] !== null => -1,
            $b['position'] !== null => 1,
            default => $a['index'] <=> $b['index'],
        });

        return array_column($rows, 'genre');
    }
}
