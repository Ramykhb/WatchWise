<?php

namespace App\Auth\DTOs\Requests;

final readonly class LoginData
{
    public function __construct(
        public string $email,
        public string $password,
    ) {}

    /**
     * @param  array{email: string, password: string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self($validated['email'], $validated['password']);
    }
}
