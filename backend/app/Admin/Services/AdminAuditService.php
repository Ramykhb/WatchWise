<?php

namespace App\Admin\Services;

use App\Admin\DTOs\Requests\AdminAuditQuery;
use App\Admin\DTOs\Responses\AdminAuditLogResponse;
use App\Admin\DTOs\Responses\AdminPageResponse;
use App\Admin\InterfaceRepository\AdminAuditRepositoryInterface;
use App\Admin\InterfaceServices\AdminAuditServiceInterface;
use App\Admin\Support\AdminAction;
use App\Models\AdminAuditLog;
use App\Models\User;

class AdminAuditService implements AdminAuditServiceInterface
{
    public function __construct(private readonly AdminAuditRepositoryInterface $entries) {}

    public function record(User $actor, AdminAction $action, ?int $subjectId, ?string $subjectLabel, ?array $changes = null): void
    {
        $this->entries->insert($actor, $action, $subjectId, $subjectLabel, $changes);
    }

    public function recordAsSystem(AdminAction $action, ?int $subjectId, ?string $subjectLabel, ?array $changes = null): void
    {
        $this->entries->insert(null, $action, $subjectId, $subjectLabel, $changes);
    }

    public function list(AdminAuditQuery $query): AdminPageResponse
    {
        $paginator = $this->entries->paginate($query);

        return new AdminPageResponse(
            array_map(fn (AdminAuditLog $log): AdminAuditLogResponse => AdminAuditLogResponse::fromModel($log), $paginator->items()),
            $paginator->currentPage(),
            $paginator->lastPage(),
            $paginator->perPage(),
            $paginator->total(),
        );
    }
}
