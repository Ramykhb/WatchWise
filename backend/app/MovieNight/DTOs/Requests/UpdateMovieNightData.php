<?php

namespace App\MovieNight\DTOs\Requests;

final readonly class UpdateMovieNightData
{
    /**
     * @param  array<string, mixed>  $attributes  only the fields the client sent
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
