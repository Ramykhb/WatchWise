<?php

namespace App\Admin\Services;

use App\Admin\DTOs\Requests\AdminGenreQuery;
use App\Admin\DTOs\Requests\UpdateGenreOverrideData;
use App\Admin\DTOs\Responses\AdminGenreResponse;
use App\Admin\InterfaceServices\AdminAuditServiceInterface;
use App\Admin\InterfaceServices\AdminGenreServiceInterface;
use App\Admin\Support\AdminAction;
use App\Genre\DTOs\Responses\GenreResponse;
use App\Genre\Exceptions\GenreNotFound;
use App\Genre\InterfaceServices\GenreServiceInterface;
use App\Models\GenreOverride;
use App\Models\User;
use App\Movie\InterfaceServices\MovieCatalogServiceInterface;
use App\Support\Exceptions\TmdbUnavailable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class AdminGenreService implements AdminGenreServiceInterface
{
    /** What an override holds when it changes nothing. */
    private const DEFAULT_VALUES = ['name' => null, 'is_hidden' => false, 'position' => null];

    public function __construct(
        private readonly GenreServiceInterface $genres,
        private readonly MovieCatalogServiceInterface $catalog,
        private readonly AdminAuditServiceInterface $audit,
    ) {}

    public function list(AdminGenreQuery $query): array
    {
        $tmdb = $this->genres->tmdb($query->type);
        $overrides = $this->genres->overrides($query->type);
        $counts = $this->filmCounts($query->type);

        return array_map(
            fn (GenreResponse $genre): AdminGenreResponse => AdminGenreResponse::merge($genre, $overrides->get($genre->id), $counts[$genre->id] ?? 0),
            $tmdb,
        );
    }

    public function update(User $admin, string $type, int $tmdbId, UpdateGenreOverrideData $data): AdminGenreResponse
    {
        $genre = $this->tmdbGenre($type, $tmdbId);

        try {
            $override = $this->applyUpdate($admin, $type, $tmdbId, $data, $genre);
        } catch (UniqueConstraintViolationException) {
            $override = $this->applyUpdate($admin, $type, $tmdbId, $data, $genre);
        }

        return AdminGenreResponse::merge($genre, $override, $this->filmCounts($type)[$tmdbId] ?? 0);
    }

    public function reset(User $admin, string $type, int $tmdbId): void
    {
        if ($this->genres->override($type, $tmdbId) === null) {
            return;
        }

        $label = $this->label($type, $this->tmdbNameOrFallback($type, $tmdbId));

        DB::transaction(function () use ($admin, $type, $tmdbId, $label): void {
            $current = $this->genres->override($type, $tmdbId, lockForUpdate: true);

            if ($current === null) {
                return;
            }

            $this->genres->removeOverride($current);
            $this->audit->record(
                $admin,
                AdminAction::GenreReset,
                $tmdbId,
                $label,
                $this->changesBetween($this->values($current), self::DEFAULT_VALUES),
            );
        });
    }

    /**
     * One attempt at the update, in its own transaction. Two requests creating the first override of the same genre
     * can both read no row and both insert; the loser's insert violates the unique index, its transaction is
     * rolled back and the caller runs this once more, now finding the winner's row.
     *
     * @param  'movie'|'tv'  $type
     *
     * @throws UniqueConstraintViolationException
     */
    private function applyUpdate(User $admin, string $type, int $tmdbId, UpdateGenreOverrideData $data, GenreResponse $genre): ?GenreOverride
    {
        return DB::transaction(function () use ($admin, $type, $tmdbId, $data, $genre): ?GenreOverride {
            $current = $this->genres->override($type, $tmdbId, lockForUpdate: true);
            $before = $this->values($current);
            $after = [...$before, ...$data->changes()];
            $changes = $this->changesBetween($before, $after);

            if ($changes === []) {
                return $current;
            }

            $saved = $this->genres->saveOverride($type, $tmdbId, $after, $admin->id);
            $this->audit->record($admin, AdminAction::GenreUpdated, $tmdbId, $this->label($type, $genre->name), $changes);

            return $saved;
        });
    }

    /**
     * @param  'movie'|'tv'  $type
     *
     * @throws GenreNotFound
     * @throws TmdbUnavailable
     */
    private function tmdbGenre(string $type, int $tmdbId): GenreResponse
    {
        foreach ($this->genres->tmdb($type) as $genre) {
            if ($genre->id === $tmdbId) {
                return $genre;
            }
        }

        throw new GenreNotFound($type, $tmdbId);
    }

    /**
     * A reset must work for a leftover override whose genre TMDB no longer lists, or while TMDB is down, so it
     * falls back to a generic name instead of failing.
     */
    private function tmdbNameOrFallback(string $type, int $tmdbId): string
    {
        try {
            return $this->tmdbGenre($type, $tmdbId)->name;
        } catch (GenreNotFound|TmdbUnavailable) {
            return "Genre {$tmdbId}";
        }
    }

    /**
     * Films in the local catalogue carry movie genre ids only, so a tv genre counts no films.
     *
     * @return array<int, int>
     */
    private function filmCounts(string $type): array
    {
        return $type === 'movie' ? $this->catalog->visibleGenreCounts() : [];
    }

    /** A movie genre and a tv genre can share an id, so the log tells tv ones apart by name. */
    private function label(string $type, string $tmdbName): string
    {
        return $type === 'tv' ? "{$tmdbName} (TV)" : $tmdbName;
    }

    /**
     * @return array{name: string|null, is_hidden: bool, position: int|null}
     */
    private function values(?GenreOverride $override): array
    {
        return $override === null
            ? self::DEFAULT_VALUES
            : ['name' => $override->name, 'is_hidden' => (bool) $override->is_hidden, 'position' => $override->position];
    }

    /**
     * @param  array{name: string|null, is_hidden: bool, position: int|null}  $before
     * @param  array{name: string|null, is_hidden: bool, position: int|null}  $after
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function changesBetween(array $before, array $after): array
    {
        $changes = [];

        foreach ($after as $field => $value) {
            if ($before[$field] !== $value) {
                $changes[$field] = ['from' => $before[$field], 'to' => $value];
            }
        }

        return $changes;
    }
}
