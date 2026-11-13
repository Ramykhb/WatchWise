<?php

namespace App\Admin\InterfaceRepository;

use App\Admin\DTOs\Requests\AdminRoomQuery;
use App\Models\DiscussionRoom;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * What staff see of discussion rooms: every room whoever it is for, read-only. Changing a room goes through the Room module.
 * A room's "open report" is an open report on the room itself or on one of its messages.
 */
interface AdminRoomRepositoryInterface
{
    /**
     * One page, the room with the newest activity first (its newest stored message, else its creation; then the
     * highest id), each with its `creator` loaded and `members_count`, `messages_count` and `last_message_at` set.
     *
     * @return LengthAwarePaginator<int, DiscussionRoom>
     */
    public function paginate(AdminRoomQuery $query): LengthAwarePaginator;

    /**
     * Whole-table counts of rooms.
     *
     * @return array{total: int, public: int, private: int, locked: int, reported: int}
     */
    public function totals(): array;

    /** The room as paginate() loads it. */
    public function loadRow(DiscussionRoom $room): DiscussionRoom;

    /**
     * Open reports on the messages of each room, for the rooms that have any.
     *
     * @param  list<int>  $roomIds
     * @return array<int, int> room id => open reports on its messages
     */
    public function openMessageReportCounts(array $roomIds): array;

    /** Whether the room, or any message in it, has an open report. */
    public function hasOpenReport(DiscussionRoom $room): bool;

    /**
     * The members in the order they joined, at most `$limit`, with the `created_at` of their `room_members` row as pivot.
     *
     * @return Collection<int, User>
     */
    public function members(DiscussionRoom $room, int $limit): Collection;

    /**
     * One page of the room's stored messages, newest first, each with its author loaded.
     *
     * @return LengthAwarePaginator<int, Message>
     */
    public function messages(DiscussionRoom $room, int $page, int $perPage): LengthAwarePaginator;

    /**
     * @return list<int>
     */
    public function messageIds(DiscussionRoom $room): array;
}
