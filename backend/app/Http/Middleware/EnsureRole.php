<?php

namespace App\Http\Middleware;

use App\Auth\CurrentUserFactory;
use App\Contracts\CurrentUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function __construct(private readonly CurrentUserFactory $factory) {}

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $current = $this->factory->fromRequest($request);
        app()->instance(CurrentUser::class, $current);

        if (! $current->isAuthenticated()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! in_array($current->role(), $roles, true)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $next($request);
    }
}
