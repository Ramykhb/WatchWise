<?php

namespace App\Admin\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** The note does not belong to this member, or was deleted a moment ago. Rendered as a 404. */
class NoteNotFound extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Note not found.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 404);
    }
}
