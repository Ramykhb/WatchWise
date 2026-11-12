<?php

namespace App\Auth\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * An admin closed registration, so no new account can be created. A password sign-up renders it as a 403 with
 * `code: registration_closed`; a first social sign-in is a browser redirect, so there it is the `registration_closed`
 * reason of the frontend failure page instead. Existing members can still sign in.
 */
class RegistrationClosed extends RuntimeException implements ShouldntReport, SocialLoginFailure
{
    public const CODE = 'registration_closed';

    public function __construct()
    {
        parent::__construct('New accounts cannot be created right now.');
    }

    public function reason(): string
    {
        return self::CODE;
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'code' => self::CODE], 403);
    }
}
