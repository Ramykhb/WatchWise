<?php

namespace App\Auth\Factories;

use App\Auth\Exceptions\InvalidRefreshToken;
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

    /** Issue a new access + refresh token pair (new refresh family unless one is given). */
    public function issue(User $user, Request $request, ?string $familyId = null): TokenPair
    {
        [$access, $accessExp] = $this->issueAccessToken($user);
        [$plain, $row] = $this->issueRefreshToken($user, $request, $familyId);

        return new TokenPair($user, $access, $accessExp, $plain, $row->expires_at);
    }

    /** @return array{0: string, 1: CarbonImmutable} */
    public function issueAccessToken(User $user): array
    {
        $now = now()->toImmutable();
        $exp = $now->addMinutes(config('jwt.access_ttl'));

        $jwt = JWT::encode([
            'iss' => config('jwt.issuer'),
            'sub' => (string) $user->id,
            'role' => $user->role,
            'iat' => $now->getTimestamp(),
            'exp' => $exp->getTimestamp(),
            'jti' => (string) Str::uuid(),
        ], $this->secret(), self::ALGO);

        return [$jwt, $exp];
    }

    /** @return array{0: string, 1: RefreshToken} plain token and its stored row */
    public function issueRefreshToken(User $user, Request $request, ?string $familyId = null): array
    {
        $plain = bin2hex(random_bytes(32));

        $row = RefreshToken::create([
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
     * @throws InvalidRefreshToken
     */
    public function rotate(string $plain, Request $request): TokenPair
    {
        $pair = DB::transaction(function () use ($plain, $request): ?TokenPair {
            $row = RefreshToken::where('token_hash', $this->hash($plain))->lockForUpdate()->first();

            if (! $row) {
                return null;
            }

            if ($row->revoked_at !== null) {
                // Reuse detected: revoke every token of this login chain.
                $this->revokeFamily($row->family_id);

                return null;
            }

            $user = $row->user;

            if ($row->expires_at->isPast() || ! $user || ! $user->is_active) {
                $this->revokeFamily($row->family_id);

                return null;
            }

            $pair = $this->issue($user, $request, $row->family_id);
            $new = RefreshToken::where('token_hash', $this->hash($pair->refreshToken))->first();
            $row->update(['revoked_at' => now(), 'replaced_by' => $new->id]);

            return $pair;
        });

        return $pair ?? throw new InvalidRefreshToken;
    }

    /** Revoke the family of the given refresh token (logout). */
    public function revoke(string $plain): void
    {
        $row = RefreshToken::where('token_hash', $this->hash($plain))->first();

        if ($row) {
            $this->revokeFamily($row->family_id);
        }
    }

    public function revokeFamily(string $familyId): void
    {
        RefreshToken::where('family_id', $familyId)->whereNull('revoked_at')->update(['revoked_at' => now()]);
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
