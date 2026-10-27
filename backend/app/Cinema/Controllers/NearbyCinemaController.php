<?php

namespace App\Cinema\Controllers;

use App\Cinema\FormRequests\NearbyCinemasRequest;
use App\Cinema\InterfaceServices\CinemaServiceInterface;
use Illuminate\Http\JsonResponse;

class NearbyCinemaController
{
    public function __construct(private readonly CinemaServiceInterface $cinemas) {}

    public function index(NearbyCinemasRequest $request): JsonResponse
    {
        return response()->json(['cinemas' => $this->cinemas->nearby($request->toDto())]);
    }
}
