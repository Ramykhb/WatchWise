<?php

namespace App\Admin\Services;

use App\Admin\DTOs\Requests\UpdateListVisibilityData;
use App\Admin\DTOs\Responses\AdminListDetailResponse;
use App\Admin\DTOs\Responses\AdminListResponse;
use App\Admin\DTOs\Responses\AdminPageResponse;
use App\Admin\Exceptions\ListItemNotFound;
use App\Admin\Exceptions\ListNotFound;
use App\Admin\InterfaceServices\AdminAuditServiceInterface;
use App\Admin\InterfaceServices\AdminListServiceInterface;
use App\Admin\InterfaceServices\AdminServiceInterface;
use App\Admin\Support\AdminAction;
use App\Models\MovieList;
use App\Models\MovieListItem;
use App\Models\User;
use App\Movie\InterfaceServices\MovieCatalogServiceInterface;
use App\MovieList\DTOs\Requests\ListFilmData;
use App\MovieList\DTOs\Requests\StaffListQuery;
use App\MovieList\InterfaceServices\MovieListServiceInterface;
use App\Notification\InterfaceServices\NotificationServiceInterface;
use App\Notification\Support\ListRestriction;
use App\Report\InterfaceServices\ReportServiceInterface;
use App\Report\Support\ReportStatus;
use App\Report\Support\ReportTargetType;
use App\Support\MediaType;
use Illuminate\Support\Facades\DB;

class AdminListService implements AdminListServiceInterface
{
    /** How many titles the detail shows; `items_count` always has the full number. */
    private const ITEM_LIMIT = 100;

    public function __construct(
        private readonly MovieListServiceInterface $lists,
        private readonly ReportServiceInterface $reports,
        private readonly MovieCatalogServiceInterface $catalog,
        private readonly AdminAuditServiceInterface $audit,
        private readonly NotificationServiceInterface $notifications,
        private readonly AdminServiceInterface $admin,
    ) {}

    public function lists(StaffListQuery $query): AdminPageResponse
    {
        $paginator = $this->lists->paginateForStaff($query);
        $openReports = $this->reports->openCountsFor(
            ReportTargetType::MovieList,
            array_map(fn (MovieList $list): int => $list->id, $paginator->items()),
        );

        return new AdminPageResponse(
            array_map(fn (MovieList $list): AdminListResponse => AdminListResponse::fromModel($list, $openReports[$list->id] ?? 0), $paginator->items()),
            $paginator->currentPage(),
            $paginator->lastPage(),
            $paginator->perPage(),
            $paginator->total(),
            $this->lists->staffTotals(),
        );
    }

    public function list(MovieList $list): AdminListDetailResponse
    {
        $detail = $this->lists->staffDetail($list, self::ITEM_LIMIT);
        $films = $this->catalog->summaries(
            $detail->items
                ->filter(fn (MovieListItem $item): bool => ($item->media_type ?? MediaType::MOVIE) === MediaType::MOVIE)
                ->pluck('tmdb_id')
                ->unique()
                ->values()
                ->all(),
        );

        return AdminListDetailResponse::fromModel($detail, $films, $this->reports->openFor(ReportTargetType::MovieList, $list->id));
    }

    public function makePrivate(User $staff, MovieList $list, UpdateListVisibilityData $data): AdminListResponse
    {
        $list->loadMissing('user');

        $changed = DB::transaction(function () use ($staff, $list, $data): bool {
            $current = $this->lists->lockForUpdate($list) ?? throw new ListNotFound;
            $before = $current->is_public ? 'public' : 'private';

            if (! $this->lists->makePrivate($current)) {
                return false;
            }

            $this->audit->record(
                $staff,
                AdminAction::ListMadePrivate,
                $list->id,
                $this->label($list),
                ['visibility' => ['from' => $before, 'to' => $data->visibility], 'reports_resolved' => $this->closeReports($staff, $list)],
            );

            return true;
        });

        if ($changed) {
            $this->admin->forgetStats();
            $this->tellOwner($staff, $list, ListRestriction::MadePrivate);
        }

        return $this->row($list);
    }

    public function removeItem(User $staff, MovieList $list, ListFilmData $film): AdminListResponse
    {
        $list->loadMissing('user');

        DB::transaction(function () use ($staff, $list, $film): void {
            if (! $this->lists->removeItem($list, $film)) {
                throw new ListItemNotFound;
            }

            $this->audit->record(
                $staff,
                AdminAction::ListItemRemoved,
                $list->id,
                $this->label($list),
                [
                    'tmdb_id' => ['from' => $film->tmdbId, 'to' => null],
                    'media_type' => ['from' => $film->mediaType, 'to' => null],
                    'reports_resolved' => $this->closeReports($staff, $list),
                ],
            );
        });

        $this->admin->forgetStats();
        $this->tellOwner($staff, $list, ListRestriction::ItemRemoved, ['tmdb_id' => $film->tmdbId, 'media_type' => $film->mediaType]);

        return $this->row($list);
    }

    public function delete(User $staff, MovieList $list): void
    {
        $list->loadMissing('user');

        DB::transaction(function () use ($staff, $list): void {
            if (! $this->lists->delete($list)) {
                throw new ListNotFound;
            }

            $this->audit->record(
                $staff,
                AdminAction::ListDeleted,
                $list->id,
                $this->label($list),
                ['reports_resolved' => $this->closeReports($staff, $list)],
            );
        });

        $this->admin->forgetStats();
        $this->tellOwner($staff, $list, ListRestriction::Deleted);
    }

    /** The staff acted on the list itself, so every open report against it is resolved by them. Returns how many it closed. */
    private function closeReports(User $staff, MovieList $list): int
    {
        return $this->reports->resolveForTarget(ReportTargetType::MovieList, $list->id, $staff, ReportStatus::Resolved->value);
    }

    /**
     * Tells the owner what staff did to their list. Called after the transaction committed; a failure to notify
     * is reported and never undoes or fails the action. Nobody is told about their own action.
     *
     * @param  array{tmdb_id: int, media_type: string}|null  $film
     */
    private function tellOwner(User $staff, MovieList $list, ListRestriction $restriction, ?array $film = null): void
    {
        if ($list->user_id === $staff->id) {
            return;
        }

        rescue(fn () => $this->notifications->listRestricted($list, $restriction, $film));
    }

    private function row(MovieList $list): AdminListResponse
    {
        $row = $this->lists->staffRow($list);
        $openReports = $this->reports->openCountsFor(ReportTargetType::MovieList, [$row->id]);

        return AdminListResponse::fromModel($row, $openReports[$row->id] ?? 0);
    }

    /** The log keeps a list as its title and its owner's username, so the entry stays readable after the list is gone. */
    private function label(MovieList $list): string
    {
        $owner = $list->user;

        return sprintf('List "%s" by %s', $list->title, $owner === null ? 'unknown' : ($owner->username ?? $owner->name));
    }
}
