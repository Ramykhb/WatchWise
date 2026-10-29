<?php

namespace App\Admin\InterfaceRepository;

use App\Admin\DTOs\Requests\AdminUserQuery;
use App\Models\User;
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

    /**
     * @param  array{role?: string, is_active?: bool}  $changes
     */
    public function update(User $user, array $changes): User;

    public function delete(User $user): void;
}
