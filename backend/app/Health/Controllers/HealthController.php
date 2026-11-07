<?php

namespace App\Health\Controllers;

use Illuminate\Http\JsonResponse;

class HealthController
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['status' => 'ok'])
            ->header('Cache-Control', 'no-store');
    }
}
