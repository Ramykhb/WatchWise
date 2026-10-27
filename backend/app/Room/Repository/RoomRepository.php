<?php

namespace App\Room\Repository;

use App\Models\DiscussionRoom;
use App\Models\Message;
use App\Models\Notification;
use App\Models\User;
use App\Room\InterfaceRepository\RoomRepositoryInterface;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class RoomRepository implements RoomRepositoryInterface
{
    public function visibleTo(int $userId, CarbonInterface $sentAfter): Collection
    {
        $shown = $this->shownTo($userId, $sentAfter);

        return DiscussionRoom::with($this->detail($shown))
            ->withCount(['messages' => $shown])
            ->where(fn (Builder $rooms) => $rooms
                ->where('is_private', false)
                ->orWhereHas('members', fn (Builder $members) => $members->whereKey($userId))
                ->orWhereHas('invitees', fn (Builder $invitees) => $invitees->whereKey($userId)))
            ->get();
    }

    public function create(array $attributes): DiscussionRoom
    {
        return DiscussionRoom::create($attributes);
    }

    public function loadDetail(DiscussionRoom $room, int $viewerId, CarbonInterface $sentAfter): DiscussionRoom
    {
        $shown = $this->shownTo($viewerId, $sentAfter);

        return $room->load($this->detail($shown))->loadCount(['messages' => $shown]);
    }

    public function addMember(DiscussionRoom $room, int $userId): void
    {
        // insertOrIgnore: two first posts at the same time can't break the unique pair.
        DB::table('room_members')->insertOrIgnore(['room_id' => $room->id, 'user_id' => $userId]);
    }

    public function memberIds(DiscussionRoom $room): array
    {
        return $room->members()->pluck('users.id')->map(fn ($id): int => (int) $id)->all();
    }

    public function addInvites(DiscussionRoom $room, array $userIds, int $invitedBy): void
    {
        DB::table('room_invites')->insertOrIgnore(array_map(fn (int $userId): array => [
            'room_id' => $room->id,
            'user_id' => $userId,
            'invited_by' => $invitedBy,
        ], $userIds));
    }

    public function removeInvite(DiscussionRoom $room, int $userId): void
    {
        DB::table('room_invites')->where('room_id', $room->id)->where('user_id', $userId)->delete();
    }

    public function inviteeIds(DiscussionRoom $room): array
    {
        return $room->invitees()->pluck('users.id')->map(fn ($id): int => (int) $id)->all();
    }

    public function usersByIds(array $ids): Collection
    {
        return User::whereKey($ids)->get()->keyBy('id');
    }

    public function usersByUsername(array $usernames, array $exceptIds): Collection
    {
        return User::whereIn('username', $usernames)->whereKeyNot($exceptIds)->get();
    }

    public function createMessage(DiscussionRoom $room, int $userId, string $content): Message
    {
        return $room->messages()->create(['user_id' => $userId, 'content' => $content])->load('user');
    }

    public function createReceipts(Message $message, array $userIds): void
    {
        if ($userIds === []) {
            return;
        }

        $data = json_encode(['room_id' => $message->room_id, 'message_id' => $message->id]);
        $now = now();

        Notification::insert(array_map(fn (int $userId): array => [
            'user_id' => $userId,
            'type' => Notification::ROOM_MESSAGE,
            'data' => $data,
            'created_at' => $now,
        ], $userIds));
    }

    public function storedMessages(DiscussionRoom $room): Collection
    {
        return $room->messages()->with('user')->orderBy('id')->get();
    }

    public function receipts(DiscussionRoom $room): Collection
    {
        return $this->roomReceipts($room)->orderBy('read_at')->get(['id', 'user_id', 'data', 'read_at']);
    }

    public function markRead(DiscussionRoom $room, int $userId, array $messageIds): array
    {
        $receipts = $this->roomReceipts($room)
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->whereIn('data->message_id', $messageIds)
            ->get(['id', 'data']);

        if ($receipts->isEmpty()) {
            return [];
        }

        Notification::whereKey($receipts->modelKeys())->update(['read_at' => now()]);

        return $this->messageIdsOf($receipts);
    }

    public function fullyRead(DiscussionRoom $room, array $messageIds, CarbonInterface $sentBy): array
    {
        $sent = $messageIds === [] ? [] : $room->messages()
            ->whereKey($messageIds)
            ->where('created_at', '<=', $sentBy)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($sent === []) {
            return [];
        }

        $waiting = $this->messageIdsOf(
            $this->roomReceipts($room)->whereNull('read_at')->whereIn('data->message_id', $sent)->get(['data']),
        );

        return array_values(array_diff($sent, $waiting));
    }

    public function deleteFullyRead(CarbonInterface $sentBy): int
    {
        $deleted = 0;

        Message::query()
            ->select('id')
            ->where('created_at', '<=', $sentBy)
            ->whereNotExists(fn (QueryBuilder $receipts) => $this->unreadReceipts($receipts, 'messages.id'))
            ->chunkById(500, function (Collection $messages) use (&$deleted): void {
                $ids = $messages->modelKeys();
                DB::transaction(function () use ($ids): void {
                    Notification::where('type', Notification::ROOM_MESSAGE)->whereIn('data->message_id', $ids)->delete();
                    Message::whereKey($ids)->delete();
                });
                $deleted += count($ids);
            });

        return $deleted;
    }

    public function deleteMessages(DiscussionRoom $room, array $messageIds): void
    {
        if ($messageIds === []) {
            return;
        }

        DB::transaction(function () use ($room, $messageIds): void {
            $this->roomReceipts($room)->whereIn('data->message_id', $messageIds)->delete();
            $room->messages()->whereKey($messageIds)->delete();
        });
    }

    public function unreadCounts(int $userId): array
    {
        return Notification::where('user_id', $userId)
            ->where('type', Notification::ROOM_MESSAGE)
            ->whereNull('read_at')
            ->get(['data'])
            ->countBy(fn (Notification $receipt): int => (int) $receipt->data['room_id'])
            ->all();
    }

    public function unreadCount(DiscussionRoom $room, int $userId): int
    {
        return $this->roomReceipts($room)->where('user_id', $userId)->whereNull('read_at')->count();
    }

    /**
     * The creator, members, invitees and the newest message `$shown` lets through, with its author.
     *
     * @param  Closure(Builder<Message>): Builder<Message>  $shown
     * @return array<int|string, string|Closure>
     */
    private function detail(Closure $shown): array
    {
        return [
            'creator',
            'members',
            'invitees',
            // Narrows the "newest of many" subquery itself: a constraint on the relation would only drop the newest
            // stored message instead of finding the newest one the user can see.
            'latestMessage' => fn (HasOne $latest) => $shown($latest->getOneOfManySubQuery()),
            'latestMessage.user',
        ];
    }

    /**
     * Narrows a messages query to the ones the user can see: those sent after `$sentAfter`, and older ones the
     * user still has to read.
     *
     * @return Closure(Builder<Message>): Builder<Message>
     */
    private function shownTo(int $userId, CarbonInterface $sentAfter): Closure
    {
        return fn (Builder $messages): Builder => $messages->where(fn (Builder $shown) => $shown
            ->where($messages->qualifyColumn('created_at'), '>', $sentAfter)
            ->orWhereExists(fn (QueryBuilder $receipts) => $this->unreadReceipts($receipts, $messages->qualifyColumn('id'))
                ->where('notifications.user_id', $userId)));
    }

    /** Narrows a subquery to the unread receipts of the message whose id is in `$messageIdColumn`. */
    private function unreadReceipts(QueryBuilder $receipts, string $messageIdColumn): QueryBuilder
    {
        return $receipts->from('notifications')
            ->where('notifications.type', Notification::ROOM_MESSAGE)
            ->whereNull('notifications.read_at')
            ->whereColumn('notifications.data->message_id', $messageIdColumn);
    }

    /**
     * @return Builder<Notification>
     */
    private function roomReceipts(DiscussionRoom $room): Builder
    {
        return Notification::query()->where('type', Notification::ROOM_MESSAGE)->where('data->room_id', $room->id);
    }

    /**
     * @param  Collection<int, Notification>  $receipts
     * @return list<int>
     */
    private function messageIdsOf(Collection $receipts): array
    {
        return $receipts->map(fn (Notification $receipt): int => (int) $receipt->data['message_id'])->unique()->values()->all();
    }
}
