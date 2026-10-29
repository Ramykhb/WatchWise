<?php

namespace App\Admin\DTOs\Requests;

/** One page of the user list, optionally filtered by text, role or account status. */
final readonly class AdminUserQuery
{
    public const ROLES = ['user', 'moderator', 'admin'];

    public const STATUSES = ['active', 'unverified', 'inactive'];

    public const MAX_PER_PAGE = 50;

    /**
     * @param  'user'|'moderator'|'admin'|null  $role
     * @param  'active'|'unverified'|'inactive'|null  $status
     */
    public function __construct(
        public string $search = '',
        public ?string $role = null,
        public ?string $status = null,
        public int $page = 1,
        public int $perPage = 20,
    ) {}

    /**
     * @param  array{q?: string|null, role?: string|null, status?: string|null, page?: int|string, per_page?: int|string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            trim((string) ($validated['q'] ?? '')),
            $validated['role'] ?? null,
            $validated['status'] ?? null,
            max(1, (int) ($validated['page'] ?? 1)),
            min(self::MAX_PER_PAGE, max(1, (int) ($validated['per_page'] ?? 20))),
        );
    }
}
