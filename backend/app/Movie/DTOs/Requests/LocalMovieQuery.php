<?php

namespace App\Movie\DTOs\Requests;

/** Which page of the local movie catalogue to list, optionally filtered by title. */
final readonly class LocalMovieQuery
{
    public const MAX_PER_PAGE = 50;

    public function __construct(
        public string $search = '',
        public int $page = 1,
        public int $perPage = 20,
    ) {}

    /**
     * @param  array{q?: string|null, page?: int|string, per_page?: int|string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            trim((string) ($validated['q'] ?? '')),
            max(1, (int) ($validated['page'] ?? 1)),
            min(self::MAX_PER_PAGE, max(1, (int) ($validated['per_page'] ?? 20))),
        );
    }
}
