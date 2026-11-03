<?php

namespace App\Notification\Repository;

use App\Models\Follow;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserPreference;
use App\Notification\InterfaceRepository\NotificationRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class NotificationRepository implements NotificationRepositoryInterface
{
    public function feed(int $userId, int $limit): Collection
    {
        $history = Notification::where('user_id', $userId)
            ->whereIn('type', Notification::FEED_TYPES)
            ->where('type', '!=', Notification::FOLLOW_REQUEST)
            ->latest('created_at')
            ->latest('id')
            ->limit($limit)
            ->get();

        return $history->merge(Notification::where('user_id', $userId)
            ->where('type', Notification::FOLLOW_REQUEST)->get())
            ->sortByDesc(fn (Notification $notification): array => [$notification->created_at->getTimestamp(), $notification->id])
            ->values();
    }

    public function createFollowRequest(int $requesterId, int $targetId): Notification
    {
        return Notification::firstOrCreate(
            ['user_id' => $targetId, 'pending_follow_requester_id' => $requesterId],
            ['type' => Notification::FOLLOW_REQUEST, 'data' => ['actor_id' => $requesterId]],
        );
    }

    public function create(int $userId, string $type, array $data): Notification
    {
        return Notification::create(['user_id' => $userId, 'type' => $type, 'data' => $data]);
    }

    public function update(Notification $notification, array $attributes): Notification
    {
        $notification->forceFill($attributes)->save();

        return $notification;
    }

    public function delete(array $ids): void
    {
        if ($ids !== []) {
            Notification::whereKey($ids)->delete();
        }
    }

    public function setRead(array $ids, bool $read): void
    {
        if ($ids === []) {
            return;
        }

        Notification::whereKey($ids)
            ->when($read, fn ($query) => $query->whereNull('read_at'))
            ->update(['read_at' => $read ? now() : null]);
    }

    public function markAllRead(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->whereIn('type', Notification::FEED_TYPES)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function matching(array $userIds, array $types, array $match): Collection
    {
        return $userIds === [] ? new Collection : $this->withData(Notification::whereIn('user_id', $userIds), $types, $match);
    }

    public function matchingAnyone(array $types, array $match): Collection
    {
        return $this->withData(Notification::query(), $types, $match);
    }

    public function wanting(array $userIds, string $type): array
    {
        $userIds = array_values(array_unique($userIds));
        if ($userIds === []) {
            return [];
        }

        $switchedOff = UserPreference::whereIn('user_id', $userIds)
            ->get(['user_id', 'settings'])
            ->reject(fn (UserPreference $preferences): bool => (bool) ($preferences->settings['notifications'][$type] ?? true))
            ->map(fn (UserPreference $preferences): int => (int) $preferences->user_id)
            ->all();

        return array_values(array_diff($userIds, $switchedOff));
    }

    public function users(array $ids): Collection
    {
        return $ids === [] ? new Collection : User::whereKey($ids)->get()->keyBy('id');
    }

    public function followedBy(int $followerId, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return Follow::where('follower_id', $followerId)
            ->whereIn('following_id', $userIds)
            ->pluck('following_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function requestedBy(int $requesterId, array $userIds): array
    {
        return $this->matching($userIds, [Notification::FOLLOW_REQUEST], ['actor_id' => $requesterId])
            ->map(fn (Notification $request): int => (int) $request->user_id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The query's notifications of these types whose `data` holds every key => value of `$match`.
     *
     * @param  Builder<Notification>  $query
     * @param  list<string>  $types
     * @param  array<string, int|string>  $match
     * @return Collection<int, Notification>
     */
    private function withData(Builder $query, array $types, array $match): Collection
    {
        return $query->whereIn('type', $types)
            ->latest('created_at')
            ->latest('id')
            ->get()
            ->filter(function (Notification $notification) use ($match): bool {
                foreach ($match as $key => $value) {
                    if ((string) ($notification->data[$key] ?? '') !== (string) $value) {
                        return false;
                    }
                }

                return true;
            })
            ->values();
    }
}
