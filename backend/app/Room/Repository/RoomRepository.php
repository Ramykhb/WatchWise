<?php

namespace App\Room\Repository;

use App\Models\DiscussionRoom;
use App\Models\Message;
use App\Models\Notification;
use App\Models\User;
use App\Room\InterfaceRepository\RoomRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class RoomRepository implements RoomRepositoryInterface
{
    private const DETAIL = ['creator', 'members', 'latestMessage.user'];

    public function all(): Collection
    {
        return DiscussionRoom::with(self::DETAIL)->withCount('messages')->get();
    }

    public function create(array $attributes): DiscussionRoom
    {
        return DiscussionRoom::create($attributes);
    }

    public function loadDetail(DiscussionRoom $room): DiscussionRoom
    {
        return $room->load(self::DETAIL)->loadCount('messages');
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

    public function usersByIds(array $ids): Collection
    {
        return User::whereKey($ids)->get()->keyBy('id');
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

    public function fullyRead(DiscussionRoom $room, array $messageIds): array
    {
        if ($messageIds === []) {
            return [];
        }

        $waiting = $this->messageIdsOf(
            $this->roomReceipts($room)->whereNull('read_at')->whereIn('data->message_id', $messageIds)->get(['data']),
        );

        return array_values(array_diff($messageIds, $waiting));
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
