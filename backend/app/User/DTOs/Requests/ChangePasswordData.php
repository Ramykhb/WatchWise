<?php

namespace App\User\DTOs\Requests;

final readonly class ChangePasswordData
{
    public function __construct(
        public string $currentPassword,
        public string $newPassword,
    ) {}

    /**
     * @param  array{current_password: string, password: string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self($validated['current_password'], $validated['password']);
    }
}
