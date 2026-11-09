<?php

namespace Tests\Feature\User\Repository;

use App\Models\User;
use App\User\Repository\AccountRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The email-change writes are compare-and-set: they only touch the row while it still holds the exact
 * request a link was checked against, so a request replaced or cancelled in between is never approved,
 * applied or cancelled by an older link.
 */
class AccountRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private AccountRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new AccountRepository;
    }

    /** A copy of the user as a link handler loaded it, then the same row replaced by a newer request. */
    private function staleCopyAfterReplacement(bool $authorized): User
    {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $this->repo->storePendingEmail($user, 'first@example.com', 'nonce-one', $authorized);
        $stale = User::find($user->id);

        $this->repo->storePendingEmail($user, 'second@example.com', 'nonce-two', authorized: false);

        return $stale;
    }

    public function test_authorizing_a_replaced_request_changes_nothing(): void
    {
        $stale = $this->staleCopyAfterReplacement(authorized: false);

        $this->assertFalse($this->repo->authorizePendingEmail($stale, 'nonce-one'));

        $row = User::find($stale->id);
        $this->assertSame('second@example.com', $row->pending_email);
        $this->assertNull($row->pending_email_authorized_at);
    }

    public function test_authorizing_a_request_replaced_by_one_for_the_same_address_changes_nothing(): void
    {
        $user = User::factory()->create();
        $this->repo->storePendingEmail($user, 'new@example.com', 'nonce-one', authorized: false);
        $stale = User::find($user->id);
        $this->repo->storePendingEmail($user, 'new@example.com', 'nonce-two', authorized: false);

        $this->assertFalse($this->repo->authorizePendingEmail($stale, 'nonce-one'));
        $this->assertFalse($this->repo->applyPendingEmail($stale, 'nonce-one'));
        $this->assertFalse($this->repo->clearPendingEmail($stale, 'nonce-one'));

        $row = User::find($user->id);
        $this->assertSame('nonce-two', $row->pending_email_nonce);
        $this->assertNull($row->pending_email_authorized_at);
    }

    public function test_authorizing_succeeds_once_for_the_same_request(): void
    {
        $user = User::factory()->create();
        $this->repo->storePendingEmail($user, 'new@example.com', 'nonce-one', authorized: false);
        $firstClick = User::find($user->id);
        $secondClick = User::find($user->id);

        $this->assertTrue($this->repo->authorizePendingEmail($firstClick, 'nonce-one'));
        $this->assertFalse($this->repo->authorizePendingEmail($secondClick, 'nonce-one'));

        $this->assertNotNull($firstClick->pending_email_authorized_at);
        $this->assertNotNull(User::find($user->id)->pending_email_authorized_at);
    }

    public function test_authorizing_after_a_password_change_changes_nothing(): void
    {
        $user = User::factory()->create();
        $this->repo->storePendingEmail($user, 'new@example.com', 'nonce-one', authorized: false);
        $stale = User::find($user->id);
        User::find($user->id)->forceFill(['password' => 'different-pass-1'])->save();

        $this->assertFalse($this->repo->authorizePendingEmail($stale, 'nonce-one'));
        $this->assertNull(User::find($user->id)->pending_email_authorized_at);
    }

    public function test_applying_a_replaced_request_keeps_the_email(): void
    {
        $stale = $this->staleCopyAfterReplacement(authorized: true);

        $this->assertFalse($this->repo->applyPendingEmail($stale, 'nonce-one'));

        $row = User::find($stale->id);
        $this->assertSame('old@example.com', $row->email);
        $this->assertSame('second@example.com', $row->pending_email);
    }

    public function test_applying_needs_the_request_to_be_approved(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $this->repo->storePendingEmail($user, 'new@example.com', 'nonce-one', authorized: false);

        $this->assertFalse($this->repo->applyPendingEmail($user, 'nonce-one'));
        $this->assertSame('old@example.com', User::find($user->id)->email);
    }

    public function test_applying_the_same_request_swaps_the_email_and_clears_the_marks(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com', 'password_set_without_proof_at' => now()]);
        $this->repo->storePendingEmail($user, 'new@example.com', 'nonce-one', authorized: true);

        $this->assertTrue($this->repo->applyPendingEmail($user, 'nonce-one'));

        $row = User::find($user->id);
        $this->assertSame('new@example.com', $row->email);
        $this->assertNull($row->pending_email);
        $this->assertNull($row->pending_email_nonce);
        $this->assertNull($row->password_set_without_proof_at);
        $this->assertSame('new@example.com', $user->email);
    }

    public function test_clearing_with_an_old_nonce_leaves_the_newer_request(): void
    {
        $stale = $this->staleCopyAfterReplacement(authorized: false);

        $this->assertFalse($this->repo->clearPendingEmail($stale, 'nonce-one'));
        $this->assertSame('second@example.com', User::find($stale->id)->pending_email);

        $this->assertTrue($this->repo->clearPendingEmail($stale));
        $this->assertNull(User::find($stale->id)->pending_email);
    }
}
