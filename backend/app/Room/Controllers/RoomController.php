<?php

namespace App\Room\Controllers;

use App\Auth\CurrentUser\CurrentUser;
use App\Models\DiscussionRoom;
use App\Room\FormRequests\InviteToRoomRequest;
use App\Room\FormRequests\ReadRoomMessagesRequest;
use App\Room\FormRequests\SendRoomMessageRequest;
use App\Room\FormRequests\StoreRoomRequest;
use App\Room\InterfaceServices\RoomServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/** Discussion rooms: public or private group chats, with messages kept until every member has read them. */
class RoomController
{
    public function __construct(private readonly RoomServiceInterface $rooms) {}

    /** GET /api/rooms — the rooms you can see, yours first, with your unread count. */
    public function index(CurrentUser $current): JsonResponse
    {
        return response()->json($this->rooms->list($current->user()));
    }

    /** POST /api/rooms — the creator becomes the first member. */
    public function store(StoreRoomRequest $request, CurrentUser $current): JsonResponse
    {
        return response()->json(['room' => $this->rooms->create($request->toDto(), $current->user())], 201);
    }

    /** POST /api/rooms/{room}/join — join a public room, or accept the invitation to a private one. */
    public function join(DiscussionRoom $room, CurrentUser $current): JsonResponse
    {
        Gate::authorize('join', $room);

        return response()->json(['room' => $this->rooms->join($room, $current->user())]);
    }

    /** POST /api/rooms/{room}/invites — a member of a private room invites people. */
    public function invite(InviteToRoomRequest $request, DiscussionRoom $room, CurrentUser $current): JsonResponse
    {
        return response()->json(['room' => $this->rooms->invite($room, $request->toDto(), $current->user())]);
    }

    /** DELETE /api/rooms/{room}/invite — turn down your invitation to a private room. */
    public function decline(DiscussionRoom $room, CurrentUser $current): JsonResponse
    {
        Gate::authorize('decline', $room);

        $this->rooms->decline($room, $current->user());

        return response()->json(['message' => 'Invitation declined.']);
    }

    /** GET /api/rooms/{room}/messages — the messages someone still has to read, with who has read each one. */
    public function messages(DiscussionRoom $room): JsonResponse
    {
        Gate::authorize('view', $room);

        return response()->json($this->rooms->messages($room));
    }

    /** POST /api/rooms/{room}/messages — stored until every member has read it and pushed to whoever has the room open. */
    public function send(SendRoomMessageRequest $request, DiscussionRoom $room, CurrentUser $current): JsonResponse
    {
        return response()->json(['message' => $this->rooms->send($room, $request->toDto(), $current->user())], 201);
    }

    /** POST /api/rooms/{room}/read — the signed-in member has seen these messages. */
    public function read(ReadRoomMessagesRequest $request, DiscussionRoom $room, CurrentUser $current): JsonResponse
    {
        return response()->json($this->rooms->read($room, $request->toDto(), $current->user()));
    }
}
