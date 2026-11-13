<?php

namespace App\Admin\DTOs\Requests;

use Carbon\CarbonImmutable;

/**
 * What an admin changes on an account; a null field is left as it is. `reason` and `suspendedUntil` belong to a
 * suspension (`isActive` false) and are only kept when it really starts.
 */
final readonly class UpdateUserData
{
    public function __construct(
        public ?string $role = null,
        public ?bool $isActive = null,
        public ?string $reason = null,
        public ?CarbonImmutable $suspendedUntil = null,
    ) {}

    /**
     * @param  array{role?: string, is_active?: bool, reason?: string|null, suspended_until?: string|null}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            $validated['role'] ?? null,
            isset($validated['is_active']) ? (bool) $validated['is_active'] : null,
            $validated['reason'] ?? null,
            isset($validated['suspended_until']) ? CarbonImmutable::parse($validated['suspended_until']) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function changes(): array
    {
        return array_filter([
            'role' => $this->role,
            'is_active' => $this->isActive,
        ], fn (mixed $value): bool => $value !== null);
    }
}
