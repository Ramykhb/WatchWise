<?php

namespace App\Admin\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** Staff asked to remove a profile picture or a bio the member does not have. Rendered as a 404. */
class NothingToRemove extends RuntimeException
{
    public static function noAvatar(): self
    {
        return new self('This member has no profile picture to remove.');
    }

    public static function noBio(): self
    {
        return new self('This member has no bio to remove.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 404);
    }
}
