<?php

namespace App\MovieNight\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** A guest who declined the invitation tried to vote. */
class DeclinedGuestCannotVote extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Declined guests cannot vote.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 403);
    }
}
