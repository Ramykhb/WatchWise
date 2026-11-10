<?php

namespace App\Auth\Factories;

use App\Auth\Exceptions\InvalidRefreshToken;
use App\Auth\InterfaceRepository\RefreshTokenRepositoryInterface;
use App\Auth\Support\TokenPair;
use App\Models\RefreshToken;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
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
     * Issue a new access + refresh token pair for a new login (a new refresh family). The access token
     * carries `$sessionVersion` when given (the version a revocation just produced), else the user's.
     */
    public function issue(User $user, Request $request, ?int $sessionVersion = null): TokenPair
    {
        [$access, $accessExp] = $this->issueAccessToken($user, $sessionVersion);
        [$plain, $row] = $this->issueRefreshToken($user, $request, (string) Str::uuid(), now()->toImmutable());

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
            'aud' => config('jwt.audience'),
            'sub' => (string) $user->id,
            'iat' => $now->getTimestamp(),
            'exp' => $exp->getTimestamp(),
            'sv' => $sessionVersion ?? (int) ($user->session_version ?? 0),
        ], $this->secret(), self::ALGO);

        return [$jwt, $exp];
    }

    /**
     * When a refresh token of a family that started at `$familyStartedAt` expires: the sliding window
     * (`jwt.refresh_ttl_days` from now), but never past the family's absolute end (`jwt.refresh_family_max_days`
     * after it started). Every refresh token expiry goes through here.
     */
    public function refreshExpiresAt(CarbonInterface $familyStartedAt): CarbonImmutable
    {
        $sliding = now()->toImmutable()->addDays((int) config('jwt.refresh_ttl_days'));

        return $sliding->min($this->familyEndsAt($familyStartedAt));
    }

    /** Verify signature, algorithm, expiry, issuer and audience; null when the token is invalid. */
    public function parseAccessToken(string $jwt): ?object
    {
        $secret = $this->secret();

        try {
            $claims = JWT::decode($jwt, new Key($secret, self::ALGO));
        } catch (Throwable) {
            return null;
        }

        if (! isset($claims->sub)
            || ! $this->claimMatches($claims, 'iss', config('jwt.issuer'))
            || ! $this->claimMatches($claims, 'aud', config('jwt.audience'))) {
            return null;
        }

        return $claims;
    }

    /**
     * Exchange a refresh token for a new pair, rotating the old one.
     *
     * Presenting an already-used or revoked token revokes the whole family, with one exception (see
     * isGracedReuse()): a token rotated moments ago, presented again once by the same user agent while its
     * successor is still valid (two tabs refreshing at once, or a retry after a lost response), gets one sibling
     * token in the same family and nothing is revoked. A family past its absolute lifetime
     * (`jwt.refresh_family_max_days`) is revoked too.
     *
     * The owner's row is locked first, as revokeAllForUser() and revoke() do, and the token is only checked after
     * that: a revocation that committed while this waited is seen (the token is revoked, so this fails), and one
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

            $reusedInGrace = $row->revoked_at !== null && $this->isGracedReuse($row, $request);
            $familyStartedAt = $this->familyStartedAt($row);

            // Reuse that is not graced (likely theft), an expired token or family, or a disabled account:
            // revoke every token of this login chain. Locked above, so the user read here is current.
            if (($row->revoked_at !== null && ! $reusedInGrace)
                || $row->expires_at->isPast()
                || $this->familyEndsAt($familyStartedAt)->isPast()
                || ! $user
                || ! $user->is_active) {
                $this->refreshTokens->revokeFamily($row->family_id);

                return null;
            }

            [$access, $accessExp] = $this->issueAccessToken($user);
            [$newPlain, $new] = $this->issueRefreshToken($user, $request, $row->family_id, $familyStartedAt);

            // A graced token was already rotated: it keeps pointing at its first successor and spends its grace.
            if ($reusedInGrace) {
                $this->refreshTokens->markGraceUsed($row);
            } else {
                $this->refreshTokens->markRotated($row, $new->id);
            }

            return new TokenPair($user, $access, $accessExp, $newPlain, $new->expires_at);
        });

        return $pair ?? throw new InvalidRefreshToken;
    }

    /**
     * Revoke the family of the given refresh token (logout). Takes the owner's row lock first, in the same order
     * as rotate(), so a rotation running at the same time either commits first (and its new token is revoked
     * here) or waits and then finds its token revoked.
     */
    public function revoke(string $plain): void
    {
        DB::transaction(function () use ($plain): void {
            $row = $this->refreshTokens->findByHash($this->hash($plain));

            if (! $row) {
                return;
            }

            $this->refreshTokens->findUserForUpdate($row->user_id);
            $this->refreshTokens->revokeFamily($row->family_id);
        });
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

    /** @return array{0: string, 1: RefreshToken} plain token and its stored row */
    private function issueRefreshToken(User $user, Request $request, string $familyId, CarbonInterface $familyStartedAt): array
    {
        $plain = bin2hex(random_bytes(32));

        $row = $this->refreshTokens->create([
            'user_id' => $user->id,
            'token_hash' => $this->hash($plain),
            'family_id' => $familyId,
            'family_started_at' => $familyStartedAt,
            'expires_at' => $this->refreshExpiresAt($familyStartedAt),
            'user_agent' => $this->userAgent($request),
            'ip' => $request->ip(),
        ]);

        return [$plain, $row];
    }

    /**
     * A revoked token may be presented again only when all of these hold: rotation revoked it (it has a
     * successor), less than `jwt.refresh_reuse_grace_seconds` ago; it has not used its one grace yet
     * (`grace_used_at` is null); the request comes from the same user agent the token was issued to; and the
     * successor is still unrevoked and unexpired (so it has not been rotated, logged out or revoked since). A token
     * revoked by logout, by a family revocation or by "sign out everywhere" never qualifies: none of them sets
     * `replaced_by`, and they revoke the successor too.
     */
    private function isGracedReuse(RefreshToken $row, Request $request): bool
    {
        if ($row->replaced_by === null
            || $row->grace_used_at !== null
            || $row->revoked_at->lt(now()->subSeconds((int) config('jwt.refresh_reuse_grace_seconds')))
            || (string) $row->user_agent !== $this->userAgent($request)) {
            return false;
        }

        $successor = $this->refreshTokens->findByIdForUpdate((int) $row->replaced_by);

        return $successor !== null && $successor->revoked_at === null && $successor->expires_at->isFuture();
    }

    /**
     * Rows the migration's backfill did not reach (none expected) fall back to the earliest creation time left in
     * their family, read only in that case.
     */
    private function familyStartedAt(RefreshToken $row): CarbonImmutable
    {
        if ($row->family_started_at !== null) {
            return $row->family_started_at->toImmutable();
        }

        return $this->refreshTokens->earliestCreatedAtInFamily($row->family_id)
            ?? ($row->created_at ?? now())->toImmutable();
    }

    /** The user agent as stored on a refresh token. */
    private function userAgent(Request $request): string
    {
        return Str::limit((string) $request->userAgent(), 500, '');
    }

    private function familyEndsAt(CarbonInterface $familyStartedAt): CarbonImmutable
    {
        return $familyStartedAt->toImmutable()->addDays((int) config('jwt.refresh_family_max_days'));
    }

    /**
     * A claim must equal the configured value. A token without the claim is accepted only while
     * `jwt.require_iss_aud` is off (the deploy transition for tokens issued before the claim was checked).
     */
    private function claimMatches(object $claims, string $claim, mixed $expected): bool
    {
        if (! property_exists($claims, $claim)) {
            return ! config('jwt.require_iss_aud');
        }

        return is_string($claims->{$claim}) && $claims->{$claim} === $expected;
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
