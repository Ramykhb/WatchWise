<?php

namespace App\Admin\DTOs\Requests;

/** A new internal note about a member. */
final readonly class AddUserNoteData
{
    public function __construct(public string $body) {}

    /**
     * @param  array{body: string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self($validated['body']);
    }
}
