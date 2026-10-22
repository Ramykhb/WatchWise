<?php

namespace App\Http\Middleware;

use App\Auth\CurrentUserFactory;
use App\Contracts\CurrentUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the CurrentUser once per request, binds it in the container and
 * rejects anonymous requests with 401.
 */
class AuthenticateJwt
{
    public function __construct(private readonly CurrentUserFactory $factory) {}

    public function handle(Request $request, Closure $next): Response
    {
        $current = $this->factory->fromRequest($request);
        app()->instance(CurrentUser::class, $current);

        if (! $current->isAuthenticated()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}
