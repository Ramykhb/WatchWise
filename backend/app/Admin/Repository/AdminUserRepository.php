<?php

namespace App\Admin\Repository;

use App\Admin\DTOs\Requests\AdminUserQuery;
use App\Admin\InterfaceRepository\AdminUserRepositoryInterface;
use App\Models\User;
use App\Support\LikePattern;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class AdminUserRepository implements AdminUserRepositoryInterface
{
    public function paginate(AdminUserQuery $query): LengthAwarePaginator
    {
        return User::query()
            ->withCount(['reviews' => fn (Builder $reviews) => $reviews->withText()])
            ->when($query->search !== '', function ($builder) use ($query) {
                $pattern = LikePattern::contains($query->search);

                $builder->where(fn ($match) => $match
                    ->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("username LIKE ? ESCAPE '!'", [$pattern])
                    // Emails are stored lowercase, so "ALI@" finds them on case-sensitive databases too.
                    ->orWhereRaw("email LIKE ? ESCAPE '!'", [Str::lower($pattern)]));
            })
            ->when($query->role !== null, fn ($builder) => $builder->where('role', $query->role))
            ->when($query->status !== null, fn ($builder) => match ($query->status) {
                'inactive' => $builder->where('is_active', false),
                'unverified' => $builder->where('is_active', true)->whereNull('email_verified_at'),
                default => $builder->where('is_active', true)->whereNotNull('email_verified_at'),
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($query->perPage, ['*'], 'page', $query->page);
    }

    public function totals(): array
    {
        $counts = User::query()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when is_active = ? and email_verified_at is not null then 1 else 0 end) as active', [true])
            ->selectRaw('sum(case when is_active = ? and email_verified_at is null then 1 else 0 end) as unverified', [true])
            ->selectRaw('sum(case when is_active = ? then 1 else 0 end) as inactive', [false])
            ->selectRaw("sum(case when role = 'admin' then 1 else 0 end) as admins")
            ->selectRaw("sum(case when role = 'moderator' then 1 else 0 end) as moderators")
            ->toBase()
            ->first();

        return [
            'total' => (int) $counts->total,
            'active' => (int) $counts->active,
            'unverified' => (int) $counts->unverified,
            'inactive' => (int) $counts->inactive,
            'admins' => (int) $counts->admins,
            'moderators' => (int) $counts->moderators,
        ];
    }

    public function lockForUpdate(User $user): User
    {
        return User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
    }

    public function lockById(int $id): ?User
    {
        return User::query()->whereKey($id)->lockForUpdate()->first();
    }

    public function update(User $user, array $changes): User
    {
        // Role and active state are not mass-assignable on purpose; only admin code sets them.
        $user->forceFill($changes)->save();

        return $user->loadCount(['reviews' => fn (Builder $reviews) => $reviews->withText()]);
    }

    public function findMany(array $ids): Collection
    {
        return User::query()->whereKey($ids)->get();
    }

    public function expiredSuspensionIds(CarbonInterface $now, int $afterId, int $limit): array
    {
        return User::query()
            ->where('is_active', false)
            ->whereNotNull('suspended_until')
            ->where('suspended_until', '<=', $now)
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }
}
