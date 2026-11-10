<?php

namespace Tests\Unit\Auth;

use App\Auth\Exceptions\InvalidRefreshToken;
use App\Auth\Factories\TokenFactory;
use App\Auth\InterfaceRepository\RefreshTokenRepositoryInterface;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Http\Request;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/** Refresh-token rotation and logout take the owner's row lock before they decide anything about the token. */
class TokenFactoryTest extends TestCase
{
    private MockInterface $refreshTokens;

    private TokenFactory $tokens;

    protected function setUp(): void
    {
        parent::setUp();
        $this->refreshTokens = Mockery::mock(RefreshTokenRepositoryInterface::class);
        $this->tokens = new TokenFactory($this->refreshTokens);
    }

    private function token(?string $revokedAt = null): RefreshToken
    {
        return (new RefreshToken)->forceFill([
            'id' => 10, 'user_id' => 1, 'family_id' => 'family', 'token_hash' => 'h', 'user_agent' => 'Symfony',
            'family_started_at' => now(), 'expires_at' => now()->addDay(), 'revoked_at' => $revokedAt,
        ]);
    }

    private function user(int $sessionVersion): User
    {
        return (new User)->forceFill(['id' => 1, 'role' => 'user', 'is_active' => true, 'session_version' => $sessionVersion]);
    }

    public function test_a_revocation_seen_after_the_user_lock_stops_the_rotation(): void
    {
        $hash = $this->tokens->hash('plain');

        // Unrevoked when first looked up; revoked by a revocation that committed while the lock was awaited.
        $this->refreshTokens->shouldReceive('findByHash')->once()->with($hash)->andReturn($this->token())->ordered();
        $this->refreshTokens->shouldReceive('findUserForUpdate')->once()->with(1)->andReturn($this->user(1))->ordered();
        $this->refreshTokens->shouldReceive('findByHashForUpdate')->once()->with($hash)->andReturn($this->token(now()->toDateTimeString()))->ordered();
        $this->refreshTokens->shouldReceive('revokeFamily')->once()->with('family');
        $this->refreshTokens->shouldNotReceive('create');
        $this->refreshTokens->shouldNotReceive('markRotated');

        $this->expectException(InvalidRefreshToken::class);

        $this->tokens->rotate('plain', Request::create('/'));
    }

    public function test_the_rotated_access_token_carries_the_version_read_under_the_lock(): void
    {
        $hash = $this->tokens->hash('plain');
        $old = $this->token();
        $new = (new RefreshToken)->forceFill(['id' => 11, 'expires_at' => now()->addDays(30)]);

        $this->refreshTokens->shouldReceive('findByHash')->once()->andReturn($old)->ordered();
        $this->refreshTokens->shouldReceive('findUserForUpdate')->once()->with(1)->andReturn($this->user(7))->ordered();
        $this->refreshTokens->shouldReceive('findByHashForUpdate')->once()->with($hash)->andReturn($old)->ordered();
        $this->refreshTokens->shouldReceive('create')->once()->andReturn($new);
        $this->refreshTokens->shouldReceive('markRotated')->once()->with($old, 11);

        $pair = $this->tokens->rotate('plain', Request::create('/'));

        $this->assertSame(7, $this->tokens->parseAccessToken($pair->accessToken)->sv);
    }

    public function test_a_graced_reuse_reads_the_successor_under_the_user_lock_spends_the_grace_and_revokes_nothing(): void
    {
        $hash = $this->tokens->hash('plain');
        $rotated = $this->token(now()->subSeconds(5)->toDateTimeString())->forceFill(['replaced_by' => 11]);
        $successor = (new RefreshToken)->forceFill(['id' => 11, 'expires_at' => now()->addDays(30), 'revoked_at' => null]);
        $sibling = (new RefreshToken)->forceFill(['id' => 12, 'expires_at' => now()->addDays(30)]);

        $this->refreshTokens->shouldReceive('findByHash')->once()->with($hash)->andReturn($rotated)->ordered();
        $this->refreshTokens->shouldReceive('findUserForUpdate')->once()->with(1)->andReturn($this->user(1))->ordered();
        $this->refreshTokens->shouldReceive('findByHashForUpdate')->once()->with($hash)->andReturn($rotated)->ordered();
        $this->refreshTokens->shouldReceive('findByIdForUpdate')->once()->with(11)->andReturn($successor)->ordered();
        $this->refreshTokens->shouldReceive('create')->once()->with(Mockery::on(fn (array $row): bool => $row['family_id'] === 'family'))->andReturn($sibling)->ordered();
        $this->refreshTokens->shouldReceive('markGraceUsed')->once()->with($rotated)->ordered();
        $this->refreshTokens->shouldNotReceive('markRotated');
        $this->refreshTokens->shouldNotReceive('revokeFamily');

        $this->tokens->rotate('plain', Request::create('/'));
    }

    public function test_a_row_without_a_family_start_falls_back_to_the_earliest_row_of_its_family(): void
    {
        $old = $this->token()->forceFill(['family_started_at' => null, 'created_at' => now()->subDays(5)]);
        $new = (new RefreshToken)->forceFill(['id' => 11, 'expires_at' => now()->addDays(30)]);
        $familyStart = now()->subDays(80)->toImmutable();

        $this->refreshTokens->shouldReceive('findByHash')->once()->andReturn($old);
        $this->refreshTokens->shouldReceive('findUserForUpdate')->once()->andReturn($this->user(1));
        $this->refreshTokens->shouldReceive('findByHashForUpdate')->once()->andReturn($old);
        $this->refreshTokens->shouldReceive('earliestCreatedAtInFamily')->once()->with('family')->andReturn($familyStart);
        $this->refreshTokens->shouldReceive('create')->once()->with(Mockery::on(
            fn (array $row): bool => $row['family_started_at']->eq($familyStart) && $row['expires_at']->eq($familyStart->addDays(90)),
        ))->andReturn($new);
        $this->refreshTokens->shouldReceive('markRotated')->once();

        $this->tokens->rotate('plain', Request::create('/'));
    }

    public function test_logout_takes_the_user_lock_before_revoking_the_family(): void
    {
        $hash = $this->tokens->hash('plain');

        $this->refreshTokens->shouldReceive('findByHash')->once()->with($hash)->andReturn($this->token())->ordered();
        $this->refreshTokens->shouldReceive('findUserForUpdate')->once()->with(1)->andReturn($this->user(1))->ordered();
        $this->refreshTokens->shouldReceive('revokeFamily')->once()->with('family')->ordered();

        $this->tokens->revoke('plain');
    }

    public function test_logout_with_an_unknown_token_locks_and_revokes_nothing(): void
    {
        $this->refreshTokens->shouldReceive('findByHash')->once()->andReturn(null);
        $this->refreshTokens->shouldNotReceive('findUserForUpdate');
        $this->refreshTokens->shouldNotReceive('revokeFamily');

        $this->tokens->revoke('plain');
    }
}
