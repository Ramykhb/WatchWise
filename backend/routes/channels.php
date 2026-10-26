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

// Rooms lists: new messages and room changes of public rooms go to everyone, those of a private room to each
// of its members and invitees on their own channel.
Broadcast::connection('reverb')->channel('rooms.public', fn (User $user): bool => true);
Broadcast::connection('reverb')->channel('rooms.user.{userId}',
    fn (User $user, string $userId): bool => (string) $user->id === $userId
);
