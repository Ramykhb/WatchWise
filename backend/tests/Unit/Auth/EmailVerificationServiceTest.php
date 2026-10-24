<?php

namespace Tests\Unit\Auth;

use App\Auth\DTOs\Requests\ResendVerificationData;
use App\Auth\DTOs\Requests\VerifyEmailData;
use App\Auth\InterfaceRepository\UserRepositoryInterface;
use App\Auth\Mail\VerifyEmailMail;
use App\Auth\Services\EmailVerificationService;
use App\Models\User;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class EmailVerificationServiceTest extends TestCase
{
    private MockInterface $users;

    private EmailVerificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->users = Mockery::mock(UserRepositoryInterface::class);
        $this->service = new EmailVerificationService($this->users);
    }

    private function user(bool $verified = false): User
    {
        $user = new User(['name' => 'Ada', 'email' => 'ada@example.com']);
        $user->id = 3;
        $user->email_verified_at = $verified ? now() : null;

        return $user;
    }

    public function test_send_mails_unverified_user_after_defer(): void
    {
        Mail::fake();

        $this->service->send($this->user());
        Mail::assertNothingSent();

        app(DeferredCallbackCollection::class)->invoke();
        Mail::assertSent(VerifyEmailMail::class);
    }

    public function test_send_skips_verified_user(): void
    {
        Mail::fake();

        $this->service->send($this->user(true));
        app(DeferredCallbackCollection::class)->invoke();

        Mail::assertNothingSent();
    }

    public function test_resend_is_silent_for_unknown_email(): void
    {
        Mail::fake();
        $this->users->shouldReceive('findByEmail')->andReturnNull();

        $this->service->resend(new ResendVerificationData('x@example.com'));
        app(DeferredCallbackCollection::class)->invoke();

        Mail::assertNothingSent();
    }

    public function test_resend_skips_verified_and_sends_unverified(): void
    {
        Mail::fake();
        $this->users->shouldReceive('findByEmail')->twice()->andReturn($this->user(true), $this->user());

        $this->service->resend(new ResendVerificationData('ada@example.com'));
        app(DeferredCallbackCollection::class)->invoke();
        Mail::assertNothingSent();

        $this->service->resend(new ResendVerificationData('ada@example.com'));
        app(DeferredCallbackCollection::class)->invoke();
        Mail::assertSent(VerifyEmailMail::class, 1);
    }

    public function test_verify_fails_for_unknown_user_or_bad_hash(): void
    {
        $this->users->shouldReceive('findById')->with(1)->andReturnNull();
        $this->users->shouldReceive('findById')->with(3)->andReturn($this->user());
        $this->users->shouldNotReceive('markEmailVerified');

        $this->assertFalse($this->service->verify(new VerifyEmailData(1, 'x')));
        $this->assertFalse($this->service->verify(new VerifyEmailData(3, 'bad')));
    }

    public function test_verify_marks_unverified_user(): void
    {
        $user = $this->user();
        $this->users->shouldReceive('findById')->with(3)->andReturn($user);
        $this->users->shouldReceive('markEmailVerified')->once()->with($user);

        $this->assertTrue($this->service->verify(new VerifyEmailData(3, sha1('ada@example.com'))));
    }

    public function test_verify_does_not_remark_verified_user(): void
    {
        $this->users->shouldReceive('findById')->andReturn($this->user(true));
        $this->users->shouldNotReceive('markEmailVerified');

        $this->assertTrue($this->service->verify(new VerifyEmailData(3, sha1('ada@example.com'))));
    }
}
