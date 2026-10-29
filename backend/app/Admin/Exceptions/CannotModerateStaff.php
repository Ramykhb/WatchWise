<?php

namespace App\Admin\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** A moderator tried to change a role or to act on an admin or another moderator. Rendered as a 403. */
class CannotModerateStaff extends RuntimeException
{
    public static function forRoleChange(): self
    {
        return new self('Moderators cannot change roles.');
    }

    public static function forStaffAccount(): self
    {
        return new self('Moderators cannot manage admin or moderator accounts.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 403);
    }
}
