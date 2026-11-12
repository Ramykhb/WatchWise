<?php

namespace App\Admin\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Staff asked for the messages of a private room that has no open report. Rendered as a 403 with
 * `code: room_not_reviewable`: private conversations are only read to judge a report.
 */
class RoomNotReviewable extends RuntimeException implements ShouldntReport
{
    public const CODE = 'room_not_reviewable';

    public function __construct()
    {
        parent::__construct('The messages of a private room can only be read while it has an open report.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'code' => self::CODE], 403);
    }
}
