<?php

namespace Tests\Feature\Auth\Repository;

use App\Auth\Repository\RefreshTokenRepository;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RefreshTokenRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_and_find(): void
    {
        $user = User::factory()->create();
        $repo = new RefreshTokenRepository;

        $token = $repo->create([
            'user_id' => $user->id,
            'token_hash' => 'abc',
            'family_id' => 'fam',
            'expires_at' => now()->addDay(),
            'user_agent' => 'UA',
            'ip' => '1.2.3.4',
        ]);

        $this->assertTrue($repo->findByHash('abc')->is($token));
        $this->assertNull($repo->findByHash('missing'));
        DB::transaction(fn () => $this->assertTrue($repo->findByHashForUpdate('abc')->is($token)));
        $this->assertNull($repo->findByHashForUpdate('missing'));
    }

    public function test_mark_rotated_sets_revoked_and_replaced_by(): void
    {
        $old = RefreshToken::factory()->create();
        $new = RefreshToken::factory()->create();

        (new RefreshTokenRepository)->markRotated($old, $new->id);

        $old->refresh();
        $this->assertNotNull($old->revoked_at);
        $this->assertSame($new->id, (int) $old->replaced_by);
    }

    public function test_revoke_family_only_touches_non_revoked_rows_of_family(): void
    {
        $a = RefreshToken::factory()->create(['family_id' => 'f1']);
        $already = RefreshToken::factory()->create(['family_id' => 'f1', 'revoked_at' => now()->subDay()]);
        $other = RefreshToken::factory()->create(['family_id' => 'f2']);

        (new RefreshTokenRepository)->revokeFamily('f1');

        $this->assertNotNull($a->fresh()->revoked_at);
        $this->assertTrue($already->fresh()->revoked_at->lt(now()->subHour()));
        $this->assertNull($other->fresh()->revoked_at);
    }

    public function test_revoke_all_for_user(): void
    {
        $user = User::factory()->create();
        $mine = RefreshToken::factory()->count(2)->create(['user_id' => $user->id]);
        $theirs = RefreshToken::factory()->create();

        (new RefreshTokenRepository)->revokeAllForUser($user->id);

        $mine->each(fn ($t) => $this->assertNotNull($t->fresh()->revoked_at));
        $this->assertNull($theirs->fresh()->revoked_at);
    }
}
