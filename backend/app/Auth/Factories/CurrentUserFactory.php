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
     * Build from a verified access token; guest if invalid, the user is gone/inactive, or the token's session
     * version (`sv`, 0 for tokens issued before the claim existed) is not exactly the user's current one, i.e.
     * the user's sessions were revoked after it was issued.
     */
    public function fromToken(string $jwt): CurrentUser
    {
        $claims = $this->tokens->parseAccessToken($jwt);

        if (! $claims) {
            return $this->guest();
        }

        $user = $this->users->findById((int) $claims->sub);

        if (! $user || ! $user->is_active || ! $this->sessionStillCurrent($claims, $user)) {
            return $this->guest();
        }

        return new JwtCurrentUser($user, $claims);
    }

    private function sessionStillCurrent(object $claims, User $user): bool
    {
        $claimed = $claims->sv ?? 0;

        return is_int($claimed) && $claimed === (int) $user->session_version;
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
