<?php

namespace App\Admin\DTOs\Requests;

/** What an admin changes on an account; a null field is left as it is. */
final readonly class UpdateUserData
{
    public function __construct(
        public ?string $role = null,
        public ?bool $isActive = null,
    ) {}

    /**
     * @param  array{role?: string, is_active?: bool}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            $validated['role'] ?? null,
            isset($validated['is_active']) ? (bool) $validated['is_active'] : null,
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
