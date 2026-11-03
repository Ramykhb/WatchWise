<?php

namespace App\Notification\DTOs\Responses;

use JsonSerializable;

/** GET /api/notifications: the signed-in user's notifications, newest first. */
final readonly class NotificationFeedResponse implements JsonSerializable
{
    /**
     * @param  list<NotificationResponse>  $notifications
     */
    public function __construct(public array $notifications) {}

    /**
     * @return array{notifications: list<NotificationResponse>}
     */
    public function jsonSerialize(): array
    {
        return ['notifications' => $this->notifications];
    }
}
