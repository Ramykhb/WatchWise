<?php

namespace App\Room\InterfaceRepository;

use App\Models\DiscussionRoom;
use App\Models\Message;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Rooms, their members and their messages. A message's read receipts are `Notification::ROOM_MESSAGE` rows,
 * one per member who has to read it; `read_at` is set when they have.
 */
interface RoomRepositoryInterface
{
    /**
     * Every room with its creator, members, newest stored message (and its author) and stored-message count.
     *
     * @return Collection<int, DiscussionRoom>
     */
    public function all(): Collection;

    /**
     * @param  array{name: string, tmdb_id: int, created_by: int, spoilers: bool}  $attributes
     */
    public function create(array $attributes): DiscussionRoom;

    /** Loads the creator, members, newest stored message and stored-message count. */
    public function loadDetail(DiscussionRoom $room): DiscussionRoom;

    /** Adds the user to the room's members; nothing happens when they already are one. */
    public function addMember(DiscussionRoom $room, int $userId): void;

    /**
     * @return list<int>
     */
    public function memberIds(DiscussionRoom $room): array;

    /**
     * @param  list<int>  $ids
     * @return Collection<int, User> keyed by id
     */
    public function usersByIds(array $ids): Collection;

    /** Stores the message and loads its author. */
    public function createMessage(DiscussionRoom $room, int $userId, string $content): Message;

    /**
     * One unread receipt per user who has to read the message.
     *
     * @param  list<int>  $userIds
     */
    public function createReceipts(Message $message, array $userIds): void;

    /**
     * The messages still stored for the room, oldest first, with their authors.
     *
     * @return Collection<int, Message>
     */
    public function storedMessages(DiscussionRoom $room): Collection;

    /**
     * All of the room's receipts (user_id, data with message_id, read_at).
     *
     * @return Collection<int, Notification>
     */
    public function receipts(DiscussionRoom $room): Collection;

    /**
     * Marks the user's unread receipts for these messages of the room as read.
     *
     * @param  list<int>  $messageIds
     * @return list<int> the messages whose receipt changed
     */
    public function markRead(DiscussionRoom $room, int $userId, array $messageIds): array;

    /**
     * Of these messages, the ones no receipt is waiting on any more.
     *
     * @param  list<int>  $messageIds
     * @return list<int>
     */
    public function fullyRead(DiscussionRoom $room, array $messageIds): array;

    /**
     * Deletes the messages and their receipts.
     *
     * @param  list<int>  $messageIds
     */
    public function deleteMessages(DiscussionRoom $room, array $messageIds): void;

    /**
     * How many messages each room has that the user still has to read.
     *
     * @return array<int, int> room id => count
     */
    public function unreadCounts(int $userId): array;
}
