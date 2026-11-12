<?php

namespace Tests\Feature\Auth\Repository;

use App\Auth\Repository\PasswordResetRepository;
use App\Models\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PasswordResetRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function tokenOf(string $email): ?string
    {
        return PasswordReset::where('email', $email)->latest('id')->value('token');
    }

    public function test_replace_for_keeps_a_single_row_with_created_at(): void
    {
        $repo = new PasswordResetRepository;

        $repo->replaceFor('a@example.com', 'hash1');
        $second = $repo->replaceFor('a@example.com', 'hash2');
        $repo->replaceFor('b@example.com', 'hashb');

        $this->assertDatabaseCount('password_resets', 2);
        $this->assertSame('hash2', $second->token);
        $this->assertNotNull($second->created_at);
        $this->assertSame('hash2', $this->tokenOf('a@example.com'));
    }

    public function test_latest_for_update_finds_the_newest_row_in_any_case(): void
    {
        $repo = new PasswordResetRepository;
        $repo->replaceFor('a@example.com', 'hash1');

        $found = DB::transaction(fn () => $repo->latestForUpdate('A@Example.com'));

        $this->assertSame('hash1', $found->token);
        $this->assertNull(DB::transaction(fn () => $repo->latestForUpdate('none@example.com')));
    }

    public function test_delete_for_only_removes_that_email_and_counts_the_rows(): void
    {
        $repo = new PasswordResetRepository;
        $repo->replaceFor('a@example.com', 'h');
        $repo->replaceFor('b@example.com', 'h');

        $this->assertSame(1, $repo->deleteFor('a@example.com'));
        $this->assertSame(0, $repo->deleteFor('a@example.com'));

        $this->assertNull($this->tokenOf('a@example.com'));
        $this->assertNotNull($this->tokenOf('b@example.com'));
    }
}
