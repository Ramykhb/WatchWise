<?php

namespace App\Auth\InterfaceServices;

use App\Auth\Exceptions\AccountDisabled;
use App\Auth\Exceptions\EmailRequired;
use App\Auth\Exceptions\UnverifiedProviderEmail;
use App\Auth\Support\TokenPair;
use Illuminate\Http\Request;
use Laravel\Socialite\Contracts\User as SocialUser;

interface SocialAuthServiceInterface
{
    /**
     * @throws EmailRequired when the provider gave no email
     * @throws UnverifiedProviderEmail when the provider has not verified the email
     * @throws AccountDisabled when the user is inactive
     */
    public function loginWithProvider(string $provider, SocialUser $socialUser, Request $request): TokenPair;
}
