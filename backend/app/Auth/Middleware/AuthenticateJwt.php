<?php

namespace App\Auth\Middleware;

use App\Auth\CurrentUser\CurrentUser;
use App\Auth\Factories\CurrentUserFactory;
use App\Auth\InterfaceRepository\UserRepositoryInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the CurrentUser once per request, binds it in the container and
 * rejects anonymous requests with 401.
 */
class AuthenticateJwt
{
    /** Activity is recorded at most once in this many minutes per member, so it costs one query per window. */
    private const ACTIVITY_WINDOW_MINUTES = 5;

    public function __construct(
        private readonly CurrentUserFactory $factory,
        private readonly UserRepositoryInterface $users,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $current = $this->factory->fromRequest($request);
        app()->instance(CurrentUser::class, $current);

        if (! $current->isAuthenticated()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = $current->user();
        if ($user !== null && ($user->last_active_at === null || $user->last_active_at->lt(now()->subMinutes(self::ACTIVITY_WINDOW_MINUTES)))) {
            $this->users->markActive($user);
        }

        return $next($request);
    }
}
