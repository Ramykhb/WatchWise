<?php

namespace App\MovieList\DTOs\Requests;

/** One page of every member's lists for staff, optionally narrowed by text, visibility, owner or open reports. */
final readonly class StaffListQuery
{
    /** What a list can be: the app only knows public and private lists (followers-only is a profile setting). */
    public const VISIBILITIES = ['public', 'private'];

    public const MAX_PER_PAGE = 50;

    /**
     * @param  'public'|'private'|null  $visibility
     * @param  bool|null  $reported  true for lists with an open report, false for those without, null for both
     */
    public function __construct(
        public string $search = '',
        public ?string $visibility = null,
        public ?int $ownerId = null,
        public ?bool $reported = null,
        public int $page = 1,
        public int $perPage = 20,
    ) {}

    /**
     * @param  array{q?: string|null, visibility?: string|null, owner_id?: int|string|null, reported?: bool|int|string|null, page?: int|string, per_page?: int|string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            trim((string) ($validated['q'] ?? '')),
            $validated['visibility'] ?? null,
            isset($validated['owner_id']) ? (int) $validated['owner_id'] : null,
            isset($validated['reported']) ? filter_var($validated['reported'], FILTER_VALIDATE_BOOLEAN) : null,
            max(1, (int) ($validated['page'] ?? 1)),
            min(self::MAX_PER_PAGE, max(1, (int) ($validated['per_page'] ?? 20))),
        );
    }
}
