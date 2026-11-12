<?php

namespace App\Support;

/**
 * For FormRequests that take an `email`: it is lowercased and trimmed before validation, so the rules,
 * the lookups and the stored value all see "ali@example.com" whatever case was typed.
 *
 * A request with its own prepareForValidation() calls normalizeEmailInput() from it.
 */
trait NormalizesEmailInput
{
    protected function prepareForValidation(): void
    {
        $this->normalizeEmailInput();
    }

    protected function normalizeEmailInput(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => EmailAddress::normalize($this->input('email'))]);
        }
    }
}
