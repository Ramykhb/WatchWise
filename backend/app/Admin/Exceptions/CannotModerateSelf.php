<?php

namespace App\Admin\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** An admin tried to demote, deactivate or delete their own account. Rendered as a 403. */
class CannotModerateSelf extends RuntimeException
{
    public static function forDemotion(): self
    {
        return new self('You cannot remove your own admin role.');
    }

    public static function forDeactivation(): self
    {
        return new self('You cannot deactivate your own account.');
    }

    public static function forDeletion(): self
    {
        return new self('You cannot delete your own account from the admin area.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 403);
    }
}
