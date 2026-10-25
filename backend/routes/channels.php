<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Str;

Broadcast::connection('reverb')->channel('chat.{userId}.{conversationId}',
    fn (User $user, string $userId, string $conversationId): bool => (string) $user->id === $userId && Str::isUuid($conversationId)
);
