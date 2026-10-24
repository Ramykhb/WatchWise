<?php

namespace App\Auth\InterfaceServices;

use App\Auth\DTOs\Requests\SocialLoginData;
use App\Auth\DTOs\Responses\AuthResponse;
use App\Auth\Exceptions\AccountDisabled;
use App\Auth\Exceptions\EmailRequired;
use App\Auth\Exceptions\UnverifiedProviderEmail;
use Illuminate\Http\Request;

interface SocialAuthServiceInterface
{
    /**
     * @throws EmailRequired when the provider gave no email
     * @throws UnverifiedProviderEmail when the provider has not verified the email
     * @throws AccountDisabled when the user is inactive
     */
    public function loginWithProvider(SocialLoginData $data, Request $request): AuthResponse;
}
