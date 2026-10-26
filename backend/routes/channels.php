<?php

use App\Models\DiscussionRoom;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

Broadcast::connection('reverb')->channel('chat.{userId}.{conversationId}',
    fn (User $user, string $userId, string $conversationId): bool => (string) $user->id === $userId && Str::isUuid($conversationId)
);

// New messages and read receipts of a discussion room; an unknown room fails the binding (403).
Broadcast::connection('reverb')->channel('room.{room}',
    fn (User $user, DiscussionRoom $room): bool => Gate::allows('view', $room)
);
