<?php

namespace App\Admin\DTOs\Responses;

use App\Models\AdminAuditLog;
use JsonSerializable;

/**
 * One entry of the audit log. The actor's role and the subject's label are the ones recorded at the time. The actor's
 * id, name and username are null once that account is deleted, and for an entry the system made itself (role `system`).
 */
final readonly class AdminAuditLogResponse implements JsonSerializable
{
    /**
     * @param  array<string, mixed>|null  $changes
     */
    public function __construct(
        public int $id,
        public string $action,
        public ?int $actorId,
        public ?string $actorName,
        public ?string $actorUsername,
        public string $actorRole,
        public string $subjectType,
        public ?int $subjectId,
        public ?string $subjectLabel,
        public ?array $changes,
        public ?string $createdAt,
    ) {}

    /** Needs the entry's `actor` loaded; it is null once that account is deleted. */
    public static function fromModel(AdminAuditLog $log): self
    {
        return new self(
            id: $log->id,
            action: $log->action->value,
            actorId: $log->actor_id,
            actorName: $log->actor?->name,
            actorUsername: $log->actor?->username,
            actorRole: $log->actor_role,
            subjectType: $log->subject_type->value,
            subjectId: $log->subject_id,
            subjectLabel: $log->subject_label,
            changes: $log->changes,
            createdAt: $log->created_at?->toJSON(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'actor' => [
                'id' => $this->actorId,
                'name' => $this->actorName,
                'username' => $this->actorUsername,
                'role' => $this->actorRole,
            ],
            'subject' => [
                'type' => $this->subjectType,
                'id' => $this->subjectId,
                'label' => $this->subjectLabel,
            ],
            'changes' => $this->changes,
            'created_at' => $this->createdAt,
        ];
    }
}
