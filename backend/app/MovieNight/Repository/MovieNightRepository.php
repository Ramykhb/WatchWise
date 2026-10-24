<?php

namespace App\MovieNight\Repository;

use App\Models\MovieNight;
use App\Models\MovieNightInvite;
use App\Models\User;
use App\MovieNight\InterfaceRepository\MovieNightRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class MovieNightRepository implements MovieNightRepositoryInterface
{
    private const RELATIONS = ['organizer', 'invites.user', 'candidates.addedBy', 'votes.user'];

    public function forUser(int $userId): Collection
    {
        return MovieNight::with(self::RELATIONS)
            ->where(fn ($query) => $query->where('organizer_id', $userId)->orWhereHas('invites', fn ($invites) => $invites->where('user_id', $userId)))
            ->where('status', '!=', 'cancelled')
            ->orderBy('scheduled_at')
            ->get();
    }

    public function loadRelations(MovieNight $night): MovieNight
    {
        return $night->load(self::RELATIONS);
    }

    public function loadOrganizer(MovieNight $night): MovieNight
    {
        return $night->load('organizer');
    }

    public function create(array $attributes): MovieNight
    {
        return MovieNight::create($attributes);
    }

    public function update(MovieNight $night, array $attributes): void
    {
        $night->update($attributes);
    }

    public function addCandidate(MovieNight $night, int $tmdbId, int $addedBy): void
    {
        $night->candidates()->create(['tmdb_id' => $tmdbId, 'added_by' => $addedBy]);
    }

    public function findUsersByUsername(array $usernames, int $exceptUserId, array $excludeUserIds = []): Collection
    {
        return User::whereIn('username', $usernames)
            ->whereKeyNot($exceptUserId)
            ->when($excludeUserIds !== [], fn ($query) => $query->whereNotIn('id', $excludeUserIds))
            ->get();
    }

    public function invitedUserIds(MovieNight $night): array
    {
        return $night->invites()->pluck('user_id')->all();
    }

    public function addInvite(MovieNight $night, int $userId): void
    {
        $night->invites()->create(['user_id' => $userId]);
    }

    public function findInvite(MovieNight $night, int $userId): ?MovieNightInvite
    {
        return $night->invites()->where('user_id', $userId)->first();
    }

    public function updateInviteStatus(MovieNightInvite $invite, string $status): void
    {
        $invite->update(['status' => $status]);
    }

    public function replaceVote(MovieNight $night, int $tmdbId, int $userId): void
    {
        $night->candidates()->firstOrCreate(['tmdb_id' => $tmdbId], ['added_by' => $userId]);
        $night->votes()->where('user_id', $userId)->delete();
        $night->votes()->create(['tmdb_id' => $tmdbId, 'user_id' => $userId]);
    }
}
