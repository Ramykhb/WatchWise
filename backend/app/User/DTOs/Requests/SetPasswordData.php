<?php

namespace App\User\DTOs\Requests;

final readonly class SetPasswordData
{
    public function __construct(
        public string $password,
    ) {}

    /**
     * @param  array{password: string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self($validated['password']);
    }
}
