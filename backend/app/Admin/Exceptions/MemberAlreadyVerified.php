<?php

namespace App\Admin\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** Staff asked to resend the verification email of an account whose email is already verified. Rendered as a 409. */
class MemberAlreadyVerified extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This member has already verified their email.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 409);
    }
}
