<?php

namespace App\Auth\Services;

use App\Auth\DTOs\Requests\LoginData;
use App\Auth\DTOs\Requests\RegisterData;
use App\Auth\DTOs\Responses\AuthResponse;
use App\Auth\Exceptions\EmailAlreadyRegistered;
use App\Auth\Exceptions\InvalidCredentials;
use App\Auth\Exceptions\RegistrationClosed;
use App\Auth\Exceptions\TooManyLoginAttempts;
use App\Auth\Factories\TokenFactory;
use App\Auth\InterfaceRepository\UserRepositoryInterface;
use App\Auth\InterfaceServices\AuthServiceInterface;
use App\Auth\Support\DummyPassword;
use App\Auth\Support\RateLimitAddress;
use App\Auth\Support\TokenPair;
use App\Models\User;
use App\Setting\InterfaceServices\SettingServiceInterface;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AuthService implements AuthServiceInterface
{
    /** Failed sign-ins allowed per minute for one email from one IP (the `auth-login` route limiter counts per IP). */
    public const LOGIN_FAILURES_PER_MINUTE = 5;

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly TokenFactory $tokens,
        private readonly DummyPassword $dummyPassword,
        private readonly SettingServiceInterface $settings,
    ) {}

    /**
     * The account and its first session are created in one transaction, so a failure while issuing the tokens leaves
     * no account behind. Two sign-ups at once can both pass validation: the loser hits the unique index and gets the
     * same 422 as the validation rule when the email was taken, or one more try when only the generated username
     * collided (the next try generates another).
     *
     * @throws RegistrationClosed when an admin closed registration
     */
    public function register(RegisterData $data, Request $request): AuthResponse
    {
        if (! $this->settings->registrationOpen()) {
            throw new RegistrationClosed;
        }

        try {
            $pair = $this->createAccount($data, $request);
        } catch (UniqueConstraintViolationException $collision) {
            if ($this->users->findByEmail($data->email) !== null) {
                throw new EmailAlreadyRegistered;
            }

            try {
                $pair = $this->createAccount($data, $request);
            } catch (UniqueConstraintViolationException) {
                throw $this->users->findByEmail($data->email) !== null ? new EmailAlreadyRegistered : $collision;
            }
        }

        event(new Registered($pair->user));

        return AuthResponse::fromTokenPair($pair);
    }

    public function login(LoginData $data, Request $request): AuthResponse
    {
        $failures = self::loginFailuresKey($data->email, $request->ip());

        if (RateLimiter::tooManyAttempts($failures, self::LOGIN_FAILURES_PER_MINUTE)) {
            throw new TooManyLoginAttempts(self::LOGIN_FAILURES_PER_MINUTE, RateLimiter::availableIn($failures));
        }

        $user = $this->users->findByEmail($data->email);

        // Accounts made by social sign-in store no password until they set one, so password login is refused
        // for them whatever is typed, with the same answer as a wrong password. Both they and unknown emails are
        // checked against a dummy hash, so every refusal takes as long as a wrong password.
        if (! $user || ! $user->hasPassword()) {
            $this->dummyPassword->check($data->password);
        }

        if (! $user
            || ! $user->hasPassword()
            || ! Hash::check($data->password, $user->password)
            || ! $user->is_active) {
            RateLimiter::hit($failures, 60);

            throw new InvalidCredentials;
        }

        RateLimiter::clear($failures);
        $this->upgradeHash($user, $data->password);

        return AuthResponse::fromTokenPair($this->tokens->issue($user, $request, remember: $data->remember));
    }

    /**
     * The rate-limiter key of failed sign-ins for one email from one client address (an IPv6 /64 counts as one, see
     * `RateLimitAddress`). Hashed, so a 255-character email never overflows the cache store's key column.
     */
    public static function loginFailuresKey(string $email, ?string $ip): string
    {
        return 'auth-login:'.hash('sha256', $email.'|'.RateLimitAddress::of($ip));
    }

    public function refresh(string $plainRefreshToken, Request $request): AuthResponse
    {
        return AuthResponse::fromTokenPair($this->tokens->rotate($plainRefreshToken, $request));
    }

    public function logout(string $plainRefreshToken): void
    {
        $this->tokens->revoke($plainRefreshToken);
    }

    public function revokeSessions(User $user): void
    {
        $this->tokens->revokeAllForUser($user);
    }

    public function activeSessionCount(User $user): int
    {
        return $this->tokens->activeSessionCount($user);
    }

    public function replaceSessions(User $user, Request $request): AuthResponse
    {
        // The refresh cookie never reaches routes outside /api/auth, so the presenting session's choice is read from
        // its access token (`rem`); a token without the claim (issued before it existed) counts as remembered.
        $remember = $this->tokens->rememberedBy($request);

        $pair = DB::transaction(function () use ($user, $request, $remember): TokenPair {
            $version = $this->tokens->revokeAllForUser($user);

            return $this->tokens->issue($user, $request, sessionVersion: $version, remember: $remember);
        });

        return AuthResponse::fromTokenPair($pair);
    }

    private function createAccount(RegisterData $data, Request $request): TokenPair
    {
        return DB::transaction(function () use ($data, $request): TokenPair {
            $user = $this->users->create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => $data->password,
            ]);

            return $this->tokens->issue($user, $request);
        });
    }

    /**
     * After a successful sign-in, a hash made with an older cost (or algorithm) is replaced by a fresh one of the
     * same password. Skipped while an email change or an account deletion is pending: their emailed links are bound
     * to the password hash and would stop working. The next sign-in after that upgrades it. The write only applies
     * while the stored hash is still the one just verified, so a reset or change committed meanwhile is never undone.
     */
    private function upgradeHash(User $user, string $password): void
    {
        if (Hash::needsRehash($user->password) && $user->pending_email === null && $user->deletion_nonce === null) {
            $this->users->rehashPassword($user, $user->password, $password);
        }
    }
}
