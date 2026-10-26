<?php

namespace App\Room\InterfaceServices;

use App\Models\DiscussionRoom;
use App\Models\User;
use App\Room\DTOs\Requests\CreateRoomData;
use App\Room\DTOs\Requests\ReadRoomMessagesData;
use App\Room\DTOs\Requests\SendRoomMessageData;
use App\Room\DTOs\Responses\ReadReceiptsResponse;
use App\Room\DTOs\Responses\RoomListResponse;
use App\Room\DTOs\Responses\RoomMessageResponse;
use App\Room\DTOs\Responses\RoomMessagesResponse;
use App\Room\DTOs\Responses\RoomResponse;

/**
 * Discussion rooms. A room's members are its creator and everyone who has posted in it. A message is
 * stored until every member at the time it was sent has read it, then deleted; while stored it is pushed
 * to whoever has the room open and served to members when they open it.
 */
interface RoomServiceInterface
{
    /** Every room; the ones the viewer belongs to first, each with the viewer's unread count. */
    public function list(User $viewer): RoomListResponse;

    /** Creates the room; its creator becomes its first member. */
    public function create(CreateRoomData $data, User $creator): RoomResponse;

    /** The messages someone still has to read, with who has read each one. */
    public function messages(DiscussionRoom $room): RoomMessagesResponse;

    /**
     * Posting makes the sender a member. The message is read by its sender, waits for every other member
     * and is pushed over the room's socket channel.
     */
    public function send(DiscussionRoom $room, SendRoomMessageData $data, User $sender): RoomMessageResponse;

    /** Marks the messages read by the reader and deletes the ones every member has now read. */
    public function read(DiscussionRoom $room, ReadRoomMessagesData $data, User $reader): ReadReceiptsResponse;
}
