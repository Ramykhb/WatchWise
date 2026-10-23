<?php

namespace App\Auth\Controllers;

use App\Auth\Exceptions\UnverifiedProviderEmail;
use App\Auth\Factories\TokenFactory;
use App\Auth\Support\AuthCookies;
use App\Http\Controllers\Controller;
use App\Models\RefreshToken;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialUser;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class SocialAuthController extends Controller
{
    private const PROVIDERS = ['google', 'github'];

    private const STATE_COOKIE = 'oauth_state';

    public function __construct(
        private readonly TokenFactory $tokens,
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

        if (! $socialUser->getEmail()) {
            return $this->failure('email_required');
        }

        try {
            $user = $this->resolveUser($provider, $socialUser);
        } catch (UnverifiedProviderEmail) {
            return $this->failure('email_unverified');
        }

        if (! $user->is_active) {
            return $this->failure('account_disabled');
        }

        $pair = $this->tokens->issue($user, $request);
        $json = $this->cookies->attach(new JsonResponse, $pair);

        $redirect = redirect($this->frontend('/auth/callback?status=success'));
        foreach ($json->headers->getCookies() as $cookie) {
            $redirect->headers->setCookie($cookie);
        }

        return $redirect->withCookie(cookie()->forget(self::STATE_COOKIE, '/'));
    }

    private function resolveUser(string $provider, SocialUser $socialUser): User
    {
        return DB::transaction(function () use ($provider, $socialUser) {
            $account = SocialAccount::where('provider', $provider)
                ->where('provider_id', (string) $socialUser->getId())
                ->first();

            if ($account) {
                $account->update(['access_token' => $socialUser->token]);
                $user = $account->user;
            } else {
                $email = $socialUser->getEmail();

                // Never link to or create an account from an email the provider has not verified.
                if (! $this->emailIsVerified($provider, $socialUser)) {
                    throw new UnverifiedProviderEmail;
                }

                $user = User::where('email', $email)->first();

                if ($user) {
                    if (! $user->email_verified_at) {
                        // The local account was never proven to belong to this email, so whoever
                        // registered it may not be the owner: drop their password and sessions.
                        $user->forceFill([
                            'email_verified_at' => now(),
                            'password' => Str::random(40),
                        ])->save();
                        RefreshToken::where('user_id', $user->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
                    }
                } else {
                    $user = new User([
                        'name' => $socialUser->getName() ?: ($socialUser->getNickname() ?: Str::before($email, '@')),
                        'email' => $email,
                        'password' => Str::random(40),
                        'avatar' => $socialUser->getAvatar(),
                    ]);
                    $user->email_verified_at = now();
                    $user->save();
                    $user->refresh();
                }

                SocialAccount::create([
                    'user_id' => $user->id,
                    'provider' => $provider,
                    'provider_id' => (string) $socialUser->getId(),
                    'access_token' => $socialUser->token,
                ]);
            }

            return $user;
        });
    }

    private function emailIsVerified(string $provider, SocialUser $socialUser): bool
    {
        if ($provider === 'google') {
            return (bool) ($socialUser->user['email_verified'] ?? false);
        }

        return true;
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
