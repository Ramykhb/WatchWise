<?php

namespace App\Auth\DTOs\Requests;

final readonly class RegisterData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
    ) {}

    /**
     * @param  array{name: string, email: string, password: string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self($validated['name'], $validated['email'], $validated['password']);
    }
}
