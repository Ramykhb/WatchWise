<?php

namespace App\MovieList\InterfaceRepository;

use App\Models\MovieList;
use App\Models\User;
use App\MovieList\DTOs\ListSyncResult;
use App\MovieList\DTOs\Requests\CreateMovieListData;
use App\MovieList\DTOs\Requests\ListFilmData;
use App\MovieList\DTOs\Requests\MovieListSearchQuery;
use App\MovieList\DTOs\Requests\StaffListQuery;
use App\Support\HiddenMovies;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;

interface MovieListRepositoryInterface
{
    /**
     * One page of the lists in the query's scope, newest activity first (then newest id), with their owner, films
     * and film count. Simple paging: it knows whether another page follows, without counting them all.
     *
     * @return Paginator<int, MovieList>
     */
    public function search(MovieListSearchQuery $query, User $viewer, HiddenMovies $hidden): Paginator;

    /** Creates the list (without its films) for the owner. */
    public function create(CreateMovieListData $data, int $ownerId): MovieList;

    /**
     * Saves the fields; updated_at only moves when one of them changed.
     *
     * @param  array<string, mixed>  $attributes
     * @return bool whether any field changed
     */
    public function update(MovieList $list, array $attributes): bool;

    /** Deletes the list (its films go with it) and says whether this call removed a row: false when it was already gone. */
    public function delete(MovieList $list): bool;

    /** A fresh copy of the list, row-locked until the surrounding transaction ends (call inside one); null when it no longer exists. */
    public function lockForUpdate(MovieList $list): ?MovieList;
    /** Reloads the list and locks its row until the transaction ends; all writes must hold this lock. */
    public function lock(MovieList $list): MovieList;


    /**
     * Makes the list hold exactly these movies and TV shows in this order (positions from 0) in a few bulk
     * queries. Titles that stay keep their note and the date they were added. Bumps updated_at when titles
     * were added or removed or their order changed, not when the list already matched.
     *
     * @param  list<ListFilmData>  $films
     */
    public function syncItems(MovieList $list, array $films): ListSyncResult;

    /** Adds the title after the last one (position 0 on an empty list); false when it is already there. */
    public function addItem(MovieList $list, ListFilmData $film): bool;

    /** Removes the title; false when it was not in the list. */
    public function removeItem(MovieList $list, ListFilmData $film): bool;

    /** Loads the owner (unless already loaded), the films and the film count, leaving the hidden movies out of both. */
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

    /** How many lists were created from `$from` (inclusive) until `$until` (exclusive). */
    public function countCreatedBetween(CarbonInterface $from, CarbonInterface $until): int;
}
