<?php

namespace App\Admin\DTOs\Requests;

use App\Admin\Support\AdminAction;
use App\Admin\Support\AdminSubjectType;

/** One page of the audit log, optionally narrowed to an action, a staff member or a subject. */
final readonly class AdminAuditQuery
{
    public const MAX_PER_PAGE = 50;

    public function __construct(
        public ?AdminAction $action = null,
        public ?int $actorId = null,
        public ?AdminSubjectType $subjectType = null,
        public ?int $subjectId = null,
        public int $page = 1,
        public int $perPage = 20,
    ) {}

    /**
     * @param  array{action?: string|null, actor_id?: int|string|null, subject_type?: string|null, subject_id?: int|string|null, page?: int|string, per_page?: int|string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            isset($validated['action']) ? AdminAction::from($validated['action']) : null,
            isset($validated['actor_id']) ? (int) $validated['actor_id'] : null,
            isset($validated['subject_type']) ? AdminSubjectType::from($validated['subject_type']) : null,
            isset($validated['subject_id']) ? (int) $validated['subject_id'] : null,
            max(1, (int) ($validated['page'] ?? 1)),
            min(self::MAX_PER_PAGE, max(1, (int) ($validated['per_page'] ?? 20))),
        );
    }
}
