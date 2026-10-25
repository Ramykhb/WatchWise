<?php

namespace App\Watchlist\DTOs\Requests;

final readonly class UpdateWatchlistStatusData
{
    public function __construct(public string $status) {}

    /**
     * @param  array{status: string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self($validated['status']);
    }
}
