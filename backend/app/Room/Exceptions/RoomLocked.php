<?php

namespace App\Room\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/** Staff locked the room, so nobody can post in it, not even its creator. Rendered as a 403 with `code: room_locked`; reading, joining and leaving still work. */
class RoomLocked extends RuntimeException implements ShouldntReport
{
    public const CODE = 'room_locked';

    public function __construct()
    {
        parent::__construct('This room is locked. Nobody can post in it right now.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'code' => self::CODE], 403);
    }
}
