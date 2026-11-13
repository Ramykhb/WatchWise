<?php

namespace App\Admin\DTOs\Requests;

/** What staff set on a room: whether it is locked. */
final readonly class UpdateRoomData
{
    public function __construct(public bool $isLocked) {}

    /**
     * @param  array{is_locked: bool|int|string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self((bool) $validated['is_locked']);
    }
}
