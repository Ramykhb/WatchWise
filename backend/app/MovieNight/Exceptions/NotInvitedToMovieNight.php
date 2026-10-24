<?php

namespace App\MovieNight\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** The user tried to answer a movie night they were not invited to. */
class NotInvitedToMovieNight extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('You are not invited to this movie night.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 403);
    }
}
