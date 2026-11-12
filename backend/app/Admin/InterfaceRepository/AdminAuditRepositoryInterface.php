<?php

namespace App\Admin\InterfaceRepository;

use App\Admin\DTOs\Requests\AdminAuditQuery;
use App\Admin\Support\AdminAction;
use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

/** The audit log is append-only: this interface has no way to change or remove a row. */
interface AdminAuditRepositoryInterface
{
    /**
     * A null `$actor` is the system itself (a scheduled job): the entry is kept with no actor and the role `system`.
     *
     * @param  array<string, mixed>|null  $changes
     */
    public function insert(?User $actor, AdminAction $action, ?int $subjectId, ?string $subjectLabel, ?array $changes): void;

    /**
     * Newest first (ties broken by id), each with its `actor` loaded.
     *
     * @return LengthAwarePaginator<int, AdminAuditLog>
     */
    public function paginate(AdminAuditQuery $query): LengthAwarePaginator;
}
