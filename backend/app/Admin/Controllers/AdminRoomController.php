<?php

namespace App\Admin\Controllers;

use App\Admin\FormRequests\AdminRoomIndexRequest;
use App\Admin\FormRequests\AdminRoomMessagesRequest;
use App\Admin\FormRequests\UpdateRoomRequest;
use App\Admin\InterfaceServices\AdminRoomServiceInterface;
use App\Auth\CurrentUser\CurrentUser;
use App\Models\DiscussionRoom;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/** Staff tools for discussion rooms: reading them and their messages, removing messages, locking and deleting. */
class AdminRoomController
{
    public function __construct(private readonly AdminRoomServiceInterface $rooms) {}

    /** GET /api/admin/rooms */
    public function index(AdminRoomIndexRequest $request): JsonResponse
    {
        return response()->json($this->rooms->rooms($request->toDto()));
    }

    /** GET /api/admin/rooms/{room} */
    public function show(DiscussionRoom $room): JsonResponse
    {
        Gate::authorize('admin.rooms');

        return response()->json(['room' => $this->rooms->room($room)]);
    }

    /** GET /api/admin/rooms/{room}/messages — newest first; a private room only while it has an open report. */
    public function messages(AdminRoomMessagesRequest $request, DiscussionRoom $room): JsonResponse
    {
        return response()->json($this->rooms->messages($room, $request->toDto()));
    }

    /** DELETE /api/admin/rooms/{room}/messages/{message} */
    public function destroyMessage(DiscussionRoom $room, Message $message, CurrentUser $current): JsonResponse
    {
        Gate::authorize('admin.rooms');
        $this->rooms->deleteMessage($current->user(), $room, $message);

        return response()->json(['message' => 'Message deleted.']);
    }

    /** PATCH /api/admin/rooms/{room} — lock or unlock. */
    public function update(UpdateRoomRequest $request, DiscussionRoom $room, CurrentUser $current): JsonResponse
    {
        return response()->json(['room' => $this->rooms->setLocked($current->user(), $room, $request->toDto())]);
    }

    /** DELETE /api/admin/rooms/{room} — admins only. */
    public function destroy(DiscussionRoom $room, CurrentUser $current): JsonResponse
    {
        Gate::authorize('admin.rooms.delete');
        $this->rooms->deleteRoom($current->user(), $room);

        return response()->json(['message' => 'Room deleted.']);
    }
}
