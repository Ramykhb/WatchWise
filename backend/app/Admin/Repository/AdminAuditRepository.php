<?php

namespace App\Admin\Repository;

use App\Admin\DTOs\Requests\AdminAuditQuery;
use App\Admin\InterfaceRepository\AdminAuditRepositoryInterface;
use App\Admin\Support\AdminAction;
use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminAuditRepository implements AdminAuditRepositoryInterface
{
    /** The `actor_role` of an entry the system itself made. */
    public const SYSTEM_ROLE = 'system';

    public function insert(?User $actor, AdminAction $action, ?int $subjectId, ?string $subjectLabel, ?array $changes): void
    {
        AdminAuditLog::query()->forceCreate([
            'actor_id' => $actor?->id,
            'actor_role' => $actor === null ? self::SYSTEM_ROLE : $actor->role,
            'action' => $action,
            'subject_type' => $action->subjectType(),
            'subject_id' => $subjectId,
            'subject_label' => $subjectLabel,
            'changes' => $changes,
        ]);
    }

    public function paginate(AdminAuditQuery $query): LengthAwarePaginator
    {
        return AdminAuditLog::query()
            ->with('actor:id,name,username')
            ->when($query->action !== null, fn ($builder) => $builder->where('action', $query->action))
            ->when($query->actorId !== null, fn ($builder) => $builder->where('actor_id', $query->actorId))
            ->when($query->subjectType !== null, fn ($builder) => $builder->where('subject_type', $query->subjectType))
            ->when($query->subjectId !== null, fn ($builder) => $builder->where('subject_id', $query->subjectId))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($query->perPage, ['*'], 'page', $query->page);
    }
}
