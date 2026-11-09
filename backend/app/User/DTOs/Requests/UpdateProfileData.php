<?php

namespace App\User\DTOs\Requests;

final readonly class UpdateProfileData
{
    /**
     * @param  array<string, mixed>  $attributes  only the fields the client sent (name, username, location, bio, profile_visibility); the email changes through EmailChangeService
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
