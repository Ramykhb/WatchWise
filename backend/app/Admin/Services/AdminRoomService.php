<?php

namespace App\Admin\Services;

use App\Admin\DTOs\Requests\AdminRoomMessagesQuery;
use App\Admin\DTOs\Requests\AdminRoomQuery;
use App\Admin\DTOs\Requests\UpdateRoomData;
use App\Admin\DTOs\Responses\AdminPageResponse;
use App\Admin\DTOs\Responses\AdminRoomDetailResponse;
use App\Admin\DTOs\Responses\AdminRoomMessageResponse;
use App\Admin\DTOs\Responses\AdminRoomResponse;
use App\Admin\Exceptions\RoomNotReviewable;
use App\Admin\InterfaceRepository\AdminRoomRepositoryInterface;
use App\Admin\InterfaceServices\AdminAuditServiceInterface;
use App\Admin\InterfaceServices\AdminRoomServiceInterface;
use App\Admin\Support\AdminAction;
use App\Models\DiscussionRoom;
use App\Models\Message;
use App\Models\User;
use App\Movie\InterfaceServices\MovieCatalogServiceInterface;
use App\Notification\InterfaceServices\NotificationServiceInterface;
use App\Report\InterfaceServices\ReportServiceInterface;
use App\Report\Support\ReportTargetType;
use App\Room\InterfaceServices\RoomServiceInterface;
use App\Support\MediaType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminRoomService implements AdminRoomServiceInterface
{
    /** The most members one room detail lists. */
    private const MEMBER_LIMIT = 200;

    public function __construct(
        private readonly AdminRoomRepositoryInterface $rooms,
        private readonly RoomServiceInterface $roomService,
        private readonly ReportServiceInterface $reports,
        private readonly MovieCatalogServiceInterface $catalog,
        private readonly NotificationServiceInterface $notifications,
        private readonly AdminAuditServiceInterface $audit,
    ) {}

    public function rooms(AdminRoomQuery $query): AdminPageResponse
    {
        $paginator = $this->rooms->paginate($query);

        return (new AdminPageResponse(
            $this->rows($paginator->getCollection()),
            $paginator->currentPage(),
            $paginator->lastPage(),
            $paginator->perPage(),
            $paginator->total(),
        ))->withSummary($this->rooms->totals());
    }

    public function room(DiscussionRoom $room): AdminRoomDetailResponse
    {
        return new AdminRoomDetailResponse(
            $this->row($this->rooms->loadRow($room)),
            $this->rooms->members($room, self::MEMBER_LIMIT)->all(),
            $this->reports->openFor(ReportTargetType::Room, $room->id)->all(),
        );
    }

    public function messages(DiscussionRoom $room, AdminRoomMessagesQuery $query): AdminPageResponse
    {
        if ($room->is_private && ! $this->rooms->hasOpenReport($room)) {
            throw new RoomNotReviewable;
        }

        $paginator = $this->rooms->messages($room, $query->page, $query->perPage);
        $openReports = $this->reports->openCountsFor(ReportTargetType::Message, $paginator->getCollection()->modelKeys());

        return new AdminPageResponse(
            $paginator->getCollection()
                ->map(fn (Message $message): AdminRoomMessageResponse => AdminRoomMessageResponse::fromModel($message, $openReports[$message->id]))
                ->all(),
            $paginator->currentPage(),
            $paginator->lastPage(),
            $paginator->perPage(),
            $paginator->total(),
        );
    }

    public function deleteMessage(User $staff, DiscussionRoom $room, Message $message): void
    {
        DB::transaction(function () use ($staff, $room, $message): void {
            $this->reports->resolveForTarget(ReportTargetType::Message, $message->id, $staff, 'resolved');
            $this->audit->record(
                $staff,
                AdminAction::MessageDeleted,
                $message->id,
                "Message #{$message->id} in room {$room->id}",
                ['author_id' => $message->user_id, 'room_id' => $room->id],
            );
            $this->roomService->deleteMessage($room, $message);
        });

        if ((int) $message->user_id !== $staff->id) {
            rescue(fn () => $this->notifications->messageRemoved($room, $message));
        }
    }

    public function setLocked(User $staff, DiscussionRoom $room, UpdateRoomData $data): AdminRoomResponse
    {
        $changed = DB::transaction(function () use ($staff, $room, $data): bool {
            $changed = $this->roomService->setLocked($room, $data->isLocked);

            if ($changed) {
                $this->audit->record(
                    $staff,
                    $data->isLocked ? AdminAction::RoomLocked : AdminAction::RoomUnlocked,
                    $room->id,
                    $this->roomLabel($room),
                    ['is_locked' => ['from' => ! $data->isLocked, 'to' => $data->isLocked]],
                );
            }

            return $changed;
        });

        if ($changed && (int) $room->created_by !== $staff->id) {
            rescue(fn () => $this->notifications->roomLockChanged($room, $data->isLocked));
        }

        return $this->row($this->rooms->loadRow($room));
    }

    public function deleteRoom(User $staff, DiscussionRoom $room): void
    {
        DB::transaction(function () use ($staff, $room): void {
            $reportsResolved = $this->reports->resolveForTarget(ReportTargetType::Room, $room->id, $staff, 'resolved')
                + $this->reports->resolveForTargets(ReportTargetType::Message, $this->rooms->messageIds($room), $staff, 'resolved');

            $this->audit->record(
                $staff,
                AdminAction::RoomDeleted,
                $room->id,
                $this->roomLabel($room),
                ['creator_id' => $room->created_by, 'reports_resolved' => $reportsResolved],
            );
            $this->roomService->deleteByStaff($room, $staff);
        });
    }

    /** The titles a room is kept under in the audit log, which stay readable once the room is gone. */
    private function roomLabel(DiscussionRoom $room): string
    {
        return 'Room "'.Str::limit($room->name, 200, '').'"';
    }

    private function row(DiscussionRoom $room): AdminRoomResponse
    {
        return $this->rows(new Collection([$room]))[0];
    }

    /**
     * The rows of rooms loaded as AdminRoomRepositoryInterface::paginate() does, in a constant number of queries:
     * one for the films, one for the reports on the rooms and one for the reports on their messages.
     *
     * @param  Collection<int, DiscussionRoom>  $rooms
     * @return list<AdminRoomResponse>
     */
    private function rows(Collection $rooms): array
    {
        $ids = $rooms->modelKeys();
        $films = $this->catalog->summaries(
            $rooms->where('media_type', MediaType::MOVIE)->pluck('tmdb_id')->map(fn ($id): int => (int) $id)->unique()->values()->all(),
        );
        $roomReports = $this->reports->openCountsFor(ReportTargetType::Room, $ids);
        $messageReports = $this->rooms->openMessageReportCounts($ids);

        return $rooms
            ->map(fn (DiscussionRoom $room): AdminRoomResponse => AdminRoomResponse::fromModel(
                $room,
                $room->media_type === MediaType::MOVIE ? $films->get((int) $room->tmdb_id)?->title : null,
                $roomReports[$room->id],
                $messageReports[$room->id] ?? 0,
            ))
            ->all();
    }
}
