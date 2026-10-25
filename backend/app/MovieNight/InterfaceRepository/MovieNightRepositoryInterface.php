<?php

namespace App\MovieNight\InterfaceRepository;

use App\Models\MovieNight;
use App\Models\MovieNightInvite;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface MovieNightRepositoryInterface
{
    /**
     * Nights the user hosts or is invited to, except cancelled ones, soonest first, with every relation loaded.
     *
     * @return Collection<int, MovieNight>
     */
    public function forUser(int $userId): Collection;

    /** Loads the organizer, invites with their users, candidates with who added them, and votes with their users. */
    public function loadRelations(MovieNight $night): MovieNight;

    /**
     * @param  array{organizer_id: int, title: string, scheduled_at: string, location: string|null, meeting_link: string|null, note: string|null}  $attributes
     */
    public function create(array $attributes): MovieNight;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(MovieNight $night, array $attributes): void;

    public function addCandidate(MovieNight $night, int $tmdbId, int $addedBy): void;

    /**
     * Users by @username, leaving out one user (the host) and any ids already invited.
     *
     * @param  list<string>  $usernames
     * @param  list<int>  $excludeUserIds
     * @return Collection<int, User>
     */
    public function findUsersByUsername(array $usernames, int $exceptUserId, array $excludeUserIds = []): Collection;

    /**
     * @return list<int>
     */
    public function invitedUserIds(MovieNight $night): array;

    public function addInvite(MovieNight $night, int $userId): void;

    public function findInvite(MovieNight $night, int $userId): ?MovieNightInvite;

    public function updateInviteStatus(MovieNightInvite $invite, string $status): void;

    /** Puts the film on the shortlist if it is new and makes it the user's only vote. */
    public function replaceVote(MovieNight $night, int $tmdbId, int $userId): void;
}
