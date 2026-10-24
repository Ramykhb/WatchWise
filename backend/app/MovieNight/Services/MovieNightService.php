<?php

namespace App\MovieNight\Services;

use App\Models\MovieNight;
use App\Models\User;
use App\MovieNight\DTOs\Requests\CreateMovieNightData;
use App\MovieNight\DTOs\Requests\InviteGuestsData;
use App\MovieNight\DTOs\Requests\RsvpData;
use App\MovieNight\DTOs\Requests\UpdateMovieNightData;
use App\MovieNight\DTOs\Requests\VoteData;
use App\MovieNight\DTOs\Responses\MovieNightOverviewResponse;
use App\MovieNight\DTOs\Responses\MovieNightResponse;
use App\MovieNight\Exceptions\DeclinedGuestCannotVote;
use App\MovieNight\Exceptions\NotInvitedToMovieNight;
use App\MovieNight\Exceptions\VotingClosed;
use App\MovieNight\InterfaceRepository\MovieNightRepositoryInterface;
use App\MovieNight\InterfaceServices\MovieNightServiceInterface;
use App\Services\Notifier;
use Illuminate\Support\Facades\DB;

class MovieNightService implements MovieNightServiceInterface
{
    public function __construct(
        private readonly MovieNightRepositoryInterface $nights,
        private readonly Notifier $notifier,
    ) {}

    public function overview(User $viewer): MovieNightOverviewResponse
    {
        $id = $viewer->id;
        $nights = $this->nights->forUser($id);

        $cutoff = now()->subHours(6);
        $pending = fn (MovieNight $night) => $night->invites->firstWhere('user_id', $id)?->status === 'pending';
        $declined = fn (MovieNight $night) => $night->invites->firstWhere('user_id', $id)?->status === 'declined';
        $present = fn (MovieNight $night) => MovieNightResponse::fromModel($night, $id);

        return new MovieNightOverviewResponse(
            upcoming: $nights->filter(fn ($n) => $n->scheduled_at >= $cutoff && ! $pending($n) && ! $declined($n))
                ->map($present)->values()->all(),
            invitations: $nights->filter(fn ($n) => $n->scheduled_at >= $cutoff && $pending($n))
                ->map($present)->values()->all(),
            past: $nights->filter(fn ($n) => $n->scheduled_at < $cutoff && ! $declined($n))
                ->sortByDesc('scheduled_at')->map($present)->values()->all(),
        );
    }

    public function create(CreateMovieNightData $data, User $organizer): MovieNightResponse
    {
        $night = DB::transaction(function () use ($data, $organizer) {
            $night = $this->nights->create([
                'organizer_id' => $organizer->id,
                'title' => $data->title,
                'scheduled_at' => $data->scheduledAt,
                'location' => $data->location,
                'meeting_link' => $data->meetingLink,
                'note' => $data->note,
            ]);
            foreach ($data->tmdbIds as $tmdbId) {
                $this->nights->addCandidate($night, $tmdbId, $organizer->id);
            }

            return $night;
        });

        $this->inviteUsers($night, $data->invitees, $organizer);

        return $this->present($night, $organizer->id);
    }

    public function show(MovieNight $night, User $viewer): MovieNightResponse
    {
        return $this->present($night, $viewer->id);
    }

    public function invite(MovieNight $night, InviteGuestsData $data, User $organizer): MovieNightResponse
    {
        $this->inviteUsers($night, $data->invitees, $organizer, $this->nights->invitedUserIds($night));

        return $this->present($night, $organizer->id);
    }

    public function rsvp(MovieNight $night, RsvpData $data, User $guest): MovieNightResponse
    {
        $invite = $this->nights->findInvite($night, $guest->id);

        if ($invite === null) {
            throw new NotInvitedToMovieNight;
        }

        if ($invite->status !== $data->status) {
            $this->nights->updateInviteStatus($invite, $data->status);
            $this->notifier->answered($guest, $this->nights->loadOrganizer($night), $data->status);
        }

        return $this->present($night, $guest->id);
    }

    public function vote(MovieNight $night, VoteData $data, User $voter): MovieNightResponse
    {
        $invite = $this->nights->findInvite($night, $voter->id);

        if ($night->organizer_id !== $voter->id && $invite?->status === 'declined') {
            throw new DeclinedGuestCannotVote;
        }

        // The host closes voting by confirming the night (with a winner) or cancels it.
        if ($night->status !== 'pending' || $night->winning_tmdb_id !== null) {
            throw new VotingClosed;
        }

        DB::transaction(fn () => $this->nights->replaceVote($night, $data->tmdbId, $voter->id));
        $this->notifier->voted($voter, $night);

        return $this->present($night, $voter->id);
    }

    public function update(MovieNight $night, UpdateMovieNightData $data, User $organizer): MovieNightResponse
    {
        $this->nights->update($night, $data->attributes);

        return $this->present($night, $organizer->id);
    }

    /**
     * Invites each @username except the host and anyone in `$alreadyInvited`, and tells them.
     *
     * @param  list<string>  $usernames
     * @param  list<int>  $alreadyInvited
     */
    private function inviteUsers(MovieNight $night, array $usernames, User $organizer, array $alreadyInvited = []): void
    {
        $this->nights->findUsersByUsername($usernames, $organizer->id, $alreadyInvited)
            ->each(function (User $guest) use ($night, $organizer) {
                $this->nights->addInvite($night, $guest->id);
                $this->notifier->invited($organizer, $guest, $night);
            });
    }

    private function present(MovieNight $night, int $viewerId): MovieNightResponse
    {
        return MovieNightResponse::fromModel($this->nights->loadRelations($night), $viewerId);
    }
}
