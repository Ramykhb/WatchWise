<?php

namespace App\Admin\DTOs\Requests;

/** The visibility staff move a list to. Staff only restrict, so private is the one value they may send. */
final readonly class UpdateListVisibilityData
{
    public const ALLOWED = ['private'];

    /**
     * @param  'private'  $visibility
     */
    public function __construct(public string $visibility) {}

    /**
     * @param  array{visibility: string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self($validated['visibility']);
    }
}
