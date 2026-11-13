<?php

namespace App\MovieList\InterfaceServices;

use App\Models\MovieList;
use App\Models\User;
use App\MovieList\DTOs\Requests\CreateMovieListData;
use App\MovieList\DTOs\Requests\ListFilmData;
use App\MovieList\DTOs\Requests\MovieListSearchQuery;
use App\MovieList\DTOs\Requests\StaffListQuery;
use App\MovieList\DTOs\Requests\UpdateMovieListData;
use App\Report\DTOs\Requests\ReportContentData;
use App\Report\DTOs\Responses\ReportContentResponse;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface MovieListServiceInterface
{
    /**
     * @return Collection<int, MovieList>
     */
    public function search(MovieListSearchQuery $query, User $viewer): Collection;

    /** Creates the list with its films; newly listed movies join the local catalogue after the response is sent. */
    public function create(CreateMovieListData $data, User $owner): MovieList;

    /** The list with its owner, films and film count loaded, for the person who changes it. */
    public function detail(MovieList $list): MovieList;

    /** The list as the viewer sees it: other members do not see the movies admins hid from browsing. */
    public function show(MovieList $list, User $viewer): MovieList;

    /**
     * Replaces the movies and TV shows in the given order when `films` is set (add, remove, reorder). The list's
     * updated_at only moves when a field, the films or their order actually changed.
     */
    public function update(MovieList $list, UpdateMovieListData $data): MovieList;

    /** Deletes the list with its films. False when it was already gone (another request deleted it first). */
    public function delete(MovieList $list): bool;

    /** Adds one movie or TV show at the end (the Movie page's "Add to list"); does nothing if it is already there. */
    public function addMovie(MovieList $list, ListFilmData $film): MovieList;

    public function removeMovie(MovieList $list, ListFilmData $film): MovieList;

    /**
     * Removes one title and says whether it was there. Unlike removeMovie() it does not reload the list, for
     * callers (staff moderation) that only need to know if anything was removed.
     */
    public function removeItem(MovieList $list, ListFilmData $film): bool;

    /**
     * Every member's lists for staff: whatever their visibility, with hidden movies counted, newest first.
     *
     * @return LengthAwarePaginator<int, MovieList>
     */
    public function paginateForStaff(StaffListQuery $query): LengthAwarePaginator;

    /**
     * @return array{total: int, public: int, private: int, reported: int} counts of all lists, whatever the filters
     */
    public function staffTotals(): array;

    /** The list as a row of the staff table: its owner and the number of titles (hidden movies included) as `items_count`. */
    public function staffRow(MovieList $list): MovieList;

    /** The list as staff see it: its owner, the first `$itemLimit` titles in order (hidden movies included) and the full count as `items_count`. */
    public function staffDetail(MovieList $list, int $itemLimit): MovieList;

    /** A fresh copy of the list, row-locked until the surrounding transaction ends (call inside one); null when it no longer exists. */
    public function lockForUpdate(MovieList $list): ?MovieList;

    /** Makes the list private (the only restriction staff apply). False, changing nothing, when it already was. */
    public function makePrivate(MovieList $list): bool;

    /** Reports another member's list (the caller has checked the reporter can see it); reporting it twice files one report. */
    public function report(MovieList $list, User $reporter, ReportContentData $data): ReportContentResponse;

    /** How many lists the member owns, public or private. */
    public function countOwnedBy(User $user): int;

    /** How many lists, public or private, were created from `$from` (inclusive) until `$until` (exclusive). */
    public function countCreatedBetween(CarbonInterface $from, CarbonInterface $until): int;
}
