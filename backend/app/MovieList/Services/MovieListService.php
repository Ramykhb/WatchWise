<?php

namespace App\MovieList\Services;

use App\Models\MovieList;
use App\Models\User;
use App\Movie\InterfaceServices\MovieCatalogServiceInterface;
use App\MovieList\DTOs\Requests\CreateMovieListData;
use App\MovieList\DTOs\Requests\ListFilmData;
use App\MovieList\DTOs\Requests\MovieListSearchQuery;
use App\MovieList\DTOs\Requests\StaffListQuery;
use App\MovieList\DTOs\Requests\UpdateMovieListData;
use App\MovieList\InterfaceRepository\MovieListRepositoryInterface;
use App\MovieList\InterfaceServices\MovieListServiceInterface;
use App\Report\DTOs\Requests\ReportContentData;
use App\Report\DTOs\Responses\ReportContentResponse;
use App\Report\InterfaceServices\ReportServiceInterface;
use App\Report\Support\ReportTargetType;
use App\Support\HiddenMovies;
use App\Support\MediaType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class MovieListService implements MovieListServiceInterface
{
    public function __construct(
        private readonly MovieListRepositoryInterface $lists,
        private readonly MovieCatalogServiceInterface $catalog,
        private readonly ReportServiceInterface $reports,
    ) {}

    public function search(MovieListSearchQuery $query, User $viewer): Collection
    {
        // Hidden movies stay in the viewer's own lists; other members' lists leave them out.
        $hidden = $query->scope === 'mine' ? HiddenMovies::none() : $this->catalog->hiddenMovies();

        return $this->lists->search($query, $viewer, $hidden);
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
        return $this->lists->loadDetail($list, HiddenMovies::none());
    }

    public function show(MovieList $list, User $viewer): MovieList
    {
        $hidden = $list->user_id === $viewer->id ? HiddenMovies::none() : $this->catalog->hiddenMovies();

        return $this->lists->loadDetail($list, $hidden);
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

    public function delete(MovieList $list): bool
    {
        return $this->lists->delete($list);
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

    public function removeItem(MovieList $list, ListFilmData $film): bool
    {
        return $this->lists->removeItem($list, $film);
    }

    public function paginateForStaff(StaffListQuery $query): LengthAwarePaginator
    {
        return $this->lists->paginateForStaff($query);
    }

    public function staffTotals(): array
    {
        return $this->lists->staffTotals();
    }

    public function staffRow(MovieList $list): MovieList
    {
        return $this->lists->loadStaffRow($list);
    }

    public function staffDetail(MovieList $list, int $itemLimit): MovieList
    {
        return $this->lists->loadStaffDetail($list, $itemLimit);
    }

    public function lockForUpdate(MovieList $list): ?MovieList
    {
        return $this->lists->lockForUpdate($list);
    }

    public function makePrivate(MovieList $list): bool
    {
        return $this->lists->makePrivate($list);
    }

    public function report(MovieList $list, User $reporter, ReportContentData $data): ReportContentResponse
    {
        return $this->reports->report($reporter, ReportTargetType::MovieList, $list->id, $data);
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
