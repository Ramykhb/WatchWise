<?php

namespace App\MovieList\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** The list was saved by someone (or another tab) after the client loaded it, so this save would overwrite that. */
class ListChangedElsewhere extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This list changed since you loaded it. Reload it and try again.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 409);
    }
}
