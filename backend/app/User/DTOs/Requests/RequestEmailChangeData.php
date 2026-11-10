<?php

namespace App\User\DTOs\Requests;

final readonly class RequestEmailChangeData
{
    public function __construct(
        public string $email,
        public ?string $currentPassword,
    ) {}

    /**
     * @param  array{email: string, current_password?: string|null}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self($validated['email'], $validated['current_password'] ?? null);
    }
}
