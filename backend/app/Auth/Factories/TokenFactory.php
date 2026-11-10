<?php

namespace App\Auth\Factories;

use App\Auth\Exceptions\InvalidRefreshToken;
use App\Auth\InterfaceRepository\RefreshTokenRepositoryInterface;
use App\Auth\Support\TokenPair;
use App\Models\RefreshToken;
use App\Models\User;
use Carbon\CarbonImmutable;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class TokenFactory
{
    private const ALGO = 'HS256';

    public function __construct(private readonly RefreshTokenRepositoryInterface $refreshTokens) {}

    /**
     * Issue a new access + refresh token pair (new refresh family unless one is given). The access token
     * carries `$sessionVersion` when given (the version a revocation just produced), else the user's.
     */
    public function issue(User $user, Request $request, ?string $familyId = null, ?int $sessionVersion = null): TokenPair
    {
        [$access, $accessExp] = $this->issueAccessToken($user, $sessionVersion);
        [$plain, $row] = $this->issueRefreshToken($user, $request, $familyId);

        return new TokenPair($user, $access, $accessExp, $plain, $row->expires_at);
    }

    /**
     * The `sv` claim is the account's session version; the token only works while it is still current.
     *
     * @return array{0: string, 1: CarbonImmutable}
     */
    public function issueAccessToken(User $user, ?int $sessionVersion = null): array
    {
        $now = now()->toImmutable();
        $exp = $now->addMinutes(config('jwt.access_ttl'));

        $jwt = JWT::encode([
            'iss' => config('jwt.issuer'),
            'sub' => (string) $user->id,
            'role' => $user->role,
            'iat' => $now->getTimestamp(),
            'exp' => $exp->getTimestamp(),
            'sv' => $sessionVersion ?? (int) ($user->session_version ?? 0),
            'jti' => (string) Str::uuid(),
        ], $this->secret(), self::ALGO);

        return [$jwt, $exp];
    }

    /** @return array{0: string, 1: RefreshToken} plain token and its stored row */
    public function issueRefreshToken(User $user, Request $request, ?string $familyId = null): array
    {
        $plain = bin2hex(random_bytes(32));

        $row = $this->refreshTokens->create([
            'user_id' => $user->id,
            'token_hash' => $this->hash($plain),
            'family_id' => $familyId ?? (string) Str::uuid(),
            'expires_at' => now()->addDays(config('jwt.refresh_ttl_days')),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'ip' => $request->ip(),
        ]);

        return [$plain, $row];
    }

    /** Verify signature and expiry; null when the token is invalid. */
    public function parseAccessToken(string $jwt): ?object
    {
        $secret = $this->secret();

        try {
            $claims = JWT::decode($jwt, new Key($secret, self::ALGO));
        } catch (Throwable) {
            return null;
        }

        return isset($claims->sub) ? $claims : null;
    }

    /**
     * Exchange a refresh token for a new pair, rotating the old one.
     * Presenting an already-used or revoked token revokes the whole family.
     *
     * The owner's row is locked first, as revokeAllForUser() does, and the token is only checked after that:
     * a revocation that committed while this waited is seen (the token is revoked, so this fails), and one
     * that starts later waits for this commit and then revokes the token created here too.
     *
     * @throws InvalidRefreshToken
     */
    public function rotate(string $plain, Request $request): TokenPair
    {
        $pair = DB::transaction(function () use ($plain, $request): ?TokenPair {
            $hash = $this->hash($plain);
            $found = $this->refreshTokens->findByHash($hash);

            if (! $found) {
                return null;
            }

            $user = $this->refreshTokens->findUserForUpdate($found->user_id);
            // Read again under the user lock: only this read decides whether the token is still usable.
            $row = $this->refreshTokens->findByHashForUpdate($hash);

            if (! $row) {
                return null;
            }

            if ($row->revoked_at !== null) {
                // Reuse detected: revoke every token of this login chain.
                $this->revokeFamily($row->family_id);

                return null;
            }

            // Locked above, so the new access token carries the current session version.
            if ($row->expires_at->isPast() || ! $user || ! $user->is_active) {
                $this->revokeFamily($row->family_id);

                return null;
            }

            [$access, $accessExp] = $this->issueAccessToken($user);
            [$newPlain, $new] = $this->issueRefreshToken($user, $request, $row->family_id);
            $this->refreshTokens->markRotated($row, $new->id);

            return new TokenPair($user, $access, $accessExp, $newPlain, $new->expires_at);
        });

        return $pair ?? throw new InvalidRefreshToken;
    }

    /** Revoke the family of the given refresh token (logout). */
    public function revoke(string $plain): void
    {
        $row = $this->refreshTokens->findByHash($this->hash($plain));

        if ($row) {
            $this->revokeFamily($row->family_id);
        }
    }

    public function revokeFamily(string $familyId): void
    {
        $this->refreshTokens->revokeFamily($familyId);
    }

    /** Revoke every session the user holds (refresh tokens and older access tokens); returns the new session version. */
    public function revokeAllForUser(User $user): int
    {
        return $this->refreshTokens->revokeAllForUser($user);
    }

    public function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    private function secret(): string
    {
        $secret = config('jwt.secret');

        if (! is_string($secret) || strlen($secret) < 32) {
            throw new RuntimeException('JWT_SECRET is missing or too short (min 32 chars).');
        }

        return $secret;
    }
}
