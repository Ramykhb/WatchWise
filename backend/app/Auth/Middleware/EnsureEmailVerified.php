<?php

namespace App\Auth\Middleware;

use App\Auth\CurrentUser\CurrentUser;
use App\Auth\Factories\CurrentUserFactory;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailVerified
{
    public function __construct(private readonly CurrentUserFactory $factory) {}

    public function handle(Request $request, Closure $next): Response
    {
        $current = $this->factory->fromRequest($request);
        app()->instance(CurrentUser::class, $current);

        if (! $current->isAuthenticated()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $current->isVerified()) {
            return response()->json(['message' => 'Your email address is not verified.'], 403);
        }

        return $next($request);
    }
}
