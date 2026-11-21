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
use App\Notification\InterfaceServices\NotificationServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MovieNightService implements MovieNightServiceInterface
{
    public function __construct(
        private readonly MovieNightRepositoryInterface $nights,
        private readonly NotificationServiceInterface $notifications,
    ) {}

    public function shareLink(MovieNight $night): array
    {
        return DB::transaction(function () use ($night): array {
            $night = $this->nights->lock($night);
            abort_if($night->status === 'cancelled' || $night->scheduled_at->copy()->addHours(6)->isPast(), 410, 'This movie night is no longer accepting guests.');
            if ($night->share_token === null || $night->share_expires_at?->isPast()) {
                $this->nights->update($night, ['share_token' => Str::random(64),
                    'share_expires_at' => now()->addDays(7)->min($night->scheduled_at->copy()->addHours(6))]);
            }

            return ['url' => rtrim(config('services.frontend_url'), '/').'/movie-nights/'.$night->id.'/join?token='.rawurlencode($night->share_token),
                'expires_at' => $night->share_expires_at->toJSON()];
        });
    }

    public function revokeShareLink(MovieNight $night): void
    {
        DB::transaction(function () use ($night): void {
            $this->nights->update($this->nights->lock($night), ['share_token' => null, 'share_expires_at' => null]);
        });
    }

    private function ensureShareLinkValid(MovieNight $night, string $token): void
    {
        abort_unless($night->share_token !== null && hash_equals($night->share_token, $token), 404, 'Invitation not found.');
        abort_if($night->status === 'cancelled' || $night->share_expires_at === null || $night->share_expires_at->isPast()
            || $night->scheduled_at->copy()->addHours(6)->isPast(), 410, 'This invitation has expired or the movie night was cancelled.');
    }

    public function invitation(MovieNight $night, string $token): array
    {
        $this->ensureShareLinkValid($night, $token);
        $night = $this->nights->loadRelations($night);

        // Location, meeting link, note, guest list and shortlist stay private until the member joins.
        return ['title' => $night->title, 'scheduled_at' => $night->scheduled_at->toJSON(),
            'host_name' => $night->organizer->name];
    }

    public function joinFromLink(MovieNight $night, string $token, User $guest): MovieNightResponse
    {
        $night = DB::transaction(function () use ($night, $token, $guest): MovieNight {
            $night = $this->nights->lock($night);
            $this->ensureShareLinkValid($night, $token);
            if ($night->organizer_id === $guest->id) {
                return $night;
            }
            $invite = $this->nights->findInvite($night, $guest->id);
            if ($invite === null) {
                $this->nights->addInvite($night, $guest->id);
                $invite = $this->nights->findInvite($night, $guest->id);
            }
            if ($invite->status !== 'accepted') {
                $this->nights->updateInviteStatus($invite, 'accepted');
                DB::afterCommit(fn () => $this->notifications->nightAnswered($night, $guest, 'accepted'));
            }

            return $night;
        });

        return $this->present($night, $guest->id);
    }

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
        if ($night->status === 'cancelled') {
            throw ValidationException::withMessages(['night' => 'This movie night is cancelled.']);
        }
        $this->inviteUsers($night, $data->invitees, $organizer, $this->nights->invitedUserIds($night));

        return $this->present($night, $organizer->id);
    }

    public function rsvp(MovieNight $night, RsvpData $data, User $guest): MovieNightResponse
    {
        if ($night->status === 'cancelled') {
            throw ValidationException::withMessages(['night' => 'This movie night is cancelled.']);
        }
        $invite = $this->nights->findInvite($night, $guest->id);

        if ($invite === null) {
            throw new NotInvitedToMovieNight;
        }

        if ($invite->status !== $data->status) {
            $this->nights->updateInviteStatus($invite, $data->status);

            if ($data->status !== 'pending') {
                $this->notifications->nightAnswered($night, $guest, $data->status);
            }
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
        $this->notifications->nightVoted($night, $voter, $data->tmdbId);

        return $this->present($night, $voter->id);
    }

    public function update(MovieNight $night, UpdateMovieNightData $data, User $organizer): MovieNightResponse
    {
        $before = ['status' => $night->status, 'scheduled_at' => $night->scheduled_at?->toJSON()];
        $this->nights->update($night, $data->attributes);

        $change = $this->changeGuestsHearAbout($before, $night);
        if ($change !== null) {
            $this->notifications->nightChanged($night, $change, $this->nights->attendingGuestIds($night));
        }

        return $this->present($night, $organizer->id);
    }

    /**
     * Invites each @username except the host and anyone in `$alreadyInvited`.
     *
     * @param  list<string>  $usernames
     * @param  list<int>  $alreadyInvited
     */
    private function inviteUsers(MovieNight $night, array $usernames, User $organizer, array $alreadyInvited = []): void
    {
        $guests = $this->nights->findUsersByUsername($usernames, $organizer->id, $alreadyInvited)
            ->each(fn (User $guest) => $this->nights->addInvite($night, $guest->id));

        if ($guests->isNotEmpty()) {
            $this->notifications->nightInvited($night, $organizer, $guests->modelKeys());
        }
    }

    /**
     * What the guests are told after the host's edit: the pick was locked in (confirmed), the night was cancelled,
     * voting reopened, or the time moved. Other edits (notes, place, link) don't notify anyone.
     *
     * @param  array{status: string, scheduled_at: string|null}  $before
     */
    private function changeGuestsHearAbout(array $before, MovieNight $night): ?string
    {
        if ($night->status !== $before['status']) {
            return match ($night->status) {
                'confirmed' => 'confirmed',
                'cancelled' => 'cancelled',
                default => 'reopened',
            };
        }

        return $night->scheduled_at?->toJSON() !== $before['scheduled_at'] ? 'rescheduled' : null;
    }

    private function present(MovieNight $night, int $viewerId): MovieNightResponse
    {
        return MovieNightResponse::fromModel($this->nights->loadRelations($night), $viewerId);
    }
}
