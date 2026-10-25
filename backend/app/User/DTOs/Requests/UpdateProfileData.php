<?php

namespace App\User\DTOs\Requests;

final readonly class UpdateProfileData
{
    /**
     * @param  array<string, mixed>  $attributes  only the fields the client sent (name, username, email, location, bio, profile_visibility)
     */
    public function __construct(public array $attributes) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self($validated);
    }
}
