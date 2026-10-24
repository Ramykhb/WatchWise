<?php

namespace App\MovieList\InterfaceServices;

use App\Models\MovieList;
use App\Models\User;
use App\MovieList\DTOs\Requests\CreateMovieListData;
use App\MovieList\DTOs\Requests\MovieListSearchQuery;
use App\MovieList\DTOs\Requests\UpdateMovieListData;
use Illuminate\Database\Eloquent\Collection;

interface MovieListServiceInterface
{
    /**
     * @return Collection<int, MovieList>
     */
    public function search(MovieListSearchQuery $query, User $viewer): Collection;

    public function create(CreateMovieListData $data, User $owner): MovieList;

    /** The list with its owner, films and film count loaded. */
    public function detail(MovieList $list): MovieList;

    /** Replaces the films in the given order when `tmdbIds` is set (add, remove, reorder). */
    public function update(MovieList $list, UpdateMovieListData $data, User $editor): MovieList;

    public function delete(MovieList $list): void;
}
