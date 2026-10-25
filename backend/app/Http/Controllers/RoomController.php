<?php

namespace App\Http\Controllers;

use App\Auth\CurrentUser\CurrentUser;
use App\Models\DiscussionRoom;
use App\Models\Message;
use App\Models\User;
use App\Services\Notifier;
use App\User\Resources\MemberResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Discussion rooms (one per movie topic) and their messages. Rooms are open to every signed-in
 * user; a room's members are the people who have posted in it. There is no real-time channel
 * yet, so the page polls `messages?after=` while a room is open.
 */
class RoomController extends Controller
{
    public function __construct(private readonly Notifier $notifier) {}

    /** GET /api/rooms — rooms the user posted in first, then the most active. */
    public function index(CurrentUser $current): JsonResponse
    {
        $rooms = DiscussionRoom::with('creator')
            ->withCount('messages')
            ->withMax('messages', 'created_at')
            ->orderByDesc('messages_max_created_at')
            ->limit(50)
            ->get();

        $roomIds = $rooms->pluck('id');
        $joined = Message::whereIn('room_id', $roomIds)->where('user_id', $current->id())->distinct()->pluck('room_id')->all();
        $members = Message::whereIn('room_id', $roomIds)->select('room_id', 'user_id')->distinct()->get()->groupBy('room_id');
        $users = User::whereIn('id', $members->flatten()->pluck('user_id')->unique())->get()->keyBy('id');
        $last = Message::with('user')->whereIn('id', Message::whereIn('room_id', $roomIds)->selectRaw('max(id)')->groupBy('room_id'))->get()->keyBy('room_id');

        $data = $rooms->map(fn (DiscussionRoom $room) => [
            'id' => $room->id,
            'name' => $room->name,
            'tmdb_id' => $room->tmdb_id,
            'spoilers' => $room->spoilers,
            'host' => $room->creator ? new MemberResource($room->creator) : null,
            'members' => MemberResource::collection(($members->get($room->id) ?? collect())
                ->map(fn ($m) => $users->get($m->user_id))->filter()->values()),
            'joined' => in_array($room->id, $joined, true) || (int) $room->created_by === $current->id(),
            'message_count' => $room->messages_count,
            'last_message' => ($message = $last->get($room->id)) ? [
                'author' => $message->user?->name,
                'content' => $message->content,
                'created_at' => $message->created_at?->toJSON(),
            ] : null,
            'created_at' => $room->created_at?->toJSON(),
        ])->sortByDesc('joined')->values();

        return response()->json(['rooms' => $data]);
    }

    /** POST /api/rooms */
    public function store(Request $request, CurrentUser $current): JsonResponse
    {
        Gate::authorize('create', Message::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:80'],
            'tmdb_id' => ['required', 'integer', 'min:1'],
            'spoilers' => ['sometimes', 'boolean'],
        ]);

        $room = DiscussionRoom::create([...$data, 'created_by' => $current->id()]);

        return response()->json(['room' => ['id' => $room->id]], 201);
    }

    /** GET /api/rooms/{room}/messages?after={id} — the last 100, or only newer ones when polling. */
    public function messages(Request $request, DiscussionRoom $room): JsonResponse
    {
        Gate::authorize('viewAny', Message::class);
        $after = (int) $request->query('after', 0);

        $messages = $room->messages()->with('user')
            ->when($after > 0, fn ($q) => $q->where('id', '>', $after))
            ->latest('id')->limit(100)->get()->reverse()->values();

        return response()->json(['messages' => $messages->map(fn (Message $m) => $this->present($m))]);
    }

    /** POST /api/rooms/{room}/messages — also notifies anyone @mentioned. */
    public function send(Request $request, DiscussionRoom $room, CurrentUser $current): JsonResponse
    {
        Gate::authorize('create', Message::class);
        $data = $request->validate(['content' => ['required', 'string', 'max:2000']]);

        $message = $room->messages()->create(['user_id' => $current->id(), 'content' => trim($data['content'])]);
        $message->setRelation('user', $current->user());
        $this->notifier->mentioned($current->user(), $room, $message);

        return response()->json(['message' => $this->present($message->refresh()->load('user'))], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Message $message): array
    {
        return [
            'id' => $message->id,
            'user' => new MemberResource($message->user),
            'content' => $message->content,
            'created_at' => $message->created_at?->toJSON(),
        ];
    }
}
