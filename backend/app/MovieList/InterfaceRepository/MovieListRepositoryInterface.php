<?php

namespace App\MovieList\InterfaceRepository;

use App\Models\MovieList;
use App\Models\User;
use App\MovieList\DTOs\Requests\ListFilmData;
use App\MovieList\DTOs\Requests\MovieListSearchQuery;
use App\MovieList\DTOs\Requests\StaffListQuery;
use App\Support\HiddenMovies;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface MovieListRepositoryInterface
{
    /**
     * Lists in the query's scope, newest activity first, with their owner, films and film count. Hidden movies
     * are left out of the films and the count.
     *
     * @return Collection<int, MovieList>
     */
    public function search(MovieListSearchQuery $query, User $viewer, HiddenMovies $hidden): Collection;

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

    /** Deletes the list (its films go with it) and says whether this call removed a row: false when it was already gone. */
    public function delete(MovieList $list): bool;

    /** A fresh copy of the list, row-locked until the surrounding transaction ends (call inside one); null when it no longer exists. */
    public function lockForUpdate(MovieList $list): ?MovieList;

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

    /** Loads the owner, the films and the film count, leaving the hidden movies out of both. */
    public function loadDetail(MovieList $list, HiddenMovies $hidden): MovieList;

    /**
     * Every member's lists whatever their visibility, newest first, then by id, with the owner loaded and the
     * number of films (hidden movies included) as `items_count`.
     *
     * @return LengthAwarePaginator<int, MovieList>
     */
    public function paginateForStaff(StaffListQuery $query): LengthAwarePaginator;

    /**
     * Whole-table counts for staff: all lists, public ones, private ones and those with an open report.
     *
     * @return array{total: int, public: int, private: int, reported: int}
     */
    public function staffTotals(): array;

    /** Reloads the list from the database, then loads the owner and the number of films (hidden movies included) as `items_count`, without the films themselves. */
    public function loadStaffRow(MovieList $list): MovieList;

    /** Loads the owner, the first `$itemLimit` films in position order (hidden movies included) and the full film count as `items_count`. */
    public function loadStaffDetail(MovieList $list, int $itemLimit): MovieList;

    /**
     * Makes the list private in one conditional update, so of two racing calls only one changes it. The model is
     * reloaded either way, so it shows the list as it is now. False when it was private already.
     */
    public function makePrivate(MovieList $list): bool;

    /** How many lists the user owns, public or not. */
    public function countOwnedBy(User $user): int;
}
