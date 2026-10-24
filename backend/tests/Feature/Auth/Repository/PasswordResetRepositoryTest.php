<?php

namespace Tests\Feature\Auth\Repository;

use App\Auth\Repository\PasswordResetRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordResetRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_replace_for_keeps_a_single_row_with_created_at(): void
    {
        $repo = new PasswordResetRepository;

        $repo->replaceFor('a@example.com', 'hash1');
        $second = $repo->replaceFor('a@example.com', 'hash2');
        $repo->replaceFor('b@example.com', 'hashb');

        $this->assertDatabaseCount('password_resets', 2);
        $this->assertSame('hash2', $second->token);
        $this->assertNotNull($second->created_at);
        $this->assertSame('hash2', $repo->latestFor('a@example.com')->token);
    }

    public function test_latest_for_returns_null_when_missing(): void
    {
        $this->assertNull((new PasswordResetRepository)->latestFor('none@example.com'));
    }

    public function test_delete_for_only_removes_that_email(): void
    {
        $repo = new PasswordResetRepository;
        $repo->replaceFor('a@example.com', 'h');
        $repo->replaceFor('b@example.com', 'h');

        $repo->deleteFor('a@example.com');

        $this->assertNull($repo->latestFor('a@example.com'));
        $this->assertNotNull($repo->latestFor('b@example.com'));
    }
}
