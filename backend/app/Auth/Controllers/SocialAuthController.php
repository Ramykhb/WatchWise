<?php

namespace App\Auth\Controllers;

use App\Auth\Exceptions\SocialLoginFailure;
use App\Auth\FormRequests\SocialCallbackRequest;
use App\Auth\InterfaceServices\SocialAuthServiceInterface;
use App\Auth\Support\AuthCookies;
use App\Auth\Support\OAuthState;
use App\Auth\Support\SocialLoginRedirect;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Throwable;

/**
 * Both routes are browser navigations, so every outcome is a redirect: to the provider, or to the frontend's
 * /auth/callback page with `status=success` or `status=error&reason=<reason>`. An expected failure carries its
 * reason (`SocialLoginFailure`); anything else is reported and shown as `provider_error`.
 */
class SocialAuthController
{
    private const PROVIDERS = ['google', 'github', 'discord'];

    public function __construct(
        private readonly SocialAuthServiceInterface $social,
        private readonly AuthCookies $cookies,
    ) {}

    public function redirect(string $provider): RedirectResponse
    {
        $this->assertProvider($provider);

        $state = OAuthState::issue($provider);

        try {
            $url = $this->social->redirectUrl($provider, $state);
        } catch (SocialLoginFailure $failure) {
            return SocialLoginRedirect::failure($failure->reason());
        }

        return redirect($url)->withCookie(OAuthState::cookie($state));
    }

    public function callback(SocialCallbackRequest $request, string $provider): RedirectResponse
    {
        $this->assertProvider($provider);

        try {
            $authResponse = $this->social->loginWithProvider($request->toDto());
        } catch (SocialLoginFailure $failure) {
            return SocialLoginRedirect::failure($failure->reason());
        } catch (Throwable $e) {
            report($e);

            return SocialLoginRedirect::failure('provider_error');
        }

        $json = $this->cookies->attach(new JsonResponse, $authResponse);

        $redirect = SocialLoginRedirect::success();
        foreach ($json->headers->getCookies() as $cookie) {
            $redirect->headers->setCookie($cookie);
        }

        return $redirect;
    }

    private function assertProvider(string $provider): void
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);
    }
}
