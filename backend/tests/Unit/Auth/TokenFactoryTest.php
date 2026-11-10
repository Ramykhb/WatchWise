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

/** Refresh-token rotation takes the owner's row lock before it decides anything about the token. */
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
            'id' => 10, 'user_id' => 1, 'family_id' => 'family', 'token_hash' => 'h',
            'expires_at' => now()->addDay(), 'revoked_at' => $revokedAt,
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
}
