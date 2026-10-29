<?php

namespace App\MovieList\InterfaceRepository;

use App\Models\MovieList;
use App\Models\User;
use App\MovieList\DTOs\Requests\ListFilmData;
use App\MovieList\DTOs\Requests\MovieListSearchQuery;
use Illuminate\Database\Eloquent\Collection;

interface MovieListRepositoryInterface
{
    /**
     * Lists in the query's scope, newest activity first, with their owner, films and film count.
     *
     * @return Collection<int, MovieList>
     */
    public function search(MovieListSearchQuery $query, User $viewer): Collection;

    /**
     * @param  array{user_id: int, title: string, description: string|null, is_public: bool}  $attributes
     */
    public function create(array $attributes): MovieList;

    /**
     * Saves the fields and bumps updated_at even when none of them changed.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(MovieList $list, array $attributes): void;

    public function delete(MovieList $list): void;

    /**
     * Makes the list hold exactly these movies and TV shows in this order, keeping notes of titles that stay.
     *
     * @param  list<ListFilmData>  $films
     * @return list<ListFilmData> titles that were not in the list before
     */
    public function syncItems(MovieList $list, array $films): array;

    /** Adds the title at the end of the list; false when it is already there. */
    public function addItem(MovieList $list, ListFilmData $film): bool;

    /** Removes the title; false when it was not in the list. */
    public function removeItem(MovieList $list, ListFilmData $film): bool;

    /** Loads the owner, the films and the film count. */
    public function loadDetail(MovieList $list): MovieList;
}
