<?php

namespace App\Admin\InterfaceRepository;

use App\Admin\DTOs\Requests\AdminUserQuery;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface AdminUserRepositoryInterface
{
    /**
     * Newest first, each with its `reviews_count` (reviews with text).
     *
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(AdminUserQuery $query): LengthAwarePaginator;

    /**
     * Account counts across all users, ignoring any filter: `unverified` is an active account without a
     * verified email, so `active + unverified + inactive = total`.
     *
     * @return array{total: int, active: int, unverified: int, inactive: int, admins: int, moderators: int}
     */
    public function totals(): array;

    /** A fresh copy of the user, row-locked until the surrounding transaction ends (call inside one). */
    public function lockForUpdate(User $user): User;

    /** Like lockForUpdate(), found by id; null when no such user exists any more. */
    public function lockById(int $id): ?User;

    /**
     * @param  array{role?: string, is_active?: bool, suspension_reason?: string|null, suspended_until?: CarbonInterface|null}  $changes
     */
    public function update(User $user, array $changes): User;

    /**
     * The users with these ids, in no particular order; ids that match nobody are left out.
     *
     * @param  list<int>  $ids
     * @return Collection<int, User>
     */
    public function findMany(array $ids): Collection;

    /**
     * Ids of the suspended accounts whose end (`suspended_until`) is at or before `$now` and above `$afterId`,
     * ascending, at most `$limit`.
     *
     * @return list<int>
     */
    public function expiredSuspensionIds(CarbonInterface $now, int $afterId, int $limit): array;
}
