<?php

namespace App\Admin\Repository;

use App\Admin\DTOs\Requests\AdminUserQuery;
use App\Admin\InterfaceRepository\AdminUserRepositoryInterface;
use App\Models\User;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

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
                    ->orWhereRaw("email LIKE ? ESCAPE '!'", [$pattern]));
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

    public function update(User $user, array $changes): User
    {
        // Role and active state are not mass-assignable on purpose; only admin code sets them.
        $user->forceFill($changes)->save();

        return $user->loadCount(['reviews' => fn (Builder $reviews) => $reviews->withText()]);
    }

    public function delete(User $user): void
    {
        $user->delete();
    }
}
