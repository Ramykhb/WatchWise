<?php

namespace App\Admin\DTOs\Requests;

/** The settings an admin sends: any subset, by setting key. */
final readonly class UpdateSettingsData
{
    /**
     * @param  array<string, bool|array<string, mixed>>  $values
     */
    public function __construct(public array $values) {}

    /**
     * @param  array<string, bool|array<string, mixed>>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self($validated);
    }
}
