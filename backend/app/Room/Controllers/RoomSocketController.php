<?php

namespace App\Room\Controllers;

use App\Auth\CurrentUser\CurrentUser;
use App\Room\FormRequests\RoomSocketRequest;
use Illuminate\Contracts\Broadcasting\Factory;
use Illuminate\Http\JsonResponse;

class RoomSocketController
{
    public function __construct(private readonly Factory $broadcasting) {}

    /** POST /api/rooms/socket-auth — signs the browser's subscription to a room's private channel. */
    public function store(RoomSocketRequest $request, CurrentUser $current): JsonResponse
    {
        $request->setUserResolver(fn () => $current->user());

        return response()->json($this->broadcasting->connection('reverb')->auth($request));
    }
}
