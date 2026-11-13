<?php

namespace App\Admin\InterfaceServices;

use App\Admin\DTOs\Requests\AdminAuditQuery;
use App\Admin\DTOs\Responses\AdminPageResponse;
use App\Admin\Support\AdminAction;
use App\Models\User;

/** What staff did, kept for good: entries are added and read, never changed or removed. */
interface AdminAuditServiceInterface
{
    /**
     * Adds one entry. Call it only once the action has gone through. The actor's role is kept as it is now,
     * and `$subjectLabel` is a name to keep the entry readable after the subject is gone (never an email).
     * `$changes` maps each changed field to `['from' => ..., 'to' => ...]`, or to `['changed' => true]` for text too long to keep.
     *
     * @param  array<string, mixed>|null  $changes
     */
    public function record(User $actor, AdminAction $action, ?int $subjectId, ?string $subjectLabel, ?array $changes = null): void;

    /**
     * Adds one entry for something the system did on its own (a scheduled job): no actor, the role `system`. Same rules as record().
     *
     * @param  array<string, mixed>|null  $changes
     */
    public function recordAsSystem(AdminAction $action, ?int $subjectId, ?string $subjectLabel, ?array $changes = null): void;

    public function list(AdminAuditQuery $query): AdminPageResponse;
}
