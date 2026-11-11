<?php

namespace Tests\Unit\Auth;

use App\Auth\DTOs\Requests\ForgotPasswordData;
use App\Auth\DTOs\Requests\ResetPasswordData;
use App\Auth\Exceptions\InvalidResetToken;
use App\Auth\InterfaceRepository\PasswordResetRepositoryInterface;
use App\Auth\InterfaceRepository\RefreshTokenRepositoryInterface;
use App\Auth\InterfaceRepository\UserRepositoryInterface;
use App\Auth\Mail\PasswordChangedMail;
use App\Auth\Mail\ResetPasswordMail;
use App\Auth\Services\PasswordResetService;
use App\Models\PasswordReset;
use App\Models\User;
use App\User\InterfaceServices\AccountServiceInterface;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class PasswordResetServiceTest extends TestCase
{
    private MockInterface $users;

    private MockInterface $resets;

    private MockInterface $refreshTokens;

    private MockInterface $accounts;

    private PasswordResetService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->users = Mockery::mock(UserRepositoryInterface::class);
        $this->resets = Mockery::mock(PasswordResetRepositoryInterface::class);
        $this->refreshTokens = Mockery::mock(RefreshTokenRepositoryInterface::class);
        $this->accounts = Mockery::mock(AccountServiceInterface::class);
        $this->service = new PasswordResetService($this->users, $this->resets, $this->refreshTokens, $this->accounts);
    }

    private function user(): User
    {
        $user = new User(['name' => 'Ada', 'email' => 'ada@example.com']);
        $user->id = 7;

        return $user;
    }

    private function record(string $plain, int $minutesAgo = 5): PasswordReset
    {
        $record = new PasswordReset(['email' => 'ada@example.com', 'token' => hash('sha256', $plain)]);
        $record->created_at = now()->subMinutes($minutesAgo);

        return $record;
    }

    public function test_send_reset_link_does_nothing_for_unknown_email(): void
    {
        Mail::fake();
        $this->users->shouldReceive('findByEmail')->with('x@example.com')->andReturnNull();
        $this->resets->shouldNotReceive('replaceFor');

        $this->service->sendResetLink(new ForgotPasswordData('x@example.com'));
        app(DeferredCallbackCollection::class)->invoke();

        Mail::assertNothingSent();
    }

    public function test_send_reset_link_stores_the_sha256_of_the_token_and_mails_after_defer(): void
    {
        Mail::fake();
        $stored = null;
        $this->users->shouldReceive('findByEmail')->andReturn($this->user());
        $this->resets->shouldReceive('replaceFor')
            ->once()
            ->with('ada@example.com', Mockery::on(function ($hash) use (&$stored): bool {
                $stored = $hash;

                return is_string($hash) && preg_match('/^[0-9a-f]{64}$/', $hash) === 1;
            }))
            ->andReturn(new PasswordReset);

        $this->service->sendResetLink(new ForgotPasswordData('ada@example.com'));
        Mail::assertNothingSent();

        app(DeferredCallbackCollection::class)->invoke();

        Mail::assertSent(ResetPasswordMail::class, function (ResetPasswordMail $mail) use (&$stored): bool {
            parse_str((string) parse_url($mail->url, PHP_URL_QUERY), $query);

            return hash('sha256', $query['token']) === $stored;
        });
    }

    public function test_reset_throws_when_no_record(): void
    {
        $this->resets->shouldReceive('latestForUpdate')->andReturnNull();
        $this->resets->shouldNotReceive('deleteFor');
        $this->expectException(InvalidResetToken::class);

        $this->service->reset(new ResetPasswordData('ada@example.com', 'tok', 'newpassword1'));
    }

    public function test_reset_throws_when_expired(): void
    {
        $this->resets->shouldReceive('latestForUpdate')->andReturn($this->record('tok', 61));
        $this->resets->shouldNotReceive('deleteFor');
        $this->expectException(InvalidResetToken::class);

        $this->service->reset(new ResetPasswordData('ada@example.com', 'tok', 'newpassword1'));
    }

    public function test_reset_throws_when_token_wrong(): void
    {
        $this->resets->shouldReceive('latestForUpdate')->andReturn($this->record('tok'));
        $this->resets->shouldNotReceive('deleteFor');
        $this->users->shouldNotReceive('updatePassword');
        $this->expectException(InvalidResetToken::class);

        $this->service->reset(new ResetPasswordData('ada@example.com', 'wrong', 'newpassword1'));
    }

    public function test_reset_throws_when_the_row_was_consumed_meanwhile(): void
    {
        $this->resets->shouldReceive('latestForUpdate')->andReturn($this->record('tok'));
        $this->resets->shouldReceive('deleteFor')->once()->andReturn(0);
        $this->users->shouldNotReceive('updatePassword');
        $this->expectException(InvalidResetToken::class);

        $this->service->reset(new ResetPasswordData('ada@example.com', 'tok', 'newpassword1'));
    }

    public function test_reset_throws_when_user_missing(): void
    {
        $this->resets->shouldReceive('latestForUpdate')->andReturn($this->record('tok'));
        $this->resets->shouldReceive('deleteFor')->andReturn(1);
        $this->users->shouldReceive('findByEmail')->andReturnNull();
        $this->expectException(InvalidResetToken::class);

        $this->service->reset(new ResetPasswordData('ada@example.com', 'tok', 'newpassword1'));
    }

    public function test_reset_consumes_the_row_updates_password_records_inbox_proof_revokes_tokens_and_notifies(): void
    {
        Mail::fake();
        $user = $this->user();
        $this->resets->shouldReceive('latestForUpdate')->once()->with('ada@example.com')->andReturn($this->record('tok'))->ordered();
        $this->resets->shouldReceive('deleteFor')->once()->with('ada@example.com')->andReturn(1)->ordered();
        $this->users->shouldReceive('findByEmail')->andReturn($user);
        $this->users->shouldReceive('updatePassword')->once()->with($user, 'newpassword1')->ordered();
        $this->accounts->shouldReceive('passwordResetThroughEmail')->once()->with($user);
        $this->refreshTokens->shouldReceive('revokeAllForUser')->once()->with($user)->andReturn(1);

        $this->service->reset(new ResetPasswordData('ada@example.com', 'tok', 'newpassword1'));
        Mail::assertNothingSent();
        app(DeferredCallbackCollection::class)->invoke();

        Mail::assertSent(PasswordChangedMail::class, fn (PasswordChangedMail $mail): bool => $mail->hasTo('ada@example.com'));
    }
}
