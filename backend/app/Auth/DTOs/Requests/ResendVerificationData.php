<?php

namespace App\Auth\DTOs\Requests;

final readonly class ResendVerificationData
{
    public function __construct(public string $email) {}

    /**
     * @param  array{email: string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self($validated['email']);
    }
}
