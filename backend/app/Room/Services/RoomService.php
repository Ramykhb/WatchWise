<?php

namespace App\Room\Services;

use App\Models\DiscussionRoom;
use App\Models\Message;
use App\Models\Notification;
use App\Models\User;
use App\Room\DTOs\Requests\CreateRoomData;
use App\Room\DTOs\Requests\InviteToRoomData;
use App\Room\DTOs\Requests\ReadRoomMessagesData;
use App\Room\DTOs\Requests\SendRoomMessageData;
use App\Room\DTOs\Responses\ReadReceiptsResponse;
use App\Room\DTOs\Responses\RoomListResponse;
use App\Room\DTOs\Responses\RoomMessageResponse;
use App\Room\DTOs\Responses\RoomMessagesResponse;
use App\Room\DTOs\Responses\RoomResponse;
use App\Room\Events\RoomMessageSent;
use App\Room\Events\RoomMessagesRead;
use App\Room\Events\RoomsChanged;
use App\Room\InterfaceRepository\RoomRepositoryInterface;
use App\Room\InterfaceServices\RoomServiceInterface;
use Carbon\CarbonInterface;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RoomService implements RoomServiceInterface
{
    /** How long a message is shown to everyone who can see its room; after that only to the members who have yet to read it. */
    //change to 24, 2 is for testing
    private const MESSAGE_LIFETIME_HOURS = 2;

    public function __construct(private readonly RoomRepositoryInterface $rooms) {}

    public function list(User $viewer): RoomListResponse
    {
        $unread = $this->rooms->unreadCounts($viewer->id);

        $rooms = $this->rooms->visibleTo($viewer->id, $this->lifetimeCutoff())
            ->map(fn (DiscussionRoom $room): RoomResponse => RoomResponse::fromModel($room, $viewer->id, $unread[$room->id] ?? 0))
            ->sort(fn (RoomResponse $a, RoomResponse $b): int => [$b->joined(), $b->invited(), $b->lastActivity()] <=> [$a->joined(), $a->invited(), $a->lastActivity()])
            ->values()
            ->all();

        return new RoomListResponse($rooms);
    }

    public function create(CreateRoomData $data, User $creator): RoomResponse
    {
        $room = DB::transaction(function () use ($data, $creator): DiscussionRoom {
            $room = $this->rooms->create([
                'name' => $data->name,
                'tmdb_id' => $data->tmdbId,
                'created_by' => $creator->id,
                'spoilers' => $data->spoilers,
                'is_private' => $data->isPrivate,
            ]);
            $this->rooms->addMember($room, $creator->id);

            return $room;
        });

        // A new public room shows up in everyone's list; a private one only concerns its creator so far.
        if (! $room->is_private) {
            $this->broadcast(new RoomsChanged($room->id, $this->listChannels($room)));
        }

        return RoomResponse::fromModel($this->rooms->loadDetail($room, $creator->id, $this->lifetimeCutoff()), $creator->id, 0);
    }

    public function join(DiscussionRoom $room, User $user): RoomResponse
    {
        DB::transaction(function () use ($room, $user): void {
            $this->rooms->addMember($room, $user->id);
            $this->rooms->removeInvite($room, $user->id);
        });

        $this->broadcast(new RoomsChanged($room->id, $this->listChannels($room)));

        return $this->present($room, $user);
    }

    public function invite(DiscussionRoom $room, InviteToRoomData $data, User $inviter): RoomResponse
    {
        $alreadyIn = [...$this->rooms->memberIds($room), ...$this->rooms->inviteeIds($room)];
        $invitees = $this->rooms->usersByUsername($data->usernames, $alreadyIn);

        if ($invitees->isNotEmpty()) {
            $this->rooms->addInvites($room, $invitees->modelKeys(), $inviter->id);
            $this->broadcast(new RoomsChanged($room->id, $this->listChannels($room)));
        }

        return $this->present($room, $inviter);
    }

    public function decline(DiscussionRoom $room, User $user): void
    {
        $this->rooms->removeInvite($room, $user->id);

        // The decliner is no longer an invitee, but their own list (other tabs) has to drop the room too.
        $this->broadcast(new RoomsChanged($room->id, $this->listChannels($room, [$user->id])));
    }

    public function messages(DiscussionRoom $room, User $viewer): RoomMessagesResponse
    {
        $cutoff = $this->lifetimeCutoff();
        $messages = $this->rooms->storedMessages($room);
        /** @var Collection<int, Collection<int, Notification>> $receipts by message id */
        $receipts = $this->rooms->receipts($room)->groupBy(fn (Notification $receipt): int => (int) $receipt->data['message_id']);

        // Clean-up: receipts of messages that are gone (e.g. their author deleted their account), and messages whose
        // 24 hours are up that nobody is waiting on any more (e.g. the last reader deleted theirs) and the scheduled
        // prune hasn't reached yet.
        $stale = $receipts->keys()->diff($messages->modelKeys())
            ->merge($messages->filter(fn (Message $message): bool => $this->hasExpired($message, $cutoff) && $this->waitingFor($message, $receipts) === [])->modelKeys())
            ->all();
        $this->rooms->deleteMessages($room, array_values($stale));

        $users = $this->rooms->usersByIds($receipts->flatten(1)->pluck('user_id')->unique()->values()->all());

        return new RoomMessagesResponse($messages
            ->reject(fn (Message $message): bool => in_array($message->id, $stale, true))
            ->filter(fn (Message $message): bool => $this->isShownTo($viewer, $message, $this->waitingFor($message, $receipts), $cutoff))
            ->map(fn (Message $message): RoomMessageResponse => new RoomMessageResponse(
                $message,
                [$message->user, ...$this->users($users, $this->readers($message, $receipts))],
                $this->users($users, $this->waitingFor($message, $receipts)),
            ))
            ->values()
            ->all());
    }

    public function send(DiscussionRoom $room, SendRoomMessageData $data, User $sender): RoomMessageResponse
    {
        [$message, $recipientIds] = DB::transaction(function () use ($room, $data, $sender): array {
            $recipientIds = array_values(array_diff($this->rooms->memberIds($room), [$sender->id]));
            $message = $this->rooms->createMessage($room, $sender->id, $data->content);
            $this->rooms->createReceipts($message, $recipientIds);

            return [$message, $recipientIds];
        });

        $response = new RoomMessageResponse($message, [$sender], $this->users($this->rooms->usersByIds($recipientIds), $recipientIds));
        $this->broadcast(new RoomMessageSent($response, $this->listChannels($room)));

        return $response;
    }

    public function read(DiscussionRoom $room, ReadRoomMessagesData $data, User $reader): ReadReceiptsResponse
    {
        $readIds = $this->rooms->markRead($room, $reader->id, $data->messageIds);

        if ($readIds === []) {
            return new ReadReceiptsResponse([], []);
        }

        // Checked after the reader's receipts are saved, so when two members read the last copies at the same
        // time at least one of them sees nothing left and deletes the message. One read by everyone within its
        // 24 hours stays until they are up (see pruneMessages()).
        $deletedIds = $this->rooms->fullyRead($room, $readIds, $this->lifetimeCutoff());
        $this->rooms->deleteMessages($room, $deletedIds);

        $this->broadcast(new RoomMessagesRead($room->id, $reader, $readIds, $deletedIds));

        return new ReadReceiptsResponse($readIds, $deletedIds);
    }

    public function pruneMessages(): int
    {
        return $this->rooms->deleteFullyRead($this->lifetimeCutoff());
    }

    /**
     * Members who have read the message, in the order they read it (the author is not among them).
     *
     * @param  Collection<int, Collection<int, Notification>>  $receipts
     * @return list<int>
     */
    private function readers(Message $message, Collection $receipts): array
    {
        return $receipts->get($message->id, collect())
            ->filter(fn (Notification $receipt): bool => $receipt->read_at !== null)
            ->map(fn (Notification $receipt): int => (int) $receipt->user_id)
            ->values()
            ->all();
    }

    /**
     * Members who still have to read the message.
     *
     * @param  Collection<int, Collection<int, Notification>>  $receipts
     * @return list<int>
     */
    private function waitingFor(Message $message, Collection $receipts): array
    {
        return $receipts->get($message->id, collect())
            ->filter(fn (Notification $receipt): bool => $receipt->read_at === null)
            ->map(fn (Notification $receipt): int => (int) $receipt->user_id)
            ->values()
            ->all();
    }

    /**
     * Whether the viewer still sees the message: everyone who can see the room does until its lifetime is up,
     * then only the members who have yet to read it.
     *
     * @param  list<int>  $waitingFor
     */
    private function isShownTo(User $viewer, Message $message, array $waitingFor, CarbonInterface $cutoff): bool
    {
        return ! $this->hasExpired($message, $cutoff) || in_array($viewer->id, $waitingFor, true);
    }

    /** Whether the message's 24 hours are up. */
    private function hasExpired(Message $message, CarbonInterface $cutoff): bool
    {
        return ! $message->created_at->isAfter($cutoff);
    }

    /** Messages sent after this moment are still within their lifetime. */
    private function lifetimeCutoff(): CarbonInterface
    {
        //change to subHours in real, currently testing
        return now()->subMinutes(self::MESSAGE_LIFETIME_HOURS);
    }

    /**
     * @param  Collection<int, User>  $users  keyed by id
     * @param  list<int>  $ids
     * @return list<User>
     */
    private function users(Collection $users, array $ids): array
    {
        return array_values(array_filter(array_map(fn (int $id): ?User => $users->get($id), $ids)));
    }

    private function present(DiscussionRoom $room, User $viewer): RoomResponse
    {
        return RoomResponse::fromModel(
            $this->rooms->loadDetail($room, $viewer->id, $this->lifetimeCutoff()),
            $viewer->id,
            $this->rooms->unreadCount($room, $viewer->id),
        );
    }

    /**
     * Who hears about a room in their Rooms list: everyone for a public room (`rooms.public`), only the members
     * and invitees of a private one (each on `rooms.user.{id}`), plus anyone else named.
     *
     * @param  list<int>  $alsoUserIds
     * @return list<PrivateChannel>
     */
    private function listChannels(DiscussionRoom $room, array $alsoUserIds = []): array
    {
        if (! $room->is_private) {
            return [new PrivateChannel('rooms.public')];
        }

        $userIds = array_unique([...$this->rooms->memberIds($room), ...$this->rooms->inviteeIds($room), ...$alsoUserIds]);
        sort($userIds);

        return array_map(fn (int $userId): PrivateChannel => new PrivateChannel("rooms.user.{$userId}"), $userIds);
    }

    /**
     * A failed push only delays delivery: a message stays stored for its 24 hours and until every member has
     * read it, and a list catches up the next time it loads.
     */
    private function broadcast(object $event): void
    {
        rescue(fn () => event($event));
    }
}
