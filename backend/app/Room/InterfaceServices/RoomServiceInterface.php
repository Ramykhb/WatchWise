<?php

namespace App\Room\InterfaceServices;

use App\Models\DiscussionRoom;
use App\Models\User;
use App\Room\DTOs\Requests\CreateRoomData;
use App\Room\DTOs\Requests\InviteToRoomData;
use App\Room\DTOs\Requests\ReadRoomMessagesData;
use App\Room\DTOs\Requests\SendRoomMessageData;
use App\Room\DTOs\Responses\ReadReceiptsResponse;
use App\Room\DTOs\Responses\RoomListResponse;
use App\Room\DTOs\Responses\RoomMessageResponse;
use App\Room\DTOs\Responses\RoomMessagesResponse;
use App\Room\DTOs\Responses\RoomResponse;

/**
 * Discussion rooms: group chats about a film. A public room can be read and joined by anyone; a private one
 * only by its members and the people they invite. A room's members are its creator and everyone who joined.
 * A message is stored until every member at the time it was sent has read it, then deleted; while stored it
 * is pushed to whoever has the room open and served when they open it. Room lists are kept current over the
 * `rooms.public` and `rooms.user.{id}` socket channels.
 */
interface RoomServiceInterface
{
    /** The rooms the viewer can see; theirs first, then invitations, each with the viewer's unread count. */
    public function list(User $viewer): RoomListResponse;

    /** Creates the room; its creator becomes its first member. */
    public function create(CreateRoomData $data, User $creator): RoomResponse;

    /** Joins a public room, or accepts the invitation to a private one. */
    public function join(DiscussionRoom $room, User $user): RoomResponse;

    /** Invites people to a private room; members and people already invited are skipped. */
    public function invite(DiscussionRoom $room, InviteToRoomData $data, User $inviter): RoomResponse;

    /** Turns down the user's invitation to a private room. */
    public function decline(DiscussionRoom $room, User $user): void;

    /** The messages someone still has to read, with who has read each one. */
    public function messages(DiscussionRoom $room): RoomMessagesResponse;

    /**
     * The message is read by its sender (a member), waits for every other member and is pushed to the room's
     * socket channel and the room-list channels.
     */
    public function send(DiscussionRoom $room, SendRoomMessageData $data, User $sender): RoomMessageResponse;

    /** Marks the messages read by the reader and deletes the ones every member has now read. */
    public function read(DiscussionRoom $room, ReadRoomMessagesData $data, User $reader): ReadReceiptsResponse;
}
