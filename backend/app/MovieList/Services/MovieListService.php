<?php

namespace App\MovieList\Services;

use App\ActivityFeed\Services\ActivityFeedService;
use App\Models\MovieList;
use App\Models\User;
use App\MovieList\DTOs\Requests\CreateMovieListData;
use App\MovieList\DTOs\Requests\MovieListSearchQuery;
use App\MovieList\DTOs\Requests\UpdateMovieListData;
use App\MovieList\InterfaceRepository\MovieListRepositoryInterface;
use App\MovieList\InterfaceServices\MovieListServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class MovieListService implements MovieListServiceInterface
{
    public function __construct(
        private readonly MovieListRepositoryInterface $lists,
        private readonly ActivityFeedService $activity,
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
            $this->lists->syncItems($list, $data->tmdbIds);

            return $list;
        });

        $this->activity->record($owner, 'list_created', $list, null, $list->is_public);

        return $this->detail($list);
    }

    public function detail(MovieList $list): MovieList
    {
        return $this->lists->loadDetail($list);
    }

    public function update(MovieList $list, UpdateMovieListData $data, User $editor): MovieList
    {
        DB::transaction(function () use ($list, $data, $editor) {
            $this->lists->update($list, $data->attributes);

            if ($data->tmdbIds !== null) {
                foreach ($this->lists->syncItems($list, $data->tmdbIds) as $tmdbId) {
                    $this->activity->record($editor, 'list_movie_added', $list, $tmdbId, $list->is_public);
                }
            }
        });

        return $this->detail($list);
    }

    public function delete(MovieList $list): void
    {
        $this->lists->delete($list);
    }
}
