<?php

namespace App\User\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** There is no pending follow request from that member to accept (never sent, withdrawn or already answered). */
class FollowRequestNotFound extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This follow request is no longer pending.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 404);
    }
}
