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
 * A message is pushed to whoever has the room open and shown to everyone who can see the room for 24 hours
 * after it is sent; after that only to the members at the time it was sent who have yet to read it. It is
 * deleted once its 24 hours are up and none of them still has to read it. Browsers keep no copy: the server
 * is the only one. Room lists are kept current over the `rooms.public` and `rooms.user.{id}` socket channels.
 */
interface RoomServiceInterface
{
    /**
     * The rooms the viewer can see; theirs first, then invitations, each with the viewer's unread count and the
     * newest message the viewer can see.
     */
    public function list(User $viewer): RoomListResponse;

    /** Creates the room; its creator becomes its first member. */
    public function create(CreateRoomData $data, User $creator): RoomResponse;

    /** Joins a public room, or accepts the invitation to a private one. */
    public function join(DiscussionRoom $room, User $user): RoomResponse;

    /** Invites people to a private room; members and people already invited are skipped. */
    public function invite(DiscussionRoom $room, InviteToRoomData $data, User $inviter): RoomResponse;

    /** Turns down the user's invitation to a private room. */
    public function decline(DiscussionRoom $room, User $user): void;

    /** The stored messages the viewer can still see, with who has read each one. */
    public function messages(DiscussionRoom $room, User $viewer): RoomMessagesResponse;

    /**
     * The message is read by its sender (a member), waits for every other member and is pushed to the room's
     * socket channel and the room-list channels. It is kept for at least its 24 hours, even with nobody else to
     * read it.
     */
    public function send(DiscussionRoom $room, SendRoomMessageData $data, User $sender): RoomMessageResponse;

    /**
     * Marks the messages read by the reader and deletes the ones whose 24 hours are up that every member has now
     * read.
     */
    public function read(DiscussionRoom $room, ReadRoomMessagesData $data, User $reader): ReadReceiptsResponse;

    /**
     * Deletes, in every room, the messages whose 24 hours are up that no member still has to read (everyone read
     * them in time, or nobody else had to); returns how many.
     */
    public function pruneMessages(): int;
}
