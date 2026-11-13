<?php

namespace App\Admin\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** Staff acted on a list that another request deleted first. Rendered as a 404. */
class ListNotFound extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('List not found.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 404);
    }
}
