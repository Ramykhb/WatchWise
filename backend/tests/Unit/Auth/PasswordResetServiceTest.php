<?php

namespace Tests\Unit\Auth;

use App\Auth\Exceptions\InvalidResetToken;
use App\Auth\InterfaceRepository\PasswordResetRepositoryInterface;
use App\Auth\InterfaceRepository\RefreshTokenRepositoryInterface;
use App\Auth\InterfaceRepository\UserRepositoryInterface;
use App\Auth\Mail\ResetPasswordMail;
use App\Auth\Services\PasswordResetService;
use App\Models\PasswordReset;
use App\Models\User;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class PasswordResetServiceTest extends TestCase
{
    private MockInterface $users;

    private MockInterface $resets;

    private MockInterface $refreshTokens;

    private PasswordResetService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->users = Mockery::mock(UserRepositoryInterface::class);
        $this->resets = Mockery::mock(PasswordResetRepositoryInterface::class);
        $this->refreshTokens = Mockery::mock(RefreshTokenRepositoryInterface::class);
        $this->service = new PasswordResetService($this->users, $this->resets, $this->refreshTokens);
    }

    private function user(): User
    {
        $user = new User(['name' => 'Ada', 'email' => 'ada@example.com']);
        $user->id = 7;

        return $user;
    }

    private function record(string $plain, int $minutesAgo = 5): PasswordReset
    {
        $record = new PasswordReset(['email' => 'ada@example.com', 'token' => Hash::make($plain)]);
        $record->created_at = now()->subMinutes($minutesAgo);

        return $record;
    }

    public function test_send_reset_link_does_nothing_for_unknown_email(): void
    {
        Mail::fake();
        $this->users->shouldReceive('findByEmail')->with('x@example.com')->andReturnNull();
        $this->resets->shouldNotReceive('replaceFor');

        $this->service->sendResetLink('x@example.com');
        app(DeferredCallbackCollection::class)->invoke();

        Mail::assertNothingSent();
    }

    public function test_send_reset_link_stores_hashed_token_and_mails_after_defer(): void
    {
        Mail::fake();
        $this->users->shouldReceive('findByEmail')->andReturn($this->user());
        $this->resets->shouldReceive('replaceFor')
            ->once()
            ->with('ada@example.com', Mockery::on(fn ($hash) => is_string($hash) && Hash::info($hash)['algoName'] === 'bcrypt'))
            ->andReturn(new PasswordReset);

        $this->service->sendResetLink('ada@example.com');
        Mail::assertNothingSent();

        app(DeferredCallbackCollection::class)->invoke();

        Mail::assertSent(ResetPasswordMail::class);
    }

    public function test_reset_throws_when_no_record(): void
    {
        $this->resets->shouldReceive('latestFor')->andReturnNull();
        $this->expectException(InvalidResetToken::class);

        $this->service->reset('ada@example.com', 'tok', 'newpassword');
    }

    public function test_reset_throws_when_expired(): void
    {
        $this->resets->shouldReceive('latestFor')->andReturn($this->record('tok', 61));
        $this->expectException(InvalidResetToken::class);

        $this->service->reset('ada@example.com', 'tok', 'newpassword');
    }

    public function test_reset_throws_when_token_wrong(): void
    {
        $this->resets->shouldReceive('latestFor')->andReturn($this->record('tok'));
        $this->users->shouldNotReceive('updatePassword');
        $this->expectException(InvalidResetToken::class);

        $this->service->reset('ada@example.com', 'wrong', 'newpassword');
    }

    public function test_reset_throws_when_user_missing(): void
    {
        $this->resets->shouldReceive('latestFor')->andReturn($this->record('tok'));
        $this->users->shouldReceive('findByEmail')->andReturnNull();
        $this->expectException(InvalidResetToken::class);

        $this->service->reset('ada@example.com', 'tok', 'newpassword');
    }

    public function test_reset_updates_password_deletes_record_and_revokes_tokens(): void
    {
        $user = $this->user();
        $this->resets->shouldReceive('latestFor')->andReturn($this->record('tok'));
        $this->users->shouldReceive('findByEmail')->andReturn($user);
        $this->users->shouldReceive('updatePassword')->once()->with($user, 'newpassword');
        $this->resets->shouldReceive('deleteFor')->once()->with('ada@example.com');
        $this->refreshTokens->shouldReceive('revokeAllForUser')->once()->with(7);

        $this->service->reset('ada@example.com', 'tok', 'newpassword');
    }
}
