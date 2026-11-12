<?php

namespace App\Auth\Middleware;

use App\Auth\CurrentUser\CurrentUser;
use App\Auth\Factories\CurrentUserFactory;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets only members with a verified email through: a guest gets 401, an unverified member gets 403 with
 * `code: email_unverified` so the frontend can send them to the "check your inbox" screen.
 */
class EnsureEmailVerified
{
    public const CODE = 'email_unverified';

    public function __construct(private readonly CurrentUserFactory $factory) {}

    public function handle(Request $request, Closure $next): Response
    {
        $current = $this->factory->fromRequest($request);
        app()->instance(CurrentUser::class, $current);

        if (! $current->isAuthenticated()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $current->isVerified()) {
            return response()->json(['message' => 'Verify your email address to continue.', 'code' => self::CODE], 403);
        }

        return $next($request);
    }
}
