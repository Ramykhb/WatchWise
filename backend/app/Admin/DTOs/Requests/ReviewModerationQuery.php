<?php

namespace App\Admin\DTOs\Requests;

/** One page of the reviews an admin can moderate. */
final readonly class ReviewModerationQuery
{
    public const STATUSES = ['pending', 'approved'];

    public const MAX_PER_PAGE = 50;

    /**
     * @param  'pending'|'approved'  $status
     */
    public function __construct(
        public string $status = 'pending',
        public int $page = 1,
        public int $perPage = 20,
    ) {}

    /**
     * @param  array{status?: string|null, page?: int|string, per_page?: int|string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            $validated['status'] ?? 'pending',
            max(1, (int) ($validated['page'] ?? 1)),
            min(self::MAX_PER_PAGE, max(1, (int) ($validated['per_page'] ?? 20))),
        );
    }
}
