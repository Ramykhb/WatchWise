<?php

namespace App\MovieNight\DTOs\Responses;

use App\Http\Resources\MemberResource;
use App\Models\MovieNight;
use App\Models\MovieNightInvite;
use JsonSerializable;

/** A movie night as one viewer sees it: guests, shortlist with voters, and the viewer's own RSVP and vote. */
final readonly class MovieNightResponse implements JsonSerializable
{
    /**
     * @param  array<string, mixed>  $payload
     */
    private function __construct(private array $payload) {}

    /** Expects the organizer, invites.user, candidates.addedBy and votes.user relations to be loaded. */
    public static function fromModel(MovieNight $night, ?int $viewerId): self
    {
        $votes = $night->votes->groupBy('tmdb_id');
        $mine = $night->invites->firstWhere('user_id', $viewerId);

        return new self([
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
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->payload;
    }
}
