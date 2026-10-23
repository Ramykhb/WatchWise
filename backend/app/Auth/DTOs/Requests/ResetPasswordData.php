<?php

namespace App\Auth\DTOs\Requests;

final readonly class ResetPasswordData
{
    public function __construct(
        public string $email,
        public string $token,
        public string $password,
    ) {}

    /**
     * @param  array{email: string, token: string, password: string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self($validated['email'], $validated['token'], $validated['password']);
    }
}
