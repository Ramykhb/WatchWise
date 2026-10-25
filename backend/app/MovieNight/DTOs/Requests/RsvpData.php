<?php

namespace App\MovieNight\DTOs\Requests;

final readonly class RsvpData
{
    /**
     * The answers an invited guest can give.
     *
     * @var list<string>
     */
    public const STATUSES = ['accepted', 'maybe', 'declined'];

    public function __construct(public string $status) {}

    /**
     * @param  array{status: string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self($validated['status']);
    }
}
