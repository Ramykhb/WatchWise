<?php

namespace App\Auth\Support;

use App\Auth\DTOs\Requests\SocialLoginData;
use App\Auth\Exceptions\SocialProviderFailed;
use Illuminate\Support\Facades\Http;

/**
 * Discord's OAuth flow, which Socialite has no built-in driver for: the authorize URL, the code exchange
 * and the profile fetch.
 */
class DiscordOAuthClient
{
    private const AUTHORIZE_URL = 'https://discord.com/oauth2/authorize';

    private const TOKEN_URL = 'https://discord.com/api/oauth2/token';

    private const USER_URL = 'https://discord.com/api/users/@me';

    public function authorizeUrl(string $state): string
    {
        $credentials = config('services.discord');

        return self::AUTHORIZE_URL.'?'.http_build_query([
            'client_id' => $credentials['client_id'],
            'redirect_uri' => $credentials['redirect'],
            'response_type' => 'code',
            'scope' => 'identify email',
            'state' => $state,
        ]);
    }

    /**
     * @throws SocialProviderFailed when Discord sends no usable code, token or user
     */
    public function user(?string $code): SocialLoginData
    {
        if (! is_string($code) || $code === '') {
            throw new SocialProviderFailed('Discord did not return an authorization code.');
        }

        $credentials = config('services.discord');
        $tokenResponse = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => $credentials['client_id'],
            'client_secret' => $credentials['client_secret'],
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $credentials['redirect'],
        ])->throw()->json();

        $accessToken = $tokenResponse['access_token'] ?? null;
        if (! is_string($accessToken) || $accessToken === '') {
            throw new SocialProviderFailed('Discord did not return an access token.');
        }

        $user = Http::withToken($accessToken)->get(self::USER_URL)->throw()->json();
        $id = $user['id'] ?? null;
        if (! is_string($id) || $id === '') {
            throw new SocialProviderFailed('Discord did not return a user ID.');
        }

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
}
