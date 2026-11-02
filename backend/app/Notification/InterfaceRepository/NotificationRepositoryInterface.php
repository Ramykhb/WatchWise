<?php

namespace App\Notification\InterfaceRepository;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface NotificationRepositoryInterface
{
    /**
     * @return Collection<int, Notification> the user's newest feed notifications first (never room read receipts)
     */
    public function feed(int $userId, int $limit): Collection;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(int $userId, string $type, array $data): Notification;

    /**
     * @param  array<string, mixed>  $attributes  any of type, data, read_at, created_at
     */
    public function update(Notification $notification, array $attributes): Notification;

    /**
     * @param  list<int>  $ids
     */
    public function delete(array $ids): void;

    /**
     * @param  list<int>  $ids
     */
    public function setRead(array $ids, bool $read): void;

    /** Marks every unread feed notification of the user read and returns how many changed. */
    public function markAllRead(int $userId): int;

    /**
     * Notifications of these users and types whose `data` holds every key => value of `$match`. The JSON is
     * compared in PHP, so the lookup behaves the same on MySQL, PostgreSQL and SQLite.
     *
     * @param  list<int>  $userIds
     * @param  list<string>  $types
     * @param  array<string, int|string>  $match
     * @return Collection<int, Notification> newest first
     */
    public function matching(array $userIds, array $types, array $match): Collection;

    /**
     * Like matching(), for everyone (e.g. every notification about a room that is being deleted).
     *
     * @param  list<string>  $types
     * @param  array<string, int|string>  $match
     * @return Collection<int, Notification> newest first
     */
    public function matchingAnyone(array $types, array $match): Collection;

    /**
     * The users who haven't switched this type off in their notification settings (everyone has it on by default).
     *
     * @param  list<int>  $userIds
     * @return list<int>
     */
    public function wanting(array $userIds, string $type): array;

    /**
     * @param  list<int>  $ids
     * @return Collection<int, User> keyed by id
     */
    public function users(array $ids): Collection;

    /**
     * @param  list<int>  $userIds
     * @return list<int> the ones `$followerId` follows
     */
    public function followedBy(int $followerId, array $userIds): array;

    /**
     * @param  list<int>  $userIds
     * @return list<int> the ones `$requesterId` has a pending follow request with
     */
    public function requestedBy(int $requesterId, array $userIds): array;
}
