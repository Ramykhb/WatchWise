<?php

namespace App\Admin\InterfaceServices;

use App\Admin\DTOs\Requests\AdminRoomMessagesQuery;
use App\Admin\DTOs\Requests\AdminRoomQuery;
use App\Admin\DTOs\Requests\UpdateRoomData;
use App\Admin\DTOs\Responses\AdminPageResponse;
use App\Admin\DTOs\Responses\AdminRoomDetailResponse;
use App\Admin\DTOs\Responses\AdminRoomResponse;
use App\Admin\Exceptions\RoomNotReviewable;
use App\Models\DiscussionRoom;
use App\Models\Message;
use App\Models\User;

/**
 * Staff tools for discussion rooms and their messages. Moderators and admins read rooms, remove messages and lock
 * or unlock; deleting a whole room is the admin's. Every change is audited in the same transaction as the change;
 * the notices and pushes go out only once it committed.
 */
interface AdminRoomServiceInterface
{
    /** One page of rooms, newest activity first; hidden films do not hide a room. The page's summary counts every room by visibility, lock and open reports. */
    public function rooms(AdminRoomQuery $query): AdminPageResponse;

    /** The room's row with its members (at most 200) and the open reports against the room itself. */
    public function room(DiscussionRoom $room): AdminRoomDetailResponse;

    /**
     * One page of the room's stored messages, newest first. Reading never marks anything read. The messages of a
     * public room are open to staff; those of a private room only while the room, or one of its messages, has an open report.
     *
     * @throws RoomNotReviewable
     */
    public function messages(DiscussionRoom $room, AdminRoomMessagesQuery $query): AdminPageResponse;

    /**
     * Deletes the message as the Room module deletes one (receipts gone, unread counts drop, the open room is told),
     * closes its open reports as resolved, audits it (author and room only, never the text) and tells its author,
     * who is not told the text or who acted.
     */
    public function deleteMessage(User $staff, DiscussionRoom $room, Message $message): void;

    /**
     * Locks or unlocks the room. Only a real change is audited and told to the room's creator, who is not told who
     * acted; sending the state the room already has changes and records nothing.
     */
    public function setLocked(User $staff, DiscussionRoom $room, UpdateRoomData $data): AdminRoomResponse;

    /**
     * Deletes the room through the Room module (receipts go) and closes the open reports on the room and on its
     * messages as resolved (the messages are read inside the transaction, so one sent meanwhile is covered). Members
     * and invitees hear it from the WatchWise team, never from the staff member; the creator always does, whatever
     * their switch (`room_removed`), unless they are the staff member.
     */
    public function deleteRoom(User $staff, DiscussionRoom $room): void;
}
