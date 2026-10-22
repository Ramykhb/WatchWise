<?php

namespace App\Http\Middleware;

use App\Auth\CurrentUserFactory;
use App\Contracts\CurrentUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Same as AuthenticateJwt but lets anonymous requests through as a guest.
 */
class AuthenticateOptional
{
    public function __construct(private readonly CurrentUserFactory $factory) {}

    public function handle(Request $request, Closure $next): Response
    {
        app()->instance(CurrentUser::class, $this->factory->fromRequest($request));

        return $next($request);
    }
}
