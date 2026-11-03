<?php

namespace App\Notification\Services;

use App\Models\Notification;
use App\Notification\DTOs\Responses\NotificationResponse;
use App\Notification\InterfaceRepository\NotificationRepositoryInterface;
use Illuminate\Support\Collection;

/** Turns stored notifications into what one reader sees, loading every actor and follow state in one go. */
class NotificationPresenter
{
    public function __construct(private readonly NotificationRepositoryInterface $notifications) {}

    /**
     * @param  Collection<int, Notification>  $notifications  all belonging to `$readerId`
     * @return list<NotificationResponse> without the ones that can't be shown any more
     */
    public function present(Collection $notifications, int $readerId): array
    {
        $actorIds = $notifications->map(fn (Notification $notification): int => (int) ($notification->data['actor_id'] ?? 0))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $actors = $this->notifications->users($actorIds);
        $followed = $this->notifications->followedBy($readerId, $actorIds);
        $requested = $this->notifications->requestedBy($readerId, $actorIds);

        return $notifications
            ->map(function (Notification $notification) use ($actors, $followed, $requested): ?NotificationResponse {
                $actorId = (int) ($notification->data['actor_id'] ?? 0);

                return NotificationResponse::fromModel(
                    $notification,
                    $actors->get($actorId),
                    in_array($actorId, $followed, true),
                    in_array($actorId, $requested, true),
                );
            })
            ->filter()
            ->values()
            ->all();
    }

    public function presentOne(Notification $notification): ?NotificationResponse
    {
        return $this->present(collect([$notification]), (int) $notification->user_id)[0] ?? null;
    }
}
