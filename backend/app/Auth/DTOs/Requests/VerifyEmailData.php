<?php

namespace App\Auth\DTOs\Requests;

/** Taken from the signed verification link: /verify-email/{id}/{hash}. */
final readonly class VerifyEmailData
{
    public function __construct(
        public int $id,
        public string $hash,
    ) {}
}
