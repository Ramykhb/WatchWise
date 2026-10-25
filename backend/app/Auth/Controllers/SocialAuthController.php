<?php

namespace App\Auth\Controllers;

use App\Auth\DTOs\Requests\SocialLoginData;
use App\Auth\Exceptions\AccountDisabled;
use App\Auth\Exceptions\EmailRequired;
use App\Auth\Exceptions\UnverifiedProviderEmail;
use App\Auth\InterfaceServices\SocialAuthServiceInterface;
use App\Auth\Support\AuthCookies;
use App\Auth\Support\GooglePhoto;
use App\Support\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class SocialAuthController extends Controller
{
    private const PROVIDERS = ['google', 'github', 'discord'];

    private const STATE_COOKIE = 'oauth_state';

    public function __construct(
        private readonly SocialAuthServiceInterface $social,
        private readonly AuthCookies $cookies,
        private readonly GooglePhoto $googlePhoto,
    ) {}

    public function redirect(string $provider): RedirectResponse
    {
        $this->assertProvider($provider);

        $credentials = config("services.{$provider}");
        abort_if(
            ! is_array($credentials)
                || ! is_string($credentials['client_id'] ?? null)
                || trim($credentials['client_id']) === ''
                || str_contains(strtoupper($credentials['client_id']), 'CHANGE_ME')
                || ! is_string($credentials['client_secret'] ?? null)
                || trim($credentials['client_secret']) === ''
                || str_contains(strtoupper($credentials['client_secret']), 'CHANGE_ME'),
            503,
            ucfirst($provider).' sign-in is not configured. Add valid OAuth client credentials to the backend environment.'
        );

        $state = Str::random(40);

        if ($provider === 'discord') {
            $query = http_build_query([
                'client_id' => $credentials['client_id'],
                'redirect_uri' => $credentials['redirect'],
                'response_type' => 'code',
                'scope' => 'identify email',
                'state' => $state,
            ]);

            return redirect('https://discord.com/oauth2/authorize?'.$query)
                ->withCookie(cookie(self::STATE_COOKIE, $state, 10, '/', null, ! app()->environment('local', 'testing'), true, false, 'lax'));
        }

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
            $socialData = $provider === 'discord'
                ? $this->discordUser($request)
                : SocialLoginData::fromSocialite($provider, Socialite::driver($provider)->stateless()->user());
        } catch (Throwable) {
            return $this->failure('provider_error');
        }

        // Google's letter image for accounts without a photo counts as no photo (initials are shown).
        if ($provider === 'google' && $this->googlePhoto->isPlaceholder($socialData->accessToken) === true) {
            $socialData = $socialData->withoutPlaceholderAvatar();
        }

        try {
            $authResponse = $this->social->loginWithProvider($socialData, $request);
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

    private function discordUser(Request $request): SocialLoginData
    {
        $code = $request->query('code');
        abort_if(! is_string($code) || $code === '', 400, 'Discord did not return an authorization code.');

        $credentials = config('services.discord');
        $tokenResponse = Http::asForm()->post('https://discord.com/api/oauth2/token', [
            'client_id' => $credentials['client_id'],
            'client_secret' => $credentials['client_secret'],
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $credentials['redirect'],
        ])->throw()->json();

        $accessToken = $tokenResponse['access_token'] ?? null;
        abort_if(! is_string($accessToken) || $accessToken === '', 502, 'Discord did not return an access token.');

        $user = Http::withToken($accessToken)->get('https://discord.com/api/users/@me')->throw()->json();
        $id = $user['id'] ?? null;
        abort_if(! is_string($id) || $id === '', 502, 'Discord did not return a user ID.');

        $avatar = isset($user['avatar']) && is_string($user['avatar'])
            ? "https://cdn.discordapp.com/avatars/{$id}/{$user['avatar']}.png"
            : null;

        return new SocialLoginData(
            provider: 'discord',
            providerId: $id,
            email: is_string($user['email'] ?? null) ? $user['email'] : null,
            name: is_string($user['global_name'] ?? null) ? $user['global_name'] : (is_string($user['username'] ?? null) ? $user['username'] : null),
            nickname: is_string($user['username'] ?? null) ? $user['username'] : null,
            avatar: $avatar,
            accessToken: $accessToken,
            providerReportsEmailVerified: (bool) ($user['verified'] ?? false),
        );
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
