<?php

namespace App\Admin\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** A moderator tried to delete a note another staff member wrote; only admins may. Rendered as a 403. */
class NotYourNote extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Moderators can only delete their own notes.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 403);
    }
}
