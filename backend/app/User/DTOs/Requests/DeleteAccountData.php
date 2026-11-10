<?php

namespace App\User\DTOs\Requests;

final readonly class DeleteAccountData
{
    /**
     * @param  string|null  $password  required when the account's password proves ownership; ignored when the
     *                                 deletion is confirmed from the inbox instead
     */
    public function __construct(public ?string $password) {}

    /**
     * @param  array{password?: string|null}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self($validated['password'] ?? null);
    }
}
