<?php

namespace App\Report\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** Staff tried to resolve or dismiss a report that was already closed. Rendered as a 409. */
class ReportAlreadyClosed extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This report has already been closed.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 409);
    }
}
