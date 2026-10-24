<?php

namespace App\Http\Controllers;

use App\Auth\CurrentUser\CurrentUser;
use App\Http\Resources\MemberResource;
use App\Models\MovieNight;
use App\Models\MovieNightInvite;
use App\Models\User;
use App\Services\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Movie nights the user hosts or is invited to: guests, RSVPs, the shortlist and votes. */
class MovieNightController extends Controller
{
    private const RELATIONS = ['organizer', 'invites.user', 'candidates.addedBy', 'votes.user'];

    public function __construct(private readonly Notifier $notifier) {}

    /** GET /api/movie-nights — upcoming, pending invitations and past nights. */
    public function index(CurrentUser $current): JsonResponse
    {
        $id = $current->id();
        $nights = MovieNight::with(self::RELATIONS)
            ->where(fn ($q) => $q->where('organizer_id', $id)->orWhereHas('invites', fn ($i) => $i->where('user_id', $id)))
            ->where('status', '!=', 'cancelled')
            ->orderBy('scheduled_at')
            ->get();

        $cutoff = now()->subHours(6);
        $pending = fn (MovieNight $night) => $night->invites->firstWhere('user_id', $id)?->status === 'pending';
        $declined = fn (MovieNight $night) => $night->invites->firstWhere('user_id', $id)?->status === 'declined';

        return response()->json([
            'upcoming' => $nights->filter(fn ($n) => $n->scheduled_at >= $cutoff && ! $pending($n) && ! $declined($n))
                ->map(fn ($n) => $this->present($n, $id))->values(),
            'invitations' => $nights->filter(fn ($n) => $n->scheduled_at >= $cutoff && $pending($n))
                ->map(fn ($n) => $this->present($n, $id))->values(),
            'past' => $nights->filter(fn ($n) => $n->scheduled_at < $cutoff && ! $declined($n))
                ->sortByDesc('scheduled_at')->map(fn ($n) => $this->present($n, $id))->values(),
        ]);
    }

    /** POST /api/movie-nights — invites people by @username and seeds the shortlist. */
    public function store(Request $request, CurrentUser $current): JsonResponse
    {
        Gate::authorize('create', MovieNight::class);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:80'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'location' => ['nullable', 'string', 'max:255'],
            'meeting_link' => ['nullable', 'url', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
            'invitees' => ['sometimes', 'array', 'max:30'],
            'invitees.*' => ['string', 'distinct', Rule::exists('users', 'username')],
            'tmdb_ids' => ['sometimes', 'array', 'max:10'],
            'tmdb_ids.*' => ['integer', 'min:1', 'distinct'],
        ]);
        $me = $current->user();

        $night = DB::transaction(function () use ($data, $me) {
            $night = MovieNight::create([
                'organizer_id' => $me->id,
                'title' => $data['title'],
                'scheduled_at' => $data['scheduled_at'],
                'location' => $data['location'] ?? null,
                'meeting_link' => $data['meeting_link'] ?? null,
                'note' => $data['note'] ?? null,
            ]);
            foreach ($data['tmdb_ids'] ?? [] as $tmdbId) {
                $night->candidates()->create(['tmdb_id' => $tmdbId, 'added_by' => $me->id]);
            }

            return $night;
        });

        User::whereIn('username', $data['invitees'] ?? [])->whereKeyNot($me->id)->get()
            ->each(function (User $guest) use ($night, $me) {
                $night->invites()->create(['user_id' => $guest->id]);
                $this->notifier->invited($me, $guest, $night);
            });

        return response()->json(['night' => $this->present($night->load(self::RELATIONS), $me->id)], 201);
    }

    /** GET /api/movie-nights/{night} */
    public function show(MovieNight $night, CurrentUser $current): JsonResponse
    {
        Gate::authorize('view', $night);

        return response()->json(['night' => $this->present($night->load(self::RELATIONS), $current->id())]);
    }

    /** POST /api/movie-nights/{night}/invites — the host invites more people by @username. */
    public function invite(Request $request, MovieNight $night, CurrentUser $current): JsonResponse
    {
        Gate::authorize('update', $night);
        $usernames = $request->validate([
            'invitees' => ['required', 'array', 'min:1', 'max:30'],
            'invitees.*' => ['string', 'distinct', Rule::exists('users', 'username')],
        ])['invitees'];
        $me = $current->user();
        $already = $night->invites()->pluck('user_id')->all();

        User::whereIn('username', $usernames)->whereKeyNot($me->id)->whereNotIn('id', $already)->get()
            ->each(function (User $guest) use ($night, $me) {
                $night->invites()->create(['user_id' => $guest->id]);
                $this->notifier->invited($me, $guest, $night);
            });

        return response()->json(['night' => $this->present($night->load(self::RELATIONS), $me->id)]);
    }

    /** PUT /api/movie-nights/{night}/rsvp — an invited guest answers. */
    public function rsvp(Request $request, MovieNight $night, CurrentUser $current): JsonResponse
    {
        $status = $request->validate(['status' => ['required', Rule::in(['accepted', 'maybe', 'declined'])]])['status'];
        $invite = $night->invites()->where('user_id', $current->id())->first();
        abort_if($invite === null, 403, 'You are not invited to this movie night.');

        if ($invite->status !== $status) {
            $invite->update(['status' => $status]);
            $this->notifier->answered($current->user(), $night->load('organizer'), $status);
        }

        return response()->json(['night' => $this->present($night->load(self::RELATIONS), $current->id())]);
    }

    /** PUT /api/movie-nights/{night}/vote — one vote per person; adds the film to the shortlist if new. */
    public function vote(Request $request, MovieNight $night, CurrentUser $current): JsonResponse
    {
        Gate::authorize('view', $night);
        $tmdbId = $request->validate(['tmdb_id' => ['required', 'integer', 'min:1']])['tmdb_id'];
        $id = $current->id();
        $invite = $night->invites()->where('user_id', $id)->first();
        abort_if($night->organizer_id !== $id && $invite?->status === 'declined', 403, 'Declined guests cannot vote.');

        DB::transaction(function () use ($night, $tmdbId, $id) {
            $night->candidates()->firstOrCreate(['tmdb_id' => $tmdbId], ['added_by' => $id]);
            $night->votes()->where('user_id', $id)->delete();
            $night->votes()->create(['tmdb_id' => $tmdbId, 'user_id' => $id]);
        });
        $this->notifier->voted($current->user(), $night);

        return response()->json(['night' => $this->present($night->load(self::RELATIONS), $id)]);
    }

    /** PATCH /api/movie-nights/{night} — the host picks the winner, cancels or edits details. */
    public function update(Request $request, MovieNight $night, CurrentUser $current): JsonResponse
    {
        Gate::authorize('update', $night);
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:80'],
            'scheduled_at' => ['sometimes', 'date'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meeting_link' => ['sometimes', 'nullable', 'url', 'max:255'],
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
            'status' => ['sometimes', Rule::in(['pending', 'confirmed', 'cancelled'])],
            'winning_tmdb_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);
        $night->update($data);

        return response()->json(['night' => $this->present($night->load(self::RELATIONS), $current->id())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(MovieNight $night, ?int $viewerId): array
    {
        $votes = $night->votes->groupBy('tmdb_id');
        $mine = $night->invites->firstWhere('user_id', $viewerId);

        return [
            'id' => $night->id,
            'title' => $night->title,
            'scheduled_at' => $night->scheduled_at->toJSON(),
            'location' => $night->location,
            'meeting_link' => $night->meeting_link,
            'note' => $night->note,
            'status' => $night->status,
            'winning_tmdb_id' => $night->winning_tmdb_id,
            'organizer' => new MemberResource($night->organizer),
            'is_organizer' => $night->organizer_id === $viewerId,
            'my_rsvp' => $night->organizer_id === $viewerId ? 'host' : ($mine?->status ?? 'pending'),
            'my_vote' => $night->votes->firstWhere('user_id', $viewerId)?->tmdb_id,
            'guests' => $night->invites->map(fn (MovieNightInvite $invite) => [
                'user' => new MemberResource($invite->user),
                'status' => $invite->status,
            ])->values(),
            'candidates' => $night->candidates
                ->sortByDesc(fn ($candidate) => $votes->get($candidate->tmdb_id)?->count() ?? 0)
                ->map(fn ($candidate) => [
                    'tmdb_id' => $candidate->tmdb_id,
                    'added_by' => $candidate->addedBy ? new MemberResource($candidate->addedBy) : null,
                    'voters' => MemberResource::collection(($votes->get($candidate->tmdb_id) ?? collect())->pluck('user')->filter()->values()),
                ])->values(),
        ];
    }
}
