<?php

namespace App\Admin\InterfaceRepository;

use App\Admin\DTOs\Requests\AdminUserQuery;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface AdminUserRepositoryInterface
{
    /** @return LengthAwarePaginator<int, User> newest first */
    public function paginate(AdminUserQuery $query): LengthAwarePaginator;

    /**
     * @param  array{role?: string, is_active?: bool}  $changes
     */
    public function update(User $user, array $changes): User;

    public function delete(User $user): void;
}
