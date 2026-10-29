<?php

namespace App\MovieList\Services;

use App\Models\MovieList;
use App\Models\User;
use App\Movie\InterfaceServices\MovieCatalogServiceInterface;
use App\MovieList\DTOs\Requests\CreateMovieListData;
use App\MovieList\DTOs\Requests\ListFilmData;
use App\MovieList\DTOs\Requests\MovieListSearchQuery;
use App\MovieList\DTOs\Requests\UpdateMovieListData;
use App\MovieList\InterfaceRepository\MovieListRepositoryInterface;
use App\MovieList\InterfaceServices\MovieListServiceInterface;
use App\Support\MediaType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class MovieListService implements MovieListServiceInterface
{
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
        $list = DB::transaction(function () use ($data, $owner) {
            $list = $this->lists->create([
                'user_id' => $owner->id,
                'title' => $data->title,
                'description' => $data->description,
                'is_public' => $data->isPublic,
            ]);
            $this->lists->syncItems($list, $data->films);

            return $list;
        });
        $this->rememberAll($data->films);

        return $this->detail($list);
    }

    public function detail(MovieList $list): MovieList
    {
        return $this->lists->loadDetail($list);
    }

    public function update(MovieList $list, UpdateMovieListData $data): MovieList
    {
        DB::transaction(function () use ($list, $data) {
            $this->lists->update($list, $data->attributes);

            if ($data->films !== null) {
                $this->lists->syncItems($list, $data->films);
            }
        });
        $this->rememberAll($data->films ?? []);

        return $this->detail($list);
    }

    public function delete(MovieList $list): void
    {
        $this->lists->delete($list);
    }

    public function addMovie(MovieList $list, ListFilmData $film): MovieList
    {
        $this->lists->addItem($list, $film);
        $this->rememberAll([$film]);

        return $this->detail($list);
    }

    public function removeMovie(MovieList $list, ListFilmData $film): MovieList
    {
        $this->lists->removeItem($list, $film);

        return $this->detail($list);
    }

    /**
     * The local catalogue (admin "Movie data") only holds movies, so TV shows are skipped.
     *
     * @param  list<ListFilmData>  $films
     */
    private function rememberAll(array $films): void
    {
        foreach ($films as $film) {
            if ($film->mediaType === MediaType::MOVIE) {
                $this->catalog->remember($film->tmdbId);
            }
        }
    }
}
