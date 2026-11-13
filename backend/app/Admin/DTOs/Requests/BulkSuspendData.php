<?php

namespace App\Admin\DTOs\Requests;

use Carbon\CarbonImmutable;

/** The members to suspend in one go, with the optional reason and end every one of them gets. */
final readonly class BulkSuspendData
{
    public const MAX_USERS = 50;

    /**
     * @param  list<int>  $userIds
     */
    public function __construct(
        public array $userIds,
        public ?string $reason = null,
        public ?CarbonImmutable $suspendedUntil = null,
    ) {}

    /**
     * @param  array{user_ids: list<int|string>, reason?: string|null, suspended_until?: string|null}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            array_map('intval', array_values($validated['user_ids'])),
            $validated['reason'] ?? null,
            isset($validated['suspended_until']) ? CarbonImmutable::parse($validated['suspended_until']) : null,
        );
    }
}
