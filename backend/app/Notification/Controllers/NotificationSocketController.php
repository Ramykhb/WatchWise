<?php

namespace App\Notification\Controllers;

use App\Auth\CurrentUser\CurrentUser;
use App\Notification\FormRequests\NotificationSocketRequest;
use Illuminate\Contracts\Broadcasting\Factory;
use Illuminate\Http\JsonResponse;

class NotificationSocketController
{
    public function __construct(private readonly Factory $broadcasting) {}

    /** POST /api/notifications/socket-auth — signs the browser's subscription to its notifications channel. */
    public function store(NotificationSocketRequest $request, CurrentUser $current): JsonResponse
    {
        $request->setUserResolver(fn () => $current->user());

        return response()->json($this->broadcasting->connection('reverb')->auth($request));
    }
}
