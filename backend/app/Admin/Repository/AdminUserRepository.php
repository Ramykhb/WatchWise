<?php

namespace App\Admin\Repository;

use App\Admin\DTOs\Requests\AdminUserQuery;
use App\Admin\InterfaceRepository\AdminUserRepositoryInterface;
use App\Models\User;
use App\Support\LikePattern;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminUserRepository implements AdminUserRepositoryInterface
{
    public function paginate(AdminUserQuery $query): LengthAwarePaginator
    {
        return User::query()
            ->when($query->search !== '', function ($builder) use ($query) {
                $pattern = LikePattern::contains($query->search);

                $builder->where(fn ($match) => $match
                    ->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("username LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("email LIKE ? ESCAPE '!'", [$pattern]));
            })
            ->when($query->role !== null, fn ($builder) => $builder->where('role', $query->role))
            ->when($query->status !== null, fn ($builder) => $builder->where('is_active', $query->status === 'active'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($query->perPage, ['*'], 'page', $query->page);
    }

    public function update(User $user, array $changes): User
    {
        // Role and active state are not mass-assignable on purpose; only admin code sets them.
        $user->forceFill($changes)->save();

        return $user;
    }

    public function delete(User $user): void
    {
        $user->delete();
    }
}
