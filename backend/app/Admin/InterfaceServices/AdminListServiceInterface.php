<?php

namespace App\Admin\InterfaceServices;

use App\Admin\DTOs\Requests\UpdateListVisibilityData;
use App\Admin\DTOs\Responses\AdminListDetailResponse;
use App\Admin\DTOs\Responses\AdminListResponse;
use App\Admin\DTOs\Responses\AdminPageResponse;
use App\Admin\Exceptions\ListItemNotFound;
use App\Admin\Exceptions\ListNotFound;
use App\Models\MovieList;
use App\Models\User;
use App\MovieList\DTOs\Requests\ListFilmData;
use App\MovieList\DTOs\Requests\StaffListQuery;

/**
 * Staff's view of every member's movie lists, and the three things they may do to one: make it private, remove
 * a title from it, delete it. Each change closes the list's open reports as resolved by the staff member, is
 * recorded in the audit log in the same transaction, and tells the owner once it has committed.
 */
interface AdminListServiceInterface
{
    /** One page of lists whatever their visibility or their owner's, newest first; the page's summary counts all lists. */
    public function lists(StaffListQuery $query): AdminPageResponse;

    /** The list with its full description, its first 100 titles and its open reports; titles come from the local catalogue only. */
    public function list(MovieList $list): AdminListDetailResponse;

    /**
     * Moves the list to the given visibility, which staff may only set to private. A list that is private already
     * is left alone, also when another request made it private first: nothing is recorded, no report is closed
     * and nobody is told, and the answer shows the list as it is now.
     *
     * @throws ListNotFound when the list was deleted meanwhile
     */
    public function makePrivate(User $staff, MovieList $list, UpdateListVisibilityData $data): AdminListResponse;

    /**
     * @throws ListItemNotFound when the title is not in the list, which includes the list having been deleted meanwhile
     */
    public function removeItem(User $staff, MovieList $list, ListFilmData $film): AdminListResponse;

    /**
     * @throws ListNotFound when another request deleted the list first: nothing is recorded, closed or sent
     */
    public function delete(User $staff, MovieList $list): void;
}
