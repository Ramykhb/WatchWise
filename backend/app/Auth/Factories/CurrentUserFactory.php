<?php

namespace App\Auth\Factories;

use App\Auth\CurrentUser\CurrentUser;
use App\Auth\CurrentUser\GuestUser;
use App\Auth\CurrentUser\JwtCurrentUser;
use App\Auth\InterfaceRepository\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Http\Request;

class CurrentUserFactory
{
    public const ATTRIBUTE = 'current_user';

    public function __construct(
        private readonly TokenFactory $tokens,
        private readonly UserRepositoryInterface $users,
    ) {}

    /** Resolve the CurrentUser once per request and memoize it on the request. */
    public function fromRequest(Request $request): CurrentUser
    {
        $existing = $request->attributes->get(self::ATTRIBUTE);

        if ($existing instanceof CurrentUser) {
            return $existing;
        }

        $token = $this->extractToken($request);
        $current = $token ? $this->fromToken($token) : $this->guest();

        $request->attributes->set(self::ATTRIBUTE, $current);

        return $current;
    }

    /**
     * Build from a verified access token; guest if invalid, the user is gone/inactive, or the token was
     * issued before the user's sessions were last revoked (`sessions_invalidated_at`, whole seconds: the
     * fresh token a password change gives the calling device is issued after the stamp, so it still works).
     */
    public function fromToken(string $jwt): CurrentUser
    {
        $claims = $this->tokens->parseAccessToken($jwt);

        if (! $claims) {
            return $this->guest();
        }

        $user = $this->users->findById((int) $claims->sub);

        if (! $user || ! $user->is_active || $this->issuedBeforeInvalidation($claims, $user)) {
            return $this->guest();
        }

        return new JwtCurrentUser($user, $claims);
    }

    private function issuedBeforeInvalidation(object $claims, User $user): bool
    {
        $invalidatedAt = $user->sessions_invalidated_at;

        return $invalidatedAt !== null && (int) ($claims->iat ?? 0) < $invalidatedAt->getTimestamp();
    }

    public function guest(): CurrentUser
    {
        return new GuestUser;
    }

    private function extractToken(Request $request): ?string
    {
        return $request->bearerToken() ?: ($request->cookie(config('jwt.access_cookie')) ?: null);
    }
}
