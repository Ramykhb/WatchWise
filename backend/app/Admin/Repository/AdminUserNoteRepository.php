<?php

namespace App\Admin\Repository;

use App\Admin\DTOs\Requests\UserNoteQuery;
use App\Admin\InterfaceRepository\AdminUserNoteRepositoryInterface;
use App\Models\AdminUserNote;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminUserNoteRepository implements AdminUserNoteRepositoryInterface
{
    public function paginate(int $userId, UserNoteQuery $query): LengthAwarePaginator
    {
        return AdminUserNote::query()
            ->with('author:id,name,username,role')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($query->perPage, ['*'], 'page', $query->page);
    }

    public function create(int $userId, int $authorId, string $body): AdminUserNote
    {
        $note = AdminUserNote::query()->create(['user_id' => $userId, 'author_id' => $authorId, 'body' => $body]);

        return $note->load('author:id,name,username,role');
    }

    public function delete(AdminUserNote $note): bool
    {
        return AdminUserNote::query()->whereKey($note->getKey())->delete() === 1;
    }

    public function countFor(int $userId): int
    {
        return AdminUserNote::query()->where('user_id', $userId)->count();
    }
}
