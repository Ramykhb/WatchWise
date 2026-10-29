<?php

namespace App\MovieList\InterfaceServices;

use App\Models\MovieList;
use App\Models\User;
use App\MovieList\DTOs\Requests\CreateMovieListData;
use App\MovieList\DTOs\Requests\ListFilmData;
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

    /** Replaces the movies and TV shows in the given order when `films` is set (add, remove, reorder). */
    public function update(MovieList $list, UpdateMovieListData $data): MovieList;

    public function delete(MovieList $list): void;

    /** Adds one movie or TV show at the end (the Movie page's "Add to list"); does nothing if it is already there. */
    public function addMovie(MovieList $list, ListFilmData $film): MovieList;

    public function removeMovie(MovieList $list, ListFilmData $film): MovieList;
}
