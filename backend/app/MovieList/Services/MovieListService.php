<?php

namespace App\MovieList\Services;

use App\Models\MovieList;
use App\Models\User;
use App\Movie\InterfaceServices\MovieCatalogServiceInterface;
use App\MovieList\DTOs\Requests\CreateMovieListData;
use App\MovieList\DTOs\Requests\MovieListSearchQuery;
use App\MovieList\DTOs\Requests\UpdateMovieListData;
use App\MovieList\InterfaceRepository\MovieListRepositoryInterface;
use App\MovieList\InterfaceServices\MovieListServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class MovieListService implements MovieListServiceInterface
{
    /** The most newly added films one list save looks up on TMDB. */
    private const REMEMBER_LIMIT = 10;

    public function __construct(
        private readonly MovieListRepositoryInterface $lists,
        private readonly MovieCatalogServiceInterface $catalog,
    ) {}

    public function search(MovieListSearchQuery $query, User $viewer): Collection
    {
        return $this->lists->search($query, $viewer);
    }

    public function create(CreateMovieListData $data, User $owner): MovieList
    {
        [$list, $added] = DB::transaction(function () use ($data, $owner) {
            $list = $this->lists->create([
                'user_id' => $owner->id,
                'title' => $data->title,
                'description' => $data->description,
                'is_public' => $data->isPublic,
            ]);

            return [$list, $this->lists->syncItems($list, $data->tmdbIds)];
        });
        $this->rememberAll($added);

        return $this->detail($list);
    }

    public function detail(MovieList $list): MovieList
    {
        return $this->lists->loadDetail($list);
    }

    public function update(MovieList $list, UpdateMovieListData $data): MovieList
    {
        $added = DB::transaction(function () use ($list, $data) {
            $this->lists->update($list, $data->attributes);

            return $data->tmdbIds === null ? [] : $this->lists->syncItems($list, $data->tmdbIds);
        });
        $this->rememberAll($added);

        return $this->detail($list);
    }

    public function delete(MovieList $list): void
    {
        $this->lists->delete($list);
    }

    public function addMovie(MovieList $list, int $tmdbId): MovieList
    {
        $this->lists->addItem($list, $tmdbId);
        $this->catalog->remember($tmdbId);

        return $this->detail($list);
    }

    public function removeMovie(MovieList $list, int $tmdbId): MovieList
    {
        $this->lists->removeItem($list, $tmdbId);

        return $this->detail($list);
    }

    /**
     * Each unknown film costs a TMDB call inside the request, so a bulk save only remembers the
     * first few new films. The rest are remembered when someone rates, watchlists or adds them singly.
     *
     * @param  list<int>  $tmdbIds
     */
    private function rememberAll(array $tmdbIds): void
    {
        foreach (array_slice($tmdbIds, 0, self::REMEMBER_LIMIT) as $tmdbId) {
            $this->catalog->remember($tmdbId);
        }
    }
}
