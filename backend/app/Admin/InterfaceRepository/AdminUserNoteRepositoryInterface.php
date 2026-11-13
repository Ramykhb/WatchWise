<?php

namespace App\Admin\InterfaceRepository;

use App\Admin\DTOs\Requests\UserNoteQuery;
use App\Models\AdminUserNote;
use Illuminate\Pagination\LengthAwarePaginator;

/** The internal notes staff keep about members. */
interface AdminUserNoteRepositoryInterface
{
    /**
     * The member's notes, newest first (ties broken by id), each with its `author` loaded.
     *
     * @return LengthAwarePaginator<int, AdminUserNote>
     */
    public function paginate(int $userId, UserNoteQuery $query): LengthAwarePaginator;

    /** The new note, with its `author` loaded. */
    public function create(int $userId, int $authorId, string $body): AdminUserNote;

    /** Deletes the note; false when it was already gone. */
    public function delete(AdminUserNote $note): bool;

    public function countFor(int $userId): int;
}
