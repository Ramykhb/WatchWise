<?php

namespace App\Admin\Repository;

use App\Admin\DTOs\Requests\AdminRoomQuery;
use App\Admin\InterfaceRepository\AdminRoomRepositoryInterface;
use App\Models\DiscussionRoom;
use App\Models\LocalMovie;
use App\Models\Message;
use App\Models\Report;
use App\Report\Support\ReportTargetType;
use App\Support\LikePattern;
use App\Support\MediaType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminRoomRepository implements AdminRoomRepositoryInterface
{
    /** What a room's activity is ordered by: its newest stored message, else when it was created. */
    private const ACTIVITY = 'coalesce((select max(messages.created_at) from messages where messages.room_id = discussion_rooms.id), discussion_rooms.created_at)';

    public function paginate(AdminRoomQuery $query): LengthAwarePaginator
    {
        return $this->rows()
            ->when($query->search !== '', fn (Builder $rooms) => $this->matching($rooms, LikePattern::contains($query->search)))
            ->when($query->visibility !== null, fn (Builder $rooms) => $rooms->where('discussion_rooms.is_private', $query->visibility === 'private'))
            ->when($query->creatorId !== null, fn (Builder $rooms) => $rooms->where('discussion_rooms.created_by', $query->creatorId))
            ->when($query->tmdbId !== null, fn (Builder $rooms) => $rooms->where('discussion_rooms.tmdb_id', $query->tmdbId))
            ->when($query->locked !== null, fn (Builder $rooms) => $rooms->where('discussion_rooms.is_locked', $query->locked))
            ->when($query->reported !== null, fn (Builder $rooms) => $query->reported
                ? $this->withOpenReport($rooms)
                : $rooms->whereNot(fn (Builder $reported) => $this->withOpenReport($reported)))
            ->orderByRaw(self::ACTIVITY.' desc')
            ->orderByDesc('discussion_rooms.id')
            ->paginate($query->perPage, ['*'], 'page', $query->page);
    }

    public function totals(): array
    {
        $counts = DiscussionRoom::query()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when is_private = ? then 1 else 0 end) as open_rooms', [false])
            ->selectRaw('sum(case when is_private = ? then 1 else 0 end) as private_rooms', [true])
            ->selectRaw('sum(case when is_locked = ? then 1 else 0 end) as locked_rooms', [true])
            ->toBase()
            ->first();

        return [
            'total' => (int) $counts->total,
            'public' => (int) $counts->open_rooms,
            'private' => (int) $counts->private_rooms,
            'locked' => (int) $counts->locked_rooms,
            'reported' => $this->withOpenReport(DiscussionRoom::query())->count(),
        ];
    }

    public function loadRow(DiscussionRoom $room): DiscussionRoom
    {
        return $this->rows()->whereKey($room->getKey())->firstOrFail();
    }

    public function openMessageReportCounts(array $roomIds): array
    {
        return Report::query()
            ->open()
            ->where('reports.target_type', ReportTargetType::Message)
            ->join('messages', 'messages.id', '=', 'reports.target_id')
            ->whereIn('messages.room_id', $roomIds)
            ->selectRaw('messages.room_id as room_id, count(*) as total')
            ->groupBy('messages.room_id')
            ->pluck('total', 'room_id')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }

    public function hasOpenReport(DiscussionRoom $room): bool
    {
        return $this->withOpenReport(DiscussionRoom::query()->whereKey($room->getKey()))->exists();
    }

    public function members(DiscussionRoom $room, int $limit): Collection
    {
        return $room->members()
            ->select(['users.id', 'users.name', 'users.username'])
            ->withPivot('created_at')
            ->orderBy('room_members.created_at')
            ->orderBy('room_members.id')
            ->limit($limit)
            ->get();
    }

    public function messages(DiscussionRoom $room, int $page, int $perPage): LengthAwarePaginator
    {
        return $room->messages()
            ->with('user:id,name,username')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function messageIds(DiscussionRoom $room): array
    {
        return $room->messages()->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * Every room with the columns the staff row needs.
     *
     * @return Builder<DiscussionRoom>
     */
    private function rows(): Builder
    {
        return DiscussionRoom::query()
            ->select('discussion_rooms.*')
            ->selectSub(Message::query()->selectRaw('max(messages.created_at)')->whereColumn('messages.room_id', 'discussion_rooms.id'), 'last_message_at')
            ->withCasts(['last_message_at' => 'datetime'])
            ->withCount(['members', 'messages'])
            ->with('creator:id,name,username');
    }

    /**
     * Rooms whose title, film title in the local catalogue, or creator's name or username contains the text.
     *
     * @param  Builder<DiscussionRoom>  $rooms
     * @return Builder<DiscussionRoom>
     */
    private function matching(Builder $rooms, string $pattern): Builder
    {
        return $rooms->where(fn (Builder $match) => $match
            ->whereRaw("discussion_rooms.name LIKE ? ESCAPE '!'", [$pattern])
            ->orWhereExists(LocalMovie::query()
                ->whereColumn('movies.tmdb_id', 'discussion_rooms.tmdb_id')
                ->where('discussion_rooms.media_type', MediaType::MOVIE)
                ->whereRaw("movies.title LIKE ? ESCAPE '!'", [$pattern]))
            ->orWhereHas('creator', fn (Builder $creator) => $creator
                ->whereRaw("users.name LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("users.username LIKE ? ESCAPE '!'", [$pattern])));
    }

    /**
     * Rooms with an open report on the room itself or on one of its messages.
     *
     * @param  Builder<DiscussionRoom>  $rooms
     * @return Builder<DiscussionRoom>
     */
    private function withOpenReport(Builder $rooms): Builder
    {
        return $rooms->where(fn (Builder $reported) => $reported
            ->whereExists(Report::query()->open()
                ->where('reports.target_type', ReportTargetType::Room)
                ->whereColumn('reports.target_id', 'discussion_rooms.id'))
            ->orWhereExists(Report::query()->open()
                ->where('reports.target_type', ReportTargetType::Message)
                ->whereIn('reports.target_id', Message::query()->select('messages.id')->whereColumn('messages.room_id', 'discussion_rooms.id'))));
    }
}
