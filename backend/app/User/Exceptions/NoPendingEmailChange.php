<?php

namespace App\User\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** A confirmation link was asked for, but the member has no email change waiting. Rendered as a 422. */
class NoPendingEmailChange extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('There is no email change waiting for confirmation.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => ['email' => [$this->getMessage()]],
        ], 422);
    }
}
