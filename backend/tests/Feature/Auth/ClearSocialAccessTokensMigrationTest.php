<?php

namespace Tests\Feature\Auth;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Provider access tokens stored by earlier sign-ins are erased; the accounts stay linked. */
class ClearSocialAccessTokensMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_stored_provider_tokens_are_erased_and_links_kept(): void
    {
        $user = User::factory()->create();
        DB::table('social_accounts')->insert([
            ['user_id' => $user->id, 'provider' => 'google', 'provider_id' => 'g1', 'access_token' => Crypt::encryptString('old-token')],
            ['user_id' => $user->id, 'provider' => 'github', 'provider_id' => 'gh1', 'access_token' => null],
        ]);

        (require database_path('migrations/2026_11_11_100000_clear_social_account_access_tokens.php'))->up();

        $this->assertSame(2, SocialAccount::count());
        $this->assertSame(0, DB::table('social_accounts')->whereNotNull('access_token')->count());
    }
}
