<?php

namespace App\Admin\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** Staff asked to remove a title that is not in the list. Rendered as a 404. */
class ListItemNotFound extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('That title is not in this list.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 404);
    }
}
