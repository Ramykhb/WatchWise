<?php

namespace App\Groq\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class ChatUnavailable extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The assistant could not finish its reply. Please try again.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 502);
    }
}
