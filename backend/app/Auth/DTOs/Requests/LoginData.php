<?php

namespace App\Auth\DTOs\Requests;

final readonly class LoginData
{
    /**
     * @param  bool  $remember  keep this device signed in after the browser closes (see `TokenFactory::refreshExpiresAt()`)
     */
    public function __construct(
        public string $email,
        public string $password,
        public bool $remember = true,
    ) {}

    /**
     * @param  array{email: string, password: string, remember?: bool|int|string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self($validated['email'], $validated['password'], (bool) ($validated['remember'] ?? true));
    }
}
