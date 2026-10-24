<?php

namespace App\MovieNight\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** The host already confirmed (with a winner) or cancelled the night, so votes no longer count. */
class VotingClosed extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Voting is closed for this movie night.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }
}
