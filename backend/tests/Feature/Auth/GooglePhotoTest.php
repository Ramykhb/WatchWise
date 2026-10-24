<?php

namespace Tests\Feature\Auth;

use App\Auth\DTOs\Requests\SocialLoginData;
use App\Auth\InterfaceServices\SocialAuthServiceInterface;
use App\Auth\Support\GooglePhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GooglePhotoTest extends TestCase
{
    use RefreshDatabase;

    private const PLACEHOLDER = 'https://lh3.googleusercontent.com/a/letter=s96-c';

    private function people(array $photos, int $status = 200): void
    {
        Http::fake(['people.googleapis.com/*' => Http::response(['photos' => $photos], $status)]);
    }

    public function test_it_detects_a_placeholder(): void
    {
        $this->people([['metadata' => ['primary' => true], 'url' => self::PLACEHOLDER, 'default' => true]]);
        $this->assertTrue(app(GooglePhoto::class)->isPlaceholder('token'));
    }

    public function test_it_detects_a_real_photo(): void
    {
        $this->people([['metadata' => ['primary' => true], 'url' => 'https://lh3.googleusercontent.com/a/me']]);
        $this->assertFalse(app(GooglePhoto::class)->isPlaceholder('token'));
    }

    public function test_it_cannot_tell_when_the_api_is_off_or_there_is_no_token(): void
    {
        $this->people([], 403);
        $this->assertNull(app(GooglePhoto::class)->isPlaceholder('token'));
        $this->assertNull(app(GooglePhoto::class)->isPlaceholder(null));
    }

    private function googleLogin(string $avatar, bool $placeholder): SocialLoginData
    {
        $data = new SocialLoginData('google', 'g-123', 'maya@example.com', 'Maya Rivera', null, $avatar, 'token', true);

        return $placeholder ? $data->withoutPlaceholderAvatar() : $data;
    }

    public function test_new_accounts_get_no_photo_for_a_placeholder(): void
    {
        app(SocialAuthServiceInterface::class)->loginWithProvider($this->googleLogin(self::PLACEHOLDER, true), Request::create('/'));

        $this->assertNull(User::where('email', 'maya@example.com')->value('avatar'));
    }

    public function test_a_saved_placeholder_is_cleared_on_the_next_google_sign_in(): void
    {
        $service = app(SocialAuthServiceInterface::class);
        $service->loginWithProvider($this->googleLogin(self::PLACEHOLDER, false), Request::create('/'));
        $this->assertSame(self::PLACEHOLDER, User::where('email', 'maya@example.com')->value('avatar'));

        $service->loginWithProvider($this->googleLogin(self::PLACEHOLDER, true), Request::create('/'));
        $this->assertNull(User::where('email', 'maya@example.com')->value('avatar'));
    }

    public function test_an_uploaded_photo_is_never_cleared(): void
    {
        $service = app(SocialAuthServiceInterface::class);
        $service->loginWithProvider($this->googleLogin(self::PLACEHOLDER, false), Request::create('/'));
        User::where('email', 'maya@example.com')->update(['avatar' => 'http://localhost:8000/storage/avatars/me.jpg']);

        $service->loginWithProvider($this->googleLogin(self::PLACEHOLDER, true), Request::create('/'));
        $this->assertSame('http://localhost:8000/storage/avatars/me.jpg', User::where('email', 'maya@example.com')->value('avatar'));
    }
}
