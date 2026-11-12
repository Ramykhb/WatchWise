<?php

namespace App\Admin\DTOs\Requests;

/** One page of a member's notes, newest first. */
final readonly class UserNoteQuery
{
    public const MAX_PER_PAGE = 50;

    public function __construct(
        public int $page = 1,
        public int $perPage = 20,
    ) {}

    /**
     * @param  array{page?: int|string, per_page?: int|string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            max(1, (int) ($validated['page'] ?? 1)),
            min(self::MAX_PER_PAGE, max(1, (int) ($validated['per_page'] ?? 20))),
        );
    }
}
