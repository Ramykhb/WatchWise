<?php

namespace Tests\Feature\Auth;

use App\Auth\InterfaceServices\GooglePhotoInterface;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GooglePhotoTest extends TestCase
{
    use RefreshDatabase;
    use SignsInWithProviders;

    private const PLACEHOLDER = 'https://lh3.googleusercontent.com/a/letter=s96-c';

    private const PLACEHOLDER_PHOTOS = ['photos' => [['metadata' => ['primary' => true], 'url' => self::PLACEHOLDER, 'default' => true]]];

    private const REAL_PHOTOS = ['photos' => [['metadata' => ['primary' => true], 'url' => self::PLACEHOLDER]]];

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.frontend_url' => 'http://front.test']);
        $this->configureSocialProviders();
        $this->mockSocialite('google', $this->socialiteUser('g-123', 'maya@example.com', ['email_verified' => true], self::PLACEHOLDER));
    }

    private function people(array $photos, int $status = 200): void
    {
        Http::fake(['people.googleapis.com/*' => Http::response(['photos' => $photos], $status)]);
    }

    private function signIn(): void
    {
        $this->hitSocialCallback('google')->assertRedirect('http://front.test/auth/callback?status=success');
    }

    private function avatar(): ?string
    {
        return User::where('email', 'maya@example.com')->value('avatar');
    }

    public function test_it_detects_a_placeholder(): void
    {
        $this->people([['metadata' => ['primary' => true], 'url' => self::PLACEHOLDER, 'default' => true]]);
        $this->assertTrue(app(GooglePhotoInterface::class)->isPlaceholder('token'));
    }

    public function test_it_detects_a_real_photo(): void
    {
        $this->people([['metadata' => ['primary' => true], 'url' => 'https://lh3.googleusercontent.com/a/me']]);
        $this->assertFalse(app(GooglePhotoInterface::class)->isPlaceholder('token'));
    }

    public function test_it_cannot_tell_when_the_api_is_off_or_there_is_no_token(): void
    {
        $this->people([], 403);
        $this->assertNull(app(GooglePhotoInterface::class)->isPlaceholder('token'));
        $this->assertNull(app(GooglePhotoInterface::class)->isPlaceholder(null));
    }

    public function test_new_accounts_get_no_photo_for_a_placeholder(): void
    {
        Http::fake(['people.googleapis.com/*' => Http::response(self::PLACEHOLDER_PHOTOS)]);

        $this->signIn();

        $this->assertNull($this->avatar());
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer provider-access-token'));
    }

    public function test_a_saved_placeholder_is_cleared_on_the_next_google_sign_in(): void
    {
        Http::fake(['people.googleapis.com/*' => Http::sequence()->push(self::REAL_PHOTOS)->push(self::PLACEHOLDER_PHOTOS)]);

        $this->signIn();
        $this->assertSame(self::PLACEHOLDER, $this->avatar());

        $this->signIn();
        $this->assertNull($this->avatar());
    }

    public function test_an_uploaded_photo_is_never_cleared(): void
    {
        Http::fake(['people.googleapis.com/*' => Http::sequence()->push(self::REAL_PHOTOS)->push(self::PLACEHOLDER_PHOTOS)]);

        $this->signIn();
        User::where('email', 'maya@example.com')->update(['avatar' => 'http://localhost:8000/storage/avatars/me.jpg']);

        $this->signIn();
        $this->assertSame('http://localhost:8000/storage/avatars/me.jpg', $this->avatar());
    }
}
