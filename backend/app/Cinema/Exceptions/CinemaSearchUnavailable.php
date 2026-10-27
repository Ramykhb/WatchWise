<?php

namespace App\Cinema\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class CinemaSearchUnavailable extends RuntimeException
{
    public function __construct(private readonly int $status, string $message)
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], $this->status);
    }
}
