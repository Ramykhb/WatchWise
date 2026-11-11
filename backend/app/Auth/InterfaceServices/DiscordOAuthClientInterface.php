<?php

namespace App\Auth\InterfaceServices;

use App\Auth\DTOs\Requests\SocialLoginData;
use App\Auth\Exceptions\SocialProviderFailed;

/** Discord's OAuth flow, which Socialite has no built-in driver for. */
interface DiscordOAuthClientInterface
{
    /** The Discord consent URL; `$state` comes back on the callback. */
    public function authorizeUrl(string $state): string;

    /**
     * Exchanges the authorization code for the signed-in Discord user.
     *
     * @throws SocialProviderFailed when Discord sends no usable code, token or user id
     */
    public function user(?string $code): SocialLoginData;
}
