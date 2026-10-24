<?php

namespace App\Auth\Controllers;

use App\Auth\DTOs\Requests\SocialLoginData;
use App\Auth\Exceptions\AccountDisabled;
use App\Auth\Exceptions\EmailRequired;
use App\Auth\Exceptions\UnverifiedProviderEmail;
use App\Auth\InterfaceServices\SocialAuthServiceInterface;
use App\Auth\Support\AuthCookies;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class SocialAuthController extends Controller
{
    private const PROVIDERS = ['google', 'github'];

    private const STATE_COOKIE = 'oauth_state';

    public function __construct(
        private readonly SocialAuthServiceInterface $social,
        private readonly AuthCookies $cookies,
    ) {}

    public function redirect(string $provider): RedirectResponse
    {
        $this->assertProvider($provider);

        $state = Str::random(40);

        return Socialite::driver($provider)
            ->stateless()
            ->with(['state' => $state])
            ->redirect()
            ->withCookie(cookie(self::STATE_COOKIE, $state, 10, '/', null, ! app()->environment('local', 'testing'), true, false, 'lax'));
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $this->assertProvider($provider);

        $expected = $request->cookie(self::STATE_COOKIE);
        $given = $request->query('state');

        if (! is_string($expected) || ! is_string($given) || ! hash_equals($expected, $given)) {
            return $this->failure('invalid_state');
        }

        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();
        } catch (Throwable) {
            return $this->failure('provider_error');
        }

        try {
            $authResponse = $this->social->loginWithProvider(SocialLoginData::fromSocialite($provider, $socialUser), $request);
        } catch (EmailRequired) {
            return $this->failure('email_required');
        } catch (UnverifiedProviderEmail) {
            return $this->failure('email_unverified');
        } catch (AccountDisabled) {
            return $this->failure('account_disabled');
        }

        $json = $this->cookies->attach(new JsonResponse, $authResponse);

        $redirect = redirect($this->frontend('/auth/callback?status=success'));
        foreach ($json->headers->getCookies() as $cookie) {
            $redirect->headers->setCookie($cookie);
        }

        return $redirect->withCookie(cookie()->forget(self::STATE_COOKIE, '/'));
    }

    private function assertProvider(string $provider): void
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);
    }

    private function failure(string $reason): RedirectResponse
    {
        return redirect($this->frontend('/auth/callback?status=error&reason='.$reason));
    }

    private function frontend(string $path): string
    {
        return rtrim(config('services.frontend_url'), '/').$path;
    }
}
