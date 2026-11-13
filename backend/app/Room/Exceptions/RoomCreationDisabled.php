<?php

namespace App\Room\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/** An admin switched room creation off. Rendered as a 403 with `code: rooms_creation_disabled`; existing rooms keep working. */
class RoomCreationDisabled extends RuntimeException implements ShouldntReport
{
    public const CODE = 'rooms_creation_disabled';

    public function __construct()
    {
        parent::__construct('Creating discussion rooms is turned off right now.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'code' => self::CODE], 403);
    }
}
