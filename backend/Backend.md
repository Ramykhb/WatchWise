# Backend Overview

What each module does. Paths are relative to `backend/`. The backend is a Laravel 12 / PHP 8.4 API split into feature modules under `app/`: `Admin`, `Auth`, `Cinema`, `Docs`, `Gemini`, `Genre`, `Groq`, `Health`, `Movie`, `MovieList`, `MovieNight`, `MovieRecommendation`, `Notification`, `Person`, `Rating`, `Review`, `Room`, `User`, `Watchlist` and `Weather`. Shared code is in `app/Support/`, the Eloquent models in `app/Models/`, and framework-level providers in `app/Providers/`. Everything up to "Tests" describes the Auth module, and each other module has its own section after it. The mandatory structure rules are in `CLAUDE.md` ("WatchWise Module Architecture").

## Contents

- [Architecture](#architecture)
- [Folder layout](#folder-layout)
- [How a request flows](#how-a-request-flows)
- [Routes](#routes)
- [Config](#config)
- [Setup](#setup)
- [Rate limits](#rate-limits)
- [DTOs](#dtos)
- [Services](#services)
- [Repositories](#repositories)
- [Tokens and current user](#tokens-and-current-user)
- [Controllers](#controllers)
- [Middleware](#middleware)
- [Form requests](#form-requests)
- [Email](#email)
- [Exceptions](#exceptions)
- [Authorization](#authorization)
- [Wiring](#wiring)
- [Tests](#tests)
- [Genre and Movie modules](#genre-and-movie-modules)
- [Person module](#person-module)
- [MovieList and MovieNight modules](#movielist-and-movienight-modules)
- [Watchlist module](#watchlist-module)
- [Rating module](#rating-module)
- [Review module](#review-module)
- [User module](#user-module)
- [Room module](#room-module)
- [Notification module](#notification-module)
- [Report module](#report-module)
- [Admin module](#admin-module)
- [Removed code](#removed-code)
- [Gemini module](#gemini-module)
- [Groq module](#groq-module)
- [Weather module](#weather-module)
- [MovieRecommendation module](#movierecommendation-module)
- [Cinema module](#cinema-module)
- [Health module](#health-module)
- [API documentation (Swagger UI)](#api-documentation-swagger-ui)
- [Deployment (Docker / Render)](#deployment-docker--render)
- [Security notes](#security-notes)
- [Known follow-ups](#known-follow-ups)
- [Adding a new entity](#adding-a-new-entity)

## Architecture

Everything about an entity lives in one folder under `app/`, except its Eloquent model (`app/Models`).
The layers call downward only:

```
Controller -> InterfaceServices -> Services -> InterfaceRepository -> Repository -> Model
```

- **Controllers** only handle HTTP: validation (form requests), calling a service, status codes, cookies and redirects.
- **Services** hold the business rules. They depend on repository interfaces, never on Eloquent.
- **Repositories** are the only place that queries models.
- Interfaces are bound to implementations in each module's `Providers/<Feature>ModuleServiceProvider.php` (`public array $bindings`), for Auth `app/Auth/Providers/AuthModuleServiceProvider.php`. Every module provider is listed in `bootstrap/providers.php`.

## Folder layout

```
app/Auth/
  Controllers/          HTTP layer
  FormRequests/         Laravel form requests: validate the HTTP input and build the DTOs
  Middleware/           auth.jwt, verified.email, role
  InterfaceServices/    Service contracts
  Services/             Business logic
  InterfaceRepository/  Repository contracts
  Repository/           Eloquent queries
  DTOs/
    Requests/           Input data objects built by the form requests
    Responses/          Output data objects returned by the services
  Factories/            TokenFactory, CurrentUserFactory
  CurrentUser/          CurrentUser interface, JwtCurrentUser, GuestUser
  Support/              AuthCookies, TokenPair, OAuthState, DiscordOAuthClient, GooglePhoto, DummyPassword
  Mail/                 Verification, reset and password-changed emails, BrevoTransport
  Listeners/            Sends the verification email on registration
  Exceptions/           Domain exceptions the controllers map to responses
  Providers/            AuthModuleServiceProvider (interface bindings)
```

## How a request flows

1. A request hits a route in one of the per-entity files that `routes/api.php` `require`s (or in `api.php` itself for health and the `/auth` basics).
2. A middleware (`auth.jwt`, `verified.email` or `role`) asks `CurrentUserFactory` who is calling.
3. The factory reads the access token (cookie or Bearer header) and returns a `JwtCurrentUser`, or a `GuestUser` if the token is missing or invalid.
4. On a signed-in route outside `/api/auth`, `auth.jwt` rejects a guest with 401, then `verified.email` rejects an unverified account with 403 `{"message": "Verify your email address to continue.", "code": "email_unverified"}`. Both run before rate limiting and route model binding (the priority list in `bootstrap/app.php`), so a refused request spends no rate-limit slot and learns nothing about the bound record.
5. The controller validates the input and calls a service through its interface.
6. The service applies the rules and uses repositories for data.
7. Policies receive the `CurrentUser` to decide what it may do.

## Routes

| File | Purpose |
| --- | --- |
| `routes/api.php` | Thin entry point: `/api/health`, `/api/auth` register, login, refresh, logout and `me` (with rate limits), the one `['auth.jwt', 'verified.email']` group, and the `require` of every per-entity file below. It holds no other routes |
| `routes/auth_flows.php` | Email verification (the signed link `auth.verify-email` and its POST twin `auth.verify-email.submit`), password reset and Google/GitHub/Discord login routes (outside the verified group) |
| `routes/user.php` | Verified group: `/api/home`, `/api/me/*` (including `/me/email`), `/api/users` and following |
| `routes/lists.php`, `watchlist.php`, `ratings.php`, `reviews.php`, `movie_nights.php` | Verified group: one file each for `/api/lists`, `/api/watchlist`, `/api/ratings`, `/api/reviews`, `/api/movie-nights` |
| `routes/rooms.php`, `notifications.php` | Verified group: `/api/rooms` (with `rooms/socket-auth`) and `/api/notifications` (with `notifications/socket-auth`) |
| `routes/chat.php`, `recommendations.php` | Verified group: `/api/chat/*`; `/api/watched-recommendations`, `/api/people-recommendations`, `/api/following-recommendations`, `/api/taste-profile` |
| `routes/email_change.php`, `account_deletion.php` | The signed email-change links (`me.email.confirm`, `me.email.authorize`, `me.email.cancel`) and account-deletion links (`me.delete.confirm`, `me.delete.cancel`), plus the POST twins of the three that change something (`me.email.confirm.submit`, `me.email.authorize.submit`, `me.delete.confirm.submit`), outside the verified group because they must work without a session |
| `routes/weather.php`, `cinemas.php`, `movies.php`, `recommendations_public.php`, `documentation.php` | Public guest routes, outside the verified group: weather and weather mood, nearby cinemas, TMDB movies/people/genres, `GET /api/movie-recommendations`, documentation |
| `routes/admin.php` | `/api/admin/*`, loaded inside the verified group and adding `role:admin` or `role:admin,moderator` (see [Admin module](#admin-module)) |
| `routes/channels.php` | Reverb private-channel rules (`chat.*`, `room.*`, `rooms.*`, `notifications.*`). Loaded by `GroqModuleServiceProvider` when broadcasting resolves |
| `routes/console.php` | The `rooms:prune-messages` command and the schedule: `rooms:prune-messages` every minute, `users:prune-pending` and `auth:prune-refresh-tokens` hourly (both `withoutOverlapping`) |
| `app/Console/Commands/PrunePendingAccountData.php` | `users:prune-pending`: calls `PendingAccountCleanupServiceInterface::pruneExpired()` and prints how many expired pending email changes and account deletion requests it cleared |
| `app/Console/Commands/PruneRefreshTokens.php` | `auth:prune-refresh-tokens`: calls `RefreshTokenCleanupServiceInterface::pruneStale()` and prints how many refresh tokens it deleted (see [Security notes](#security-notes)) |

All auth endpoints:

| Method and path | Auth | Purpose |
| --- | --- | --- |
| `POST /api/auth/register` | none | Create an account and its first session in one transaction, send the verification email, set the token cookies. A sign-up that loses a race for the same email gets the validation 422 (`EmailAlreadyRegistered`); a collision on the generated username is retried once |
| `POST /api/auth/login` | none | Log in and set the token cookies. Optional `remember` (boolean, default true): see [Remember me](#remember-me) |
| `POST /api/auth/refresh` | refresh cookie | Rotate the refresh token and issue new tokens |
| `POST /api/auth/logout` | refresh cookie | Revoke the session and clear the cookies |
| `GET /api/auth/me` | `auth.jwt` | The current user. Works for an unverified account (`email_verified_at` is `null`), so the frontend can show the verify screen |
| `GET /api/auth/verify-email/{id}/{hash}` (`auth.verify-email`) | signed link, `throttle:auth-verify-email` | The link in the verification email. Changes nothing: redirects to the public page `{FRONTEND_URL}/account-link/verify-email?id=&hash=&expires=&signature=` (the link's own values, `http_build_query`), whose button POSTs the same URL. A bad signature redirects to `{FRONTEND_URL}/account-link/result?kind=verify-email&outcome=invalid\|expired` (`bootstrap/app.php`) |
| `POST /api/auth/verify-email/{id}/{hash}` (`auth.verify-email.submit`) | same signed URL (no login), `throttle:auth-verify-email` | Verifies the address: 200 `{"outcome": "success"}`; `already-verified` when it was verified before (idempotent: a second click, or two at once, changes nothing and fires `Verified` once); `invalid` when the account is gone or its address changed. A bad signature answers 403 `{"outcome": "invalid"}`, an expired one 410 `{"outcome": "expired"}` |
| `POST /api/auth/resend-verification` | none | Resend the verification email |
| `POST /api/auth/forgot-password` | none | Send a reset link (same response whether or not the account exists) |
| `POST /api/auth/reset-password` | reset token | Set a new password, mark an unverified address verified, revoke all sessions and mail `PasswordChangedMail`. The token works once. Also gives an account without a password (social sign-up) its first one |
| `GET /api/auth/social/{google\|github\|discord}` | none, `throttle:auth-social-redirect` | Start the OAuth login (redirects to `provider_unavailable` when the provider is not configured) |
| `GET /api/auth/social/{google\|github\|discord}/callback` | state cookie, `throttle:auth-social-callback` | Finish the OAuth login, then redirect to the frontend (see [Social sign-in](#social-sign-in)) |

## Config

| File | Purpose |
| --- | --- |
| `.env.example` | Every variable the app reads, with placeholders (see [Setup](#setup)) |
| `config/jwt.php` | Signing secret, issuer and audience (and `require_iss_aud`), access lifetime (15 min), refresh lifetime (30 days, sliding), the absolute family lifetime (`refresh_family_max_days`, 90), the same two for logins without "Remember me" (`refresh_session_hours`, 24, and `refresh_session_family_max_days`, 7), the reuse grace window (`refresh_reuse_grace_seconds`, 20), the prune retention (`refresh_prune_retention_days`, 7), cookie names |
| `config/trustedproxy.php` | `proxies`: which proxies' `X-Forwarded-For` and `X-Forwarded-Proto` headers are believed (`TRUSTED_PROXIES`), read per request by Laravel's `TrustProxies` middleware. The trusted headers are set in `bootstrap/app.php` (see [Deployment](#deployment-docker--render)) |
| `config/cors.php` | CORS rules for the frontend |
| `config/services.php` | Google, GitHub and Discord OAuth credentials, frontend URL, `tmdb`, `groq` (`key`, `watched_key`, `model`, `url`), `gemini`, `weatherapi`, `google_places` |
| `config/mail.php` | Default mailer `brevo` (HTTP API) and `brevo-smtp` (SMTP relay) |
| `config/broadcasting.php`, `config/reverb.php` | The Reverb connection, its public host/port/scheme for browsers, and the allowed origins (`REVERB_ALLOWED_ORIGINS`, default the `FRONTEND_URL` host) |
| `phpunit.xml` | Test-only `JWT_SECRET` and `FRONTEND_URL` |
| `app/Providers/AppServiceProvider.php` | Defines the `tmdb` limiter, pins generated URLs to `APP_URL` over https in production (`configureUrlGeneration`), and in the local environment starts Reverb automatically when `php artisan serve` runs. Module bindings, listeners and other limiters live in each module's provider |

## Setup

Copy `.env.example` to `.env` (it is gitignored) and set:

| Variable | Notes |
| --- | --- |
| `DB_*` | Database connection. `.env.example` defaults to SQLite (`database/database.sqlite`); the Docker image ships `pdo_mysql` for MySQL |
| `JWT_SECRET` | Required, at least 32 characters. Generate one with `php -r "echo bin2hex(random_bytes(32));"`. Without it, login and register return a 500 |
| `JWT_ACCESS_TTL`, `JWT_REFRESH_TTL_DAYS` | Token lifetimes, default 15 minutes and 30 days |
| `JWT_REFRESH_FAMILY_MAX_DAYS`, `JWT_REFRESH_REUSE_GRACE_SECONDS`, `JWT_REFRESH_PRUNE_RETENTION_DAYS` | Absolute lifetime of a login (default 90 days), the refresh reuse grace window (default 20 seconds), and how long expired or revoked refresh tokens are kept before pruning (default 7 days) |
| `JWT_REFRESH_SESSION_HOURS`, `JWT_REFRESH_SESSION_FAMILY_MAX_DAYS` | Logins without "Remember me": sliding refresh lifetime (default 24 hours) and absolute lifetime (default 7 days) |
| `JWT_ISSUER`, `JWT_AUDIENCE`, `JWT_REQUIRE_ISS_AUD` | The `iss` (default `APP_URL`) and `aud` (default the host of `APP_URL`) every access token carries and must match; `JWT_REQUIRE_ISS_AUD=false` also accepts tokens without them (default `true`) |
| `TRUSTED_PROXIES` | Comma-separated proxy IPs/CIDRs, or `*` for the peer that connects to the app; empty trusts none. Unset: `*` when `APP_ENV=production`, none otherwise. See [Deployment](#deployment-docker--render) before changing it |
| `FRONTEND_URL` | Where emails link to and where the social login redirects. Also the CORS origin |
| `MAIL_MAILER`, `BREVO_API_KEY`, `MAIL_FROM_*` | The default mailer is `brevo` (Brevo's transactional HTTP API, `app/Auth/Mail/BrevoTransport.php`); the sender must be verified in Brevo. `MAIL_MAILER=brevo-smtp` uses `BREVO_SMTP_USERNAME`/`BREVO_SMTP_KEY` instead. For local work, `MAIL_MAILER=log` writes emails to `storage/logs/laravel.log` |
| `GOOGLE_*`, `GITHUB_*`, `DISCORD_*` | OAuth client id, secret and redirect URI. The redirect URI must match the callback route, for example `http://localhost:8000/api/auth/social/google/callback` |
| `TMDB_API_KEY` | Needed by the Genre, Movie and Person modules. `TMDB_URL` and `TMDB_IMAGE_URL` have defaults |
| `GROQ_API_KEY` | Needed by chat, weather mood, weather recommendations and review moderation/sentiment. `GROQ_MODEL` defaults to `openai/gpt-oss-20b`; `GROQ_API_URL` has a default |
| `GROQ_API_KEY_2` | A second Groq account key, used only by the watched-history and favourite-people recommendations (`keyProfile: 'watched'`) |
| `GEMINI_API_KEY`, `GEMINI_MODEL` | Optional for the standalone Gemini client. `GEMINI_URL` has a default |
| `WEATHERAPI_KEY` | Needed by `/api/weather` and weather recommendations (WeatherAPI.com). `WEATHERAPI_URL`, `WEATHERAPI_DEFAULT_LATITUDE`/`_LONGITUDE` (Beirut) and `WEATHERAPI_TIMEZONE` (`Asia/Beirut`) have defaults |
| `GOOGLE_PLACES_API_KEY` | Needed by `/api/nearby-cinemas` (Google Places API, new) |
| `REVERB_*` | Socket server credentials (generate with `php artisan reverb:install`), `REVERB_PORT=18080`, and the optional `REVERB_PUBLIC_*` and `REVERB_ALLOWED_ORIGINS` |

Then run `composer install`, `php artisan key:generate` and `php artisan migrate`. `composer run dev` is recommended locally (`PHP_CLI_SERVER_WORKERS=4`) so slow API calls don't block other pages; run `php artisan schedule:work` too for room message, pending account data and refresh token pruning.

## Rate limits

Every limiter is a named limiter defined in the provider of the module it protects, and routes use it with `throttle:<name>`. The plain `throttle:N,M` middleware is avoided because it shares one bucket per IP across routes.

| Limiter | Defined in | Per minute | Used by |
| --- | --- | --- | --- |
| `auth-login` | `AuthModuleServiceProvider` | 20 per IP (every request); plus 5 failed sign-ins per email + IP, counted in `AuthService::login()` | `POST /api/auth/login` |
| `auth-register`, `auth-forgot-password`, `auth-resend-verification` | `AuthModuleServiceProvider` | 10 per IP and 3 per email + IP | the matching auth route |
| `auth-reset-password` | `AuthModuleServiceProvider` | 10 per IP and 5 per email + IP | `POST /api/auth/reset-password` |
| `auth-refresh` | `AuthModuleServiceProvider` | 30 per IP | `POST /api/auth/refresh` |
| `auth-verify-email` | `AuthModuleServiceProvider` | 20 per IP | the verification link and its confirming POST, one shared bucket |
| `auth-social-redirect`, `auth-social-callback` | `AuthModuleServiceProvider` | 30, per IP only | `GET /api/auth/social/{provider}` and its `/callback`, one bucket each. Over the limit they redirect to the frontend failure page with `reason=too_many_attempts` (and `Retry-After`), clearing the state cookie, instead of a JSON 429 in the middle of the OAuth round trip |
| `ai` | `GroqModuleServiceProvider` | 10 | `POST /api/weather-mood`, `GET /api/movie-recommendations`, `/api/watched-recommendations`, `/api/people-recommendations` (all call paid AI APIs) |
| `chat` | `GroqModuleServiceProvider` | 10 (and 100 per hour) | `POST /api/chat/messages`, per signed-in user |
| `me-email-change` | `UserModuleServiceProvider` | 3 (and 10 per hour), per account | `PUT /api/me/email`, `POST /api/me/email/resend` |
| `me-email-confirm` | `UserModuleServiceProvider` | 20, per IP | the signed email-change links (`me.email.confirm`, `me.email.authorize`, `me.email.cancel`) and the POSTs that confirm them, one shared bucket (opening a link and confirming it spends two) |
| `me-delete-confirm` | `UserModuleServiceProvider` | 20, per IP | the signed account-deletion links (`me.delete.confirm`, `me.delete.cancel`) and the confirming POST, one shared bucket |
| `report` | `ReportModuleServiceProvider` | 20 per hour | `POST /api/rooms/{room}/messages/{message}/report`, `POST /api/rooms/{room}/report`, `POST /api/lists/{list}/report` and `POST /api/users/{username}/report`, one shared bucket per signed-in user (every request counts, a repeat report included). `POST /api/reviews/{review}/report` keeps its own `throttle:10,1` |
| `admin` | `AdminModuleServiceProvider` | 120 | every `/api/admin/*` route, per signed-in staff member |
| `user-search` | `UserModuleServiceProvider` | 60 | `GET /api/users`, per signed-in user |
| `user-follows` | `UserModuleServiceProvider` | 60 | `POST`/`DELETE /api/users/{username}/follow` and the follow-request accept/decline routes, one shared bucket per signed-in user |
| `user-avatar` | `UserModuleServiceProvider` | 10 | `POST`/`DELETE /api/me/avatar`, one shared bucket per signed-in user |
| `user-profile-update` | `UserModuleServiceProvider` | 30 | `PATCH /api/me`, `PATCH /api/me/preferences`, one shared bucket per signed-in user |
| `weather` | `WeatherModuleServiceProvider` | 30 | `GET /api/weather` |
| `tmdb` | `AppServiceProvider` | 60 | the eight TMDB routes (movies, trending, search, discover, movie details, person, collaborators, genres) |
| `room-messages`, `room-reads`, `room-socket`, `room-invites` | `RoomModuleServiceProvider` | 30, 120, 60, 30 | the matching room routes, per signed-in user |
| `notification-socket` | `NotificationModuleServiceProvider` | 30 | `POST /api/notifications/socket-auth`, per signed-in user |

"Per IP" means `App\Auth\Support\RateLimitAddress::of($request->ip())`: an IPv4 address as is, an IPv4-mapped IPv6 address as its IPv4 address, and any other IPv6 address as its /64 prefix (one host usually holds a whole /64 and could otherwise rotate addresses to get fresh buckets). `$request->ip()` is the client address from `X-Forwarded-For` when the request comes from a trusted proxy (`TRUSTED_PROXIES`), else the connecting address, so without a trusted proxy every visitor behind the proxy shares one bucket. No auth bucket is keyed by the email alone. Every auth request counts in its limiter's per-IP bucket, so changing the email never resets it. Register, forgot-password, resend-verification and reset-password also count every request in a per-(email + IP) bucket (`email-ip:<lowercased email>|<address>`), so one IP can hit one address fewer times than it may make requests in all. Login counts only failed attempts per (email + IP): `AuthService::login()` checks `RateLimiter::tooManyAttempts(AuthService::loginFailuresKey($email, $ip), 5)` (`auth-login:` plus the SHA-256 of `<email>|<address>`, so a 255-character email never overflows the cache key column) before looking the account up, hits it (60 s decay) on every `InvalidCredentials`, clears it on success, and over the limit throws `TooManyLoginAttempts`, rendered like the throttle middleware's 429 (`{"message": "Too Many Attempts."}` with `Retry-After`, `X-RateLimit-Limit`, `X-RateLimit-Remaining` and `X-RateLimit-Reset`). `ai`, `weather` and `tmdb` count per IP; the chat, room and notification limiters count per user (`bootstrap/app.php` runs `auth.jwt` and `verified.email` before throttling, so the user is known and an unverified account never spends a slot).

A few routes still use plain `throttle:N,M`: `PUT /api/me/password` and `PUT /api/me/password/set` (6), `PUT /api/ratings/{tmdbId}` (60), `PUT /api/reviews/mine` (20), `POST /api/reviews/{review}/helpful` (60), `POST /api/reviews/{review}/report` (10), `/api/chat/connection` and `/api/chat/auth` (60), `/api/following-recommendations` and `/api/taste-profile` (60), `/api/nearby-cinemas` (30).

Trade-off of this design: an attacker spread over many IPs can no longer lock one victim out of signing in or recovering the account (the old per-email bucket let anyone who knew an address block it from any IP), because their failures land in their own (email + IP) buckets and the owner's IP has its own. In exchange, password guessing against one account is bounded per IP only (5 failures per minute per IP), not per account, and targeted mail-bombing of one address (forgot-password, resend-verification) is bounded by 3 mails per minute per attacking IP rather than per address. It relies on the client IP being right, i.e. `TRUSTED_PROXIES` set for the real proxy (see [Deployment](#deployment-docker--render)).

## DTOs

Plain `final readonly` classes in `app/Auth/DTOs/`. Services take Request DTOs and return Response DTOs, so they never see raw arrays, `Request` bodies or Socialite types.

| File | Purpose |
| --- | --- |
| `Requests/RegisterData`, `LoginData` | Built by `RegisterRequest::toDto()` and `LoginRequest::toDto()`. `LoginData::$remember` defaults to true |
| `Requests/ForgotPasswordData`, `ResetPasswordData`, `ResendVerificationData` | Built by the matching form requests |
| `Requests/VerifyEmailData` | The `id` and `hash` from the signed verification link |
| `Responses/VerifyEmailOutcome` | Enum answered by the confirming POST of the verification link: `success`, `already-verified`, `invalid` (`expired` and signature failures come from `bootstrap/app.php`) |
| `Requests/SocialCallbackData` | The callback's result for the service: provider, whether the state matched, `code`, OAuth `error`, and the user agent and IP the session is recorded with. Built by `SocialCallbackRequest::toDto()` |
| `Requests/SocialLoginData` | What the module needs from an OAuth provider, built inside `SocialAuthService` (`fromSocialite()`, or `DiscordOAuthClient`) |
| `Responses/UserResponse` | The public user JSON (same fields as before) |
| `Responses/AuthResponse` | The user plus the tokens. `body()` is the JSON, and `AuthCookies` turns the tokens into cookies |

`TokenFactory` still returns the internal `TokenPair`. The services convert it with `AuthResponse::fromTokenPair()`.

## Services

Contracts in `app/Auth/InterfaceServices/`, implementations in `app/Auth/Services/`.

| Service | Purpose |
| --- | --- |
| `AuthService` | Register, login, refresh and logout. Throws `InvalidCredentials`, `TooManyLoginAttempts`, `EmailAlreadyRegistered` or `InvalidRefreshToken`. Registration creates the user and issues its tokens in one transaction; on `UniqueConstraintViolationException` it answers `EmailAlreadyRegistered` when the email now exists, otherwise (a generated username collided) tries once more. Login counts failures per (email + IP) (see [Rate limits](#rate-limits)); an unknown email or an account without a password is checked against `DummyPassword` so it takes as long as a wrong password; after a successful login a hash made with an older cost is upgraded (`Hash::needsRehash`, `UserRepository::rehashPassword`, no model events, compare-and-set on the hash just verified so a reset or change committed meanwhile is never reverted), except while an email change or account deletion is pending (their links are bound to the hash) |
| `RefreshTokenCleanupService` | `pruneStale()`: deletes refresh tokens that expired more than `jwt.refresh_prune_retention_days` ago, and tokens revoked that long ago other than by rotation (logout, sign-out-everywhere, family revocation); a rotated token is kept until it has been expired that long. The retention is never less than the reuse grace window. 1000 rows per `DELETE`. Run hourly by `auth:prune-refresh-tokens` |
| `PasswordResetService` | Sends the reset link (deferred until after the response) and resets the password. Tokens are 64 random characters stored as SHA-256 (`replaceFor` deletes and inserts in one transaction). A reset runs in one transaction: it locks the latest row (`latestForUpdate`), checks age and token, deletes the rows and refuses (`InvalidResetToken`) when none was deleted, so two requests with one token cannot both succeed; then sets the password, calls `AccountServiceInterface::passwordResetThroughEmail()` (verifies an unverified address, clears `password_set_without_proof_at` and a pending email change) and revokes every session. After commit it mails `PasswordChangedMail`. Every attempt hashes the presented token once, so the time does not tell whether a reset was requested |
| `EmailVerificationService` | Sends the signed verification link (60 minutes), resends it, and verifies a link (`VerifyEmailOutcome`), marking it with the compare-and-set `UserRepository::markEmailVerifiedIfUnverified` |
| `SocialAuthService` | Builds the provider redirect URL and completes the callback: checks the state result and the configuration, fetches the provider's user (Discord through `DiscordOAuthClientInterface`), drops Google's placeholder photo (`GooglePhotoInterface`), then signs in, links or creates the account. Refuses unverified provider emails and takes over unverified local accounts by resetting their password. See [Social sign-in](#social-sign-in) |
| `DiscordOAuthClient`, `GooglePhoto` (`Support/`) | The two third-party HTTP clients the social service uses, behind `InterfaceServices/DiscordOAuthClientInterface` and `GooglePhotoInterface` (bound in `AuthModuleServiceProvider`) |

## Repositories

Contracts in `app/Auth/InterfaceRepository/`, implementations in `app/Auth/Repository/`.

| Repository | Model | Purpose |
| --- | --- | --- |
| `UserRepository` | `User` | Find by id or email, create, mark verified (also compare-and-set: only while `email_verified_at` is null), update or rehash the password |
| `RefreshTokenRepository` | `RefreshToken` | Create, find (with row lock for rotation, by hash or id), lock the owner's row, mark rotated or grace used, the earliest `created_at` of a family, revoke a family or all of a user's tokens, delete stale tokens in chunks |
| `PasswordResetRepository` | `PasswordReset` | Replace (one transaction), find the latest row under a row lock and delete the reset rows for an email (returns the count) |
| `SocialAccountRepository` | `SocialAccount` | Find by provider, create (provider access tokens are not stored) |

## Tokens and current user

| File | Purpose |
| --- | --- |
| `Factories/TokenFactory.php` | Issues HS256 access tokens (`iss`, `aud`, `sub`, `iat`, `exp`, `sv`, `rem`) and refresh tokens, rotates and revokes refresh tokens, parses access tokens (HS256 with our secret only; `iss` and `aud` must match). `refreshExpiresAt($familyStartedAt, $remember)` is the one place a refresh token's expiry is computed; `rememberedBy($request)` reads the calling session's `rem` claim |
| `Factories/CurrentUserFactory.php` | Resolves the current user once per request, or a guest |
| `CurrentUser/CurrentUser.php` | Interface that policies and middleware depend on |
| `CurrentUser/JwtCurrentUser.php` | The logged-in user. The role is read from the database (the token carries none), so a demotion applies immediately |
| `CurrentUser/GuestUser.php` | The anonymous user, never authenticated |
| `Support/TokenPair.php` | Holds a user's access and refresh tokens with their expiry times and the login's `remember` choice |
| `Support/AuthCookies.php` | Sets and clears the httpOnly access and refresh cookies (`Secure` outside local/testing). A remembered login's refresh cookie expires with its token; without "Remember me" both are session cookies |
| `Support/DummyPassword.php` | `check($password)`: a `Hash::check` against a bcrypt hash nobody knows, made once per cost setting and cached (`auth:dummy-password-hash:<rounds>`); when the cache store throws, it is made in-process instead and kept in a static property for the process, so sign-in never answers 500 because of the cache, so the login paths without a hash of their own take as long as a wrong password |

### Remember me

`POST /api/auth/login` takes an optional boolean `remember` (`LoginRequest`, default true so API clients keep the old behaviour). It is stored on the refresh token row (`refresh_tokens.remember`, boolean, default true; migration `2026_11_11_130000_add_remember_to_refresh_tokens_table`) and copied by every rotation, graced siblings included.

| | `remember` true | `remember` false |
| --- | --- | --- |
| Cookies | Access cookie `jwt.access_ttl` minutes, refresh cookie until its token expires | Both session cookies (no Expires/Max-Age): gone when the browser closes |
| Refresh token | Slides `jwt.refresh_ttl_days` (30) per rotation | Slides `jwt.refresh_session_hours` (24) per rotation |
| Family cap | `jwt.refresh_family_max_days` (90) after sign-in | `jwt.refresh_session_family_max_days` (7) after sign-in |

Registration and social sign-in are remembered. Setting or changing the password re-issues a session of the same kind as the calling one: the refresh cookie never reaches `/api/me/*` (its path is `/api/auth`), so the choice is read from the `rem` claim of the calling access token (`TokenFactory::rememberedBy`; a token without the claim, issued before it existed, counts as remembered). The server-side expiry is what ends a session login: a browser that restores session cookies on restart keeps it until the refresh token expires.

## Controllers

All in `app/Auth/Controllers/`. They contain no database queries.

| File | Purpose |
| --- | --- |
| `AuthController.php` | Register, login, refresh, logout and `me` |
| `EmailVerificationController.php` | `open` (GET: redirects the emailed link to the public confirmation page), `verify` (POST: verifies, answers the outcome) and `resend` |
| `PasswordResetController.php` | Forgot-password and reset-password |
| `SocialAuthController.php` | Google, GitHub and Discord redirect and callback: sets and clears the state cookie, turns every outcome into a redirect |

## Middleware

All in `app/Auth/Middleware/`.

| Alias | File | Behavior |
| --- | --- | --- |
| `auth.jwt` | `AuthenticateJwt.php` | Requires a logged-in user, otherwise 401. Does not look at the email, so `/api/auth/me` works while unverified |
| `verified.email` | `EnsureEmailVerified.php` | 401 for a guest, 403 `{"message": "Verify your email address to continue.", "code": "email_unverified"}` if the email is unverified |
| `role` | `EnsureRole.php` | 403 unless the user has one of the required roles |

**A verified email is required everywhere.** Every route that needs a signed-in user, except `/api/auth/*`, needs a verified email too, admins and moderators included. `routes/api.php` applies `['auth.jwt', 'verified.email']` once, to a group that `require`s the signed-in per-entity files (`user.php`, `lists.php`, `rooms.php`, `chat.php`, `admin.php` and so on, all bare routes with no `auth.jwt` wrapper), so a route in one of those files inherits it. `/api/auth/*` (register, login, refresh, logout, `me`, the verification link, resend, forgot/reset password, social login) stays open so an unverified account can verify, resend the link or sign out; login, register and refresh still issue tokens to it. Public guest routes (TMDB, weather, weather mood, movie recommendations, nearby cinemas, health, documentation) are unchanged. `tests/Feature/Auth/RequireVerifiedEmailTest.php` fails if a route with `auth.jwt` outside `/api/auth` lacks `verified.email`. The OpenAPI spec adds the `EmailUnverified` 403 to every secured operation outside `/api/auth` (`OpenApiSpec::documentEmailVerification`).

## Form requests

Each module keeps its Laravel form requests in a `FormRequests/` folder:

- `app/Auth/FormRequests/`: `RegisterRequest`, `LoginRequest`, `ForgotPasswordRequest`, `ResetPasswordRequest`, `ResendVerificationRequest`.
- `app/Movie/FormRequests/`: `MovieListRequest`, `MovieTrendingRequest`, `MovieSearchRequest`, `MovieDiscoverRequest`.
- Every other module with input does the same (listed in each module's section below).

A form request checks the incoming HTTP request before the controller runs, and turns it into a clean object for the service.

| Part | What it does |
| --- | --- |
| `rules()` | The validation rules for one endpoint. For example, `MovieSearchRequest` requires `q` (1 to 100 characters) and allows `page` from 1 to 500 and `year` from 1874 to 2100. `RegisterRequest` checks that the email is unique, that the name is 1 to 255 characters after trimming without control characters (Cc: line breaks, tabs, NUL), the line and paragraph separators (U+2028, U+2029), bidirectional embeddings, overrides and isolates (U+202A-U+202E, U+2066-U+2069), direction marks (U+200E, U+200F, U+061C), the BOM (U+FEFF) and unassigned, surrogate or private-use code points; the joiners U+200D/U+200C and variation selectors stay allowed, so emoji sequences work (`App\Support\DisplayName`, also used by `UpdateProfileRequest`), and applies the shared password rule (below) |
| Failed validation | Laravel stops and returns a 422 with the errors listed per field, for example `{"errors": {"q": ["The q field is required."]}}`. The controller and the service never run |
| `toDto()` | Turns the validated input into a typed DTO from `DTOs/Requests/` (for example `MovieSearchQuery`) that the service uses |
| `authorize()` | Can reject a request based on who is making it. Ours return `true`, because the auth middleware handles that |

A controller method type-hints the form request, and Laravel runs it automatically. There is no manual `validate()` call:

```php
public function search(MovieSearchRequest $request): JsonResponse
{
    return response()->json($this->movies->search($request->toDto()));
}
```

```
HTTP request -> FormRequest (validate) -> toDto() -> Controller -> Service -> Repository
```

The form request is the only layer that knows about the raw HTTP input. Everything after it works with typed, trusted data.

**One password rule.** Every new password (register and reset here, set and change in the User module) uses `App\Support\NewPasswordRules::rules()`: required, string, no NUL byte (`not_regex:/\x00/`, bcrypt ignores everything after one), `confirmed`, `Password::min(8)->letters()->numbers()`, and at most 72 bytes (`App\Support\MaxBytes`, which counts bytes with `strlen`; Laravel's `max` counts characters, and bcrypt reads only the first 72 bytes). `UpdatePasswordRequest` adds `different:current_password`. `LoginRequest` only caps the password at 1024 bytes (`bail` first, so a huge body is neither regex-checked nor hashed): the old rules had no maximum, so passwords longer than 72 bytes chosen before keep working (bcrypt reads their first 72 bytes, as it always did).

Do not confuse `FormRequests/` with `DTOs/Requests/`. The form request validates the HTTP input. The DTO is the plain data object it produces and hands to the service.

**Emails are stored lowercase.** `Ali@Example.com` and `ali@example.com` are one sign-in address everywhere:

- Every write is lowercased and trimmed by set mutators on the models (`User::email`, `User::pending_email`, `PasswordReset::email`), through `App\Support\EmailAddress::normalize()`, so registration, social sign-up, a completed email change, factories and seeders all store the lowercase form.
- Every form request that takes an `email` uses the `App\Support\NormalizesEmailInput` trait, which lowercases and trims it in `prepareForValidation()`, before the rules run: `RegisterRequest`, `LoginRequest`, `ForgotPasswordRequest`, `ResetPasswordRequest`, `ResendVerificationRequest` here, and `RequestEmailChangeRequest` and `UpdateProfileRequest` in the User module.
- Uniqueness (`RegisterRequest`, `RequestEmailChangeRequest`) uses the shared `App\Support\UniqueEmail` rule, which compares `LOWER(email)`, so even a row stored before lowercasing (`Ali@Example.com`) blocks `ali@example.com`. The message stays "The email has already been taken.".
- Lookups (`UserRepository::findByEmail` for login, password reset, verification resend and social linking, and `PasswordResetRepository`) lowercase their input and compare against the column directly, so the unique index still serves them. Social sign-in lowercases the provider's email before looking up or creating the account. The per-email rate-limit bucket uses the same normalisation.
- Migration `2026_11_11_000004_lowercase_emails` lowercases and trims `users.email`, `users.pending_email` and `password_resets.email` (in chunks of 500, in one transaction). It checks first: when two accounts' emails are equal once lowercased, it throws a `RuntimeException` listing the user ids and stored addresses and changes nothing. Resolve those accounts by hand and run it again. A pending email change to an address another account uses (exactly or in another case) does not stop it: that change could never be applied, so its `pending_email`, `pending_email_nonce`, `pending_email_authorized_at` and `pending_email_link_sent_at` are cleared in the same transaction, and the count is logged (`lowercaseEmails()` returns it). `down()` does nothing (the original casing is not kept). Lowercasing an address that was stored mixed-case invalidates links bound to it (verification `sha1(email)`, account deletion, email change); the member asks for a new one.

## Email

| File | Purpose |
| --- | --- |
| `Listeners/SendEmailVerification.php` | Sends the verification link when a user registers. Registered in `AuthModuleServiceProvider` |
| `Mail/VerifyEmailMail.php` | The verification email |
| `Mail/ResetPasswordMail.php` | The password reset email |
| `Mail/PasswordChangedMail.php` | "Your password was changed", sent after a successful reset, with a forgot-password link ("wasn't you? reset again") |
| `Mail/BrevoTransport.php` | The `brevo` mail transport (registered with `Mail::extend` in `AuthModuleServiceProvider`). Posts to `https://api.brevo.com/v3/smtp/email`; its errors never include the email body, links or key |
| `resources/views/mail/layout.blade.php` | The shared WatchWise-branded HTML layout (images in `public/images/mail/`) |
| `resources/views/mail/verify-email.blade.php`, `reset-password.blade.php`, `password-changed.blade.php` | HTML templates |
| `resources/views/mail/text/*.blade.php` | Plain-text versions of every email. They print with `{!! !!}` (never rendered as HTML, so `{{ }}` would turn the `&` of a link into `&amp;` and break it) |
| `app/User/Mail/*`, `resources/views/mail/confirm-email-change`, `authorize-email-change`, `email-change-requested`, `email-changed` | The email change emails (User module), on the same layout, with plain-text versions in `resources/views/mail/text/`. The layout takes an optional secondary link (`secondaryPrompt`, `secondaryAction`, `secondaryUrl`), used for the "cancel this change" link |
| `app/Admin/Mail/AccountSuspendedMail.php`, `AccountReinstatedMail.php`, `resources/views/mail/account-suspended`, `account-reinstated` | Sent when staff suspend or reinstate a member (see [Admin module](#admin-module)). The layout's button and copy-link footer are optional: it shows them only when the view passes a `url`, so the suspension email, which has nothing to link to, has neither; the reinstatement email links to the sign-in page |
| `app/Support/MailText.php` | `line()`: every email (HTML and text) prints the member's name through it, turning each run of the characters `DisplayName` refuses (emoji joiners are kept) into one space, so a stored or provider-supplied name cannot fake extra lines or reorder text |

Emails are sent directly, not queued: production runs no queue worker. Every account email of the Auth, User and Admin modules goes through the shared `App\Support\SendsMailAfterResponse` trait: `mailLater($to, $mail)` runs after the response is sent (`defer()`), so the response time is the same for known and unknown emails and a broken mail server never fails the request. `$mail` may be a closure that builds the mail once before the first attempt (the reset link stores its token there, the verification link signs its URL there). Sending is tried twice with `retry()`, 1 s apart (`Sleep`, faked in tests), and each attempt is bounded by an 8-second transport timeout (`BrevoTransport::TIMEOUT_SECONDS`, and `timeout` of the `brevo-smtp` mailer), so the worst case is about 17 seconds (see the deployment note on deferred work); a final failure is reported once as `App\Support\Exceptions\MailNotDelivered`, whose message names only the mail class and the failure's class (for example `ResetPasswordMail was not delivered after 2 attempt(s): TransportException.`), never the address, the link or the transport's own message, and chains no previous exception.

## Exceptions

In `app/Auth/Exceptions/`. Services throw them and controllers turn them into responses.

`InvalidCredentials` and `InvalidResetToken` render their own 401 and 422 responses. `TooManyLoginAttempts` renders the throttle middleware's 429 (body and rate-limit headers). `EmailAlreadyRegistered` renders the same 422 as the `UniqueEmail` rule (`{"message": "The email has already been taken.", "errors": {"email": [...]}}`). `InvalidRefreshToken` is caught by `AuthController`, which clears the cookies.

The social routes are browser navigations, so their exceptions are never rendered as JSON. The expected outcomes implement `SocialLoginFailure` and carry the redirect `reason()`: `InvalidOAuthState` (`invalid_state`), `SocialLoginNotConfigured` (`provider_unavailable`), `SocialLoginCancelled` (`access_denied`), `EmailRequired` (`email_required`), `UnverifiedProviderEmail` (`email_unverified`) and `AccountDisabled` (`account_disabled`). They are not reported (`SocialLoginNotConfigured` logs a warning instead, see below). `SocialAuthController` catches that interface, and catches any other `Throwable` (including `SocialProviderFailed`, `SocialAccountNotSaved` and failed Socialite or HTTP calls) as `provider_error`, reporting it first. Reported messages carry no personal data: the OAuth `error` value is logged only when it matches `^[a-z_]{1,64}$` (otherwise `unknown`), and a database failure while saving the account becomes `SocialAccountNotSaved` with only the SQLSTATE (no SQL, bindings or previous exception). `SocialLoginRedirect` (`Support/`) builds every redirect back to the frontend, for the controller and for the rate limiters.

### Social sign-in

- **State.** The redirect sets an HttpOnly, SameSite=Lax cookie (10 minutes) to `<provider>:<40 random characters>` (`Support/OAuthState`). Outside local/testing it is Secure and named `__Host-oauth_state`: browsers only accept that prefix with Secure, Path=/ and no Domain, so a sibling subdomain cannot plant a state of its own (login CSRF). It is built as a Symfony cookie directly so `SESSION_DOMAIN` never adds a Domain; local/testing use the plain `oauth_state` name because the cookie is not Secure there. The callback accepts the echoed `state` only when it equals the cookie and starts with that callback's provider, so a state issued for Google is refused at the GitHub callback. Every callback response, success or failure, expires the cookie, so a state is never accepted twice.
- **Order of checks** (`SocialAuthService::loginWithProvider`): state, then configuration (`client_id`, `client_secret` and `redirect` must be set and not `CHANGE_ME`; otherwise a warning naming the provider and the missing key, never its value, is logged), then the OAuth `error` parameter (`access_denied` is a cancelled consent; anything else is a provider error), then the provider exchange. A blank provider user id is a provider error.
- **Resolving the account** (one transaction): an identity already in `social_accounts` signs in to its user even when the provider no longer returns an email. Only an unlinked identity needs an email (`email_required`) that the provider verified (`email_unverified`). Trust is an explicit map that fails closed (`SocialAuthService::EMAIL_VERIFICATION`): Google and Discord report it per user; GitHub is trusted because Socialite's GitHub driver keeps its default `user:email` scope and takes the email from /user/emails, returning only the verified primary address (otherwise none, so `email_required`); any other provider is never trusted. Taking over an unverified local account also drops its pending email change (`EmailChangeServiceInterface::discardPendingChange()`), so its registrant cannot have a fresh confirmation link resent. `is_active` is checked before any write, both for a linked identity and for an account matched by email, so a disabled account is refused with nothing changed (no takeover, no verification, no link, no session).
- **Concurrent first sign-ins** (double click, two tabs) can collide on `users.email`, `users.username` or `social_accounts (provider, provider_id)`. The losing transaction rolls back on `UniqueConstraintViolationException` and the lookup runs again (3 attempts in all), which finds the winner's rows and signs in to that account. A third collision is a reported `SocialAccountNotSaved` (`provider_error`). Every write of one attempt is in one transaction, so a failure part-way through (for example after a takeover cleared the password) leaves the account as it was.
- **Provider tokens** are not stored. Migration `2026_11_11_100000_clear_social_account_access_tokens` erased the ones saved earlier; the nullable `social_accounts.access_token` column stays until a later change drops it, and `access_token` is no longer fillable on `SocialAccount`.
- `TokenFactory::issue()` takes a `Request`, so the service rebuilds a minimal one from the DTO's user agent and IP rather than receiving the HTTP request.

## Authorization

| File | Purpose |
| --- | --- |
| `app/Providers/AuthorizationServiceProvider.php` | Maps models to policies, binds the guest as the default current user, makes the Gate use `CurrentUser` |

Most policies stay in `app/Policies/` because each belongs to the entity it protects. The Movie List, Movie Night, Watchlist and User policies live in their own module folders (`app/MovieList/Policies/`, `app/MovieNight/Policies/`, `app/Watchlist/Policies/`, `app/User/Policies/`):

| Policy | Rule |
| --- | --- |
| `ReviewPolicy` | Owner-based access |
| `WatchlistPolicy` (`app/Watchlist/Policies/`) | Owner-based access |
| `MovieListPolicy`, `MovieListItemPolicy` (`app/MovieList/Policies/`) | Private lists are visible to their owner only; `report`: a list you can see that is not yours |
| `MovieNightPolicy` (`app/MovieNight/Policies/`) | Organizer and invitees can view a night; only the organizer can change it |
| `DiscussionRoomPolicy` (`app/Room/Policies/`) | Public rooms are open, private ones are for members and invitees; `report`: a room you can see that you did not create |
| `MessagePolicy` | Authenticated users can read and post in discussion rooms; author edits, author or admin deletes; `report`: a message in a room you can see that is not yours |
| `NotificationPolicy` (`app/Notification/Policies/`) | Members see and change only their own feed notifications, never room read receipts |
| `UserPolicy` (`app/User/Policies/`) | Private profiles are visible to the owner and admins; `report`: any active member's profile that is not yours |
| `AdminPolicy` (`app/Admin/Policies/`) | The `admin.*` gates; `admin.reports` (`handleReports`) lets admins and moderators triage reports |

## Wiring

| File | Purpose |
| --- | --- |
| `bootstrap/app.php` | Registers the API and console routes, the three middleware aliases, the trusted proxy headers (only `X-Forwarded-For` and `X-Forwarded-Proto`; the proxies themselves come from `config/trustedproxy.php`), runs `AuthenticateJwt` before `ThrottleRequests`, sends bad or expired verification, email-change and account-deletion link signatures (`App\Support\AccountLink::forRoute()`) to the public result page (GET) or answers them with JSON 403/410 (POST), and renders JSON errors for every `api/*` request |
| `bootstrap/providers.php` | Registers `AppServiceProvider`, `AuthorizationServiceProvider` and all 19 module service providers |

## Tests

| Location | Covers |
| --- | --- |
| `tests/Feature/Auth/AuthenticationTest.php` | Register, login, refresh, logout, throttling (per-IP bucket, per email + IP failures, owner on another IP not locked out, success clears the counter, 429 shape), hash upgrade on login |
| `tests/Feature/Auth/AccountFlowsTest.php` | Email verification (GET changes nothing, POST verifies once, bad and expired signatures), password reset, social login |
| `tests/Feature/Auth/RegistrationTest.php` | The display-name rule (register and profile), the lost email race (422 like validation), the username collision retry, no account left when its session cannot be issued |
| `tests/Feature/Auth/PasswordResetTest.php` | SHA-256 token storage, single-use tokens, reset verifies the address and sends the notice, same answer for unknown address and wrong token, mail retries and the context-free report |
| `tests/Feature/Auth/RememberMeTest.php` | Session cookies and the 24-hour token without "Remember me", the default, rotation and graced siblings keeping the choice, both absolute caps, password change preserving it |
| `tests/Feature/Support/NewPasswordRulesTest.php`, `PlainTextMailTest.php` | The shared password rule on register, reset, set and change (weak, 73 bytes, multibyte, NUL) and the login cap; every email's text part printing links and names unescaped, and names without control or direction characters (emoji joined by ZWJ kept) |
| `tests/Feature/Auth/SocialLoginTest.php` | Social sign-in failure reasons (each clears the state cookie), linked accounts without an email, disabled accounts left untouched, the unique-violation retry, cross-provider state, unconfigured providers, error reporting, throttling. `SignsInWithProviders` is the shared test helper |
| `tests/Feature/Auth/ClearSocialAccessTokensMigrationTest.php` | The migration erasing stored provider tokens |
| `tests/Feature/Auth/LowercaseEmailTest.php` | Emails stored lowercase; registration, login, password reset and social sign-in in any case; legacy mixed-case rows; the `lowercase_emails` migration (aborts on user email collisions, clears pending changes to a taken address) |
| `tests/Feature/Auth/BrevoVerificationTest.php` | The Brevo transport and the verification email end to end |
| `tests/Feature/Auth/GooglePhotoTest.php` | Detecting Google's generated placeholder avatar, and dropping it during the Google callback |
| `tests/Feature/Auth/MiddlewareTest.php` | The three middleware and CORS |
| `tests/Feature/Auth/RefreshTokenLifecycleTest.php` | Refresh reuse grace (once, same user agent, valid successor, family cap), absolute family lifetime, inactive user and expired token revoking the family |
| `tests/Feature/Auth/AccessTokenValidationTest.php` | Wrong secret, `alg=none`, another algorithm, wrong or missing `iss`/`aud` |
| `tests/Feature/Auth/AuthCookiesTest.php` | `Secure` in production, cookie lifetimes |
| `tests/Feature/Auth/NullPasswordTest.php` | An account without a password: the same 401 as an unknown email, no cookies, checked against the dummy hash (never the NULL password); a reset sets its first one |
| `tests/Feature/Auth/TrustedProxiesTest.php` | Per-client rate-limit buckets behind a trusted proxy, `X-Forwarded-For` ignored otherwise, `X-Forwarded-Proto` honoured, `X-Forwarded-Host`/`-Port` ignored, signed links pinned to `APP_URL` in production and validated through the proxy, the `TRUSTED_PROXIES` defaults |
| `tests/Feature/Auth/PruneRefreshTokensTest.php`, `FamilyStartedAtMigrationTest.php` | `auth:prune-refresh-tokens` and its schedule; the `family_started_at` backfill |
| `tests/Feature/Auth/SessionInvalidationTest.php` | `session_version`: revoking sessions (password set, change, reset, social takeover) ends older access tokens |
| `tests/Feature/Auth/PolicyTest.php` | The policy rules, including messages |
| `tests/Feature/Auth/Repository/` | Each repository against the database |
| `tests/Unit/Auth/` | Each service with fake repositories, and `UserResponse` matching the model's JSON |

## Genre and Movie modules

`app/Genre/` and `app/Movie/` use the same layers as Auth. TMDB has no database, so the repositories call the TMDB web API instead of Eloquent. Movie uses Genre's `GenreResponse`.

```
app/Genre/
  Controllers/          GenreController
  InterfaceServices/    GenreServiceInterface
  Services/             GenreService (maps TMDB data to DTOs, caches genres for a day)
  InterfaceRepository/  GenreRepositoryInterface
  Repository/           GenreRepository
  DTOs/Responses/       GenreResponse
  Providers/            GenreModuleServiceProvider (interface bindings)
app/Movie/
  Controllers/          MovieController
  FormRequests/         MovieListRequest, MovieTrendingRequest, MovieSearchRequest, MovieDiscoverRequest
  InterfaceServices/    MovieServiceInterface, MovieCatalogServiceInterface
  Services/             MovieService (TMDB), MovieCatalogService (the local `movies` table)
  InterfaceRepository/  MovieRepositoryInterface, MovieCatalogRepositoryInterface
  Repository/           MovieRepository (TMDB), MovieCatalogRepository (Eloquent, `LocalMovie`)
  DTOs/Requests/        MovieListQuery, MovieTrendingQuery, MovieSearchQuery, MovieDiscoverQuery, LocalMovieQuery, UpdateLocalMovieData
  DTOs/Responses/       MovieResponse, MoviePageResponse, MovieDetailsResponse (+ CastMemberResponse, PersonResponse, TrailerResponse), CatalogSyncResponse
  Exceptions/           MovieNotFound (renders 404 "Movie not found.")
  Providers/            MovieModuleServiceProvider (interface bindings)
app/Support/            Shared code: TmdbHttpClient (adds the API key, timeout, optional retry, wraps failures; getPool() runs up to 5 requests in parallel),
                        MediaType (`movie`/`tv` and the frontend key "550" / "tv-1399"), LikePattern (escaped LIKE search),
                        EmailAddress (lowercase + trim), NormalizesEmailInput (form request trait), UniqueEmail (`LOWER(email)` uniqueness rule)
  Exceptions/           TmdbUnavailable (renders a fixed 502 so TMDB's own error never reaches the client), TmdbNotFound (TMDB answered 404)
```

| Endpoint | Purpose |
| --- | --- |
| `GET /api/movies?list=&page=&sort=&release_from=` | A page of `popular` (default), `top_rated`, `now_playing` or `upcoming` movies. `upcoming` uses TMDB Discover for films whose primary release date is `release_from` (default today in UTC) or later, sorted by `popularity` (default) or `release_date`; the frontend sends the viewer's first eligible local date so filtering happens before pagination. Upcoming and trending use a 20 s timeout with one retry |
| `GET /api/movies/trending?type=movie\|tv` | TMDB's weekly trending titles, cached for an hour |
| `GET /api/movies/search?q=&page=&year=&type=` | Search by title. `q` is required (max 100 characters). `type` is `movie` (default), `tv` or `all` (movies and shows merged by popularity, up to 10 pages) |
| `GET /api/movies/discover` | Filtered browsing: `genres`, `tv_genres`, `year_from`, `year_to`, `min_rating` (0 to 5), `runtime_min`, `runtime_max`, `language`, `sort` (`popularity`, `rating`, `release_date`, `title`), `type` (`movie`, `tv`, `all`), `page` |
| `GET /api/movies/{id}?type=tv` | One movie (or TV show with `type=tv`) with genres, certification, runtime, original language, directors, top 12 cast, a YouTube trailer, budget, box office and 10 related titles (fetched in one TMDB call). Unknown ids return 404 |
| `GET /api/genres` | The movie genres (id and name), cached for a day |

- All are public. Together with the two Person routes they share the `tmdb` rate limit: 60 requests per minute per IP (see [Rate limits](#rate-limits)), so one client cannot use up the TMDB quota.
- Movie details are cached for 6 hours per title; unknown titles and TMDB failures are not cached. `credits()` and `metadata()` (used by the recommendations and taste profile) fetch many titles' credits/genres at once through `getPool()` and cache each for 6 hours.
- List pages are cached for an hour (`tmdb.list.{list}.{sort}.{release_from}.{page}`); TMDB failures are not cached.
- **Local catalogue:** `MovieCatalogService::remember()` stores a film in the `movies` table (`LocalMovie`, with its first director) the first time someone rates or reviews it. Admins edit, hide or feature these rows (see [Admin module](#admin-module)). TV shows are never stored.
- **Hidden films (`is_hidden`) are hidden from browsing.** `App\Support\HiddenMovies` is the one filter (`hides($mediaType, $tmdbId)` for payloads, `exclude($query)` for queries). It only hides movies, never a TV show that shares the id. It is read once per service call that needs it (one small query, and none when a request has nothing to check, such as the related titles of a TV show) and applied after the TMDB caches, so hiding or showing a film applies on the next request and the cached payloads stay valid. `MovieCatalogRepositoryInterface::hidden()` is the one place that builds it. `MovieService` calls it on the repository (the catalogue service depends on `MovieService`, so it cannot depend back); every other module uses `MovieCatalogServiceInterface::hiddenMovies()`. Members no longer see a hidden film in: `GET /api/movies` (every list, so Releases and Home's Coming soon too), `/api/movies/trending`, `/api/movies/search` and `/api/movies/discover` (browse by genre; a page can hold fewer than 20 titles, `total_results` goes down by the hidden titles dropped from it and never below 0, `total_pages` stays TMDB's), the related titles of `GET /api/movies/{id}`, the filmography and "Known for" of `GET /api/people/{id}`, the recommendation shelves ([MovieRecommendation module](#movierecommendation-module)), other members' lists and profiles, the Home activity feed ([User module](#user-module)) and the public rooms list ([Room module](#room-module)). Unchanged: `GET /api/movies/{id}` still opens a hidden film for anyone with the link, and existing ratings, reviews, watchlists and lists are neither altered nor deleted; the owner still sees the film in their own watchlist, lists and profile; the admin endpoints still list hidden films. Movie night candidates and votes, `next_night.leader` on Home and notifications are private or group data and are not filtered. Admin stats rankings also leave hidden films out, as before.
- **Featured films (`is_featured`)** feed one place: `GET /api/home` returns `featured`, up to 10 catalogue films that are featured and not hidden, most recently edited (`updated_at`) first, as movie cards (`id`, `media_type` `movie`, `title`, `overview`, `release_date`, `year`, `rating` and `vote_count` 0 because the catalogue keeps no votes, `genre_ids`, `poster_url`, `backdrop_url` null). It is empty when nothing is featured.
- Movie lists are paginated with `page` from 1 to 500 and 20 movies per page. Responses include `page`, `total_pages` (capped at 500, TMDB's limit) and `total_results`. Genres are a short fixed list, so they are not paginated.
- Ratings are converted from TMDB's 0 to 10 scale to 0 to 5. Adult content is always excluded.
- Setup: set `TMDB_API_KEY` in `.env`. `TMDB_URL` and `TMDB_IMAGE_URL` have working defaults in `config/services.php`.
- Routes are in `routes/movies.php` and the config is the `tmdb` entry in `config/services.php`.
- Tests: `tests/Feature/Movie/` (`HiddenMoviesTest` covers hiding and the cache), `tests/Feature/Genre/` and `tests/Feature/Support/` (the endpoints, repositories, catalogue and `TmdbHttpClient`), `tests/Feature/Social/TvShowsTest.php`, and `tests/Unit/Movie/` and `tests/Unit/Genre/` (the services).

## Person module

`app/Person/` is the person page (actor, director, writer…). Like Movie, its repository calls TMDB instead of the database.

```
app/Person/
  Controllers/          PersonController (show, collaborators)
  InterfaceServices/    PersonServiceInterface
  Services/             PersonService
  InterfaceRepository/  PersonRepositoryInterface
  Repository/           PersonRepository (/person/{id}?append_to_response=movie_credits, /movie/{id}/credits)
  DTOs/Responses/       PersonDetailsResponse, PersonCreditResponse, CollaboratorsResponse, CollaboratorResponse
  Exceptions/           PersonNotFound (404)
  Providers/            PersonModuleServiceProvider
```

| Endpoint | Purpose |
| --- | --- |
| `GET /api/people/{id}` | Biography, birth/death, place of birth, department, photo, a "Known for" row (6 most-voted films) and the full filmography (one entry per film, newest first, with every role: `Actor`, `Director`, `Screenplay`…) |
| `GET /api/people/{id}/collaborators` | The 3 people who share the most films (at least 2) with this person, counted over their 10 most-voted films' top 15 billed actors and key crew jobs. Each has their most frequent role and the shared film count |

- Both are public, under the `tmdb` limiter, and cached for 6 hours (`tmdb.person.{id}`, `tmdb.person.{id}.collaborators`). The filmography and "Known for" leave out hidden films after the cache (see [Genre and Movie modules](#genre-and-movie-modules)); collaborators are counted from the full filmography. A TMDB outage makes collaborators an empty list (not cached) rather than an error.
- Tests: `tests/Feature/Person/` (`PersonHiddenMoviesTest` for hidden films).

## MovieList and MovieNight modules

`app/MovieList/` and `app/MovieNight/` use the same layers as Auth. Their Eloquent models stay in `app/Models/`, and `MovieListResource` stays in `app/MovieList/Resources/` because the profile also uses it.

```
app/MovieList/
  Controllers/          MovieListController
  FormRequests/         MovieListIndexRequest, StoreMovieListRequest, UpdateMovieListRequest, AddMovieListItemRequest, RemoveMovieListItemRequest, ReportMovieListRequest
  InterfaceServices/    MovieListServiceInterface
  Services/             MovieListService (transactions)
  InterfaceRepository/  MovieListRepositoryInterface
  Repository/           MovieListRepository (scopes, search, syncing films)
  DTOs/Requests/        MovieListSearchQuery, CreateMovieListData, UpdateMovieListData, ListFilmData
  Resources/            MovieListResource
  Policies/             MovieListPolicy, MovieListItemPolicy
  Providers/            MovieListModuleServiceProvider (interface bindings)
app/MovieNight/
  Controllers/          MovieNightController
  FormRequests/         StoreMovieNightRequest, UpdateMovieNightRequest, InviteGuestsRequest, RsvpRequest, VoteRequest
  InterfaceServices/    MovieNightServiceInterface
  Services/             MovieNightService (invites, RSVPs, votes)
  InterfaceRepository/  MovieNightRepositoryInterface
  Repository/           MovieNightRepository
  DTOs/Requests/        CreateMovieNightData, UpdateMovieNightData, InviteGuestsData, RsvpData, VoteData
  DTOs/Responses/       MovieNightResponse, MovieNightOverviewResponse
  Exceptions/           NotInvitedToMovieNight (403), DeclinedGuestCannotVote (403), VotingClosed (422)
  Policies/             MovieNightPolicy
  Providers/            MovieNightModuleServiceProvider (interface bindings)
```

- The routes did not change (`/api/lists` and `/api/movie-nights`, in `routes/lists.php` and `routes/movie_nights.php`), apart from `POST /api/lists/{list}/report` (`reason`: `spam`, `harassment`, `inappropriate` or `other`; optional `details`, max 500; `throttle:report`). `MovieListPolicy::report` refuses your own list and one you cannot see (403); `MovieListService::report()` hands it to `ReportServiceInterface`. 201 when new, 200 with the earlier report when repeated.
- The form requests run the policy check in `authorize()`, so a forbidden request is rejected before validation, as before.
- `GET /api/lists` with an unknown `scope` now fails validation (422 with an `errors` object) instead of returning the message "Unknown scope.".
- A public list whose owner's profile is followers-only or private is for that owner's followers only: `scope=public` leaves it out for everyone else, and `GET /api/lists/{list}` answers 403 (`MovieListPolicy::view`; the owner and admins always see it). `scope=following` already only lists people you follow.
- Other members' lists leave out hidden films ([Genre and Movie modules](#genre-and-movie-modules)): `GET /api/lists` with `scope=public` or `following`, and `GET /api/lists/{list}` for anyone but the owner, drop them from `items`, `cover`, `posters` and the `films` count. The owner's own lists, and `scope=mine`, keep them. `MovieListRepository` applies the filter to the items relation and the count with `HiddenMovies::exclude()`; the service passes `HiddenMovies::none()` for the owner.
- Tests: `tests/Feature/Social/ContentTest.php`, `tests/Feature/Social/HiddenMoviesMemberViewsTest.php` and `tests/Feature/Social/MovieNightRulesTest.php`.

## Watchlist module

`app/Watchlist/` uses the same layers as Auth. The user's want-to-watch list and watch history are the same rows: a film with status `watched` is history. The `Watchlist` model stays in `app/Models/`.

```
app/Watchlist/
  Controllers/          WatchlistController
  FormRequests/         StoreWatchlistRequest, UpdateWatchlistRequest
  InterfaceServices/    WatchlistServiceInterface
  Services/             WatchlistService (sets watched_at from the status)
  InterfaceRepository/  WatchlistRepositoryInterface
  Repository/           WatchlistRepository
  DTOs/Requests/        AddToWatchlistData, UpdateWatchlistStatusData
  DTOs/Responses/       WatchlistResponse, WatchlistItemResponse, AddToWatchlistResponse
  Policies/             WatchlistPolicy
  Providers/            WatchlistModuleServiceProvider (interface bindings)
```

- The routes did not change (`/api/watchlist`, in `routes/watchlist.php`).
- `POST /api/watchlist` answers 201 for a new film and 200 when the film was already there.
- Tests: `tests/Feature/Social/ContentTest.php` and `tests/Feature/Social/WatchlistRulesTest.php`.

## Rating module

`app/Rating/` is the Movie page's "Your rating". A rating is a `reviews` row without text (migration `2026_10_26_000001_allow_rating_only_reviews`), so a rating and a review of the same title share one row.

```
app/Rating/
  Controllers/          RatingController (show, update)
  FormRequests/         ShowRatingRequest, RateMovieRequest
  InterfaceServices/    RatingServiceInterface
  Services/             RatingService (also remembers the film in the local catalogue)
  InterfaceRepository/  RatingRepositoryInterface
  Repository/           RatingRepository (upsert on user_id, tmdb_id, media_type)
  Providers/            RatingModuleServiceProvider
```

| Endpoint | Purpose |
| --- | --- |
| `GET /api/ratings/{tmdbId}?type=movie\|tv` | `{rating}`, null when you haven't rated the title |
| `PUT /api/ratings/{tmdbId}` | Body `rating` (1 to 5), `type` (default `movie`). A new rating-only row is approved straight away; an existing review keeps its text and moderation state |

- Tests: `tests/Feature/Social/RatingTest.php`.

## Review module

`app/Review/` holds the Movie page's Reviews section and the review data the Admin module moderates. The `Review` and `ReviewVote` models stay in `app/Models/` (its reports are rows of the generic `Report` model, see the [Report module](#report-module)), and `ReviewPolicy` in `app/Policies/`.

```
app/Review/
  Controllers/          MovieReviewController (index, save, destroy, helpful, unhelpful, report)
  FormRequests/         MovieReviewIndexRequest, SaveReviewRequest, MyReviewRequest, ReportReviewRequest
  DTOs/Requests/        MovieReviewQuery (sorts, 10 per page), SaveReviewData (max 2000 characters), ReportReviewData
  DTOs/Responses/       MovieReviewResponse, ReportReviewResponse
  InterfaceServices/    MovieReviewServiceInterface (the page), ReviewServiceInterface (admin queue), ReviewModerationServiceInterface
  Services/             MovieReviewService, ReviewService, ReviewModerationService (Groq labels), SentimentClassifier (Groq sentiment)
  InterfaceRepository/  MovieReviewRepositoryInterface, ReviewRepositoryInterface
  Repository/           MovieReviewRepository, ReviewRepository
  Providers/            ReviewModuleServiceProvider
```

| Endpoint | Purpose |
| --- | --- |
| `GET /api/reviews?tmdb_id=&type=&sort=helpful\|newest\|highest\|lowest&page=` | `summary` (average, counts, star distribution and sentiment split as percentages), `mine`, a page of `reviews` and `has_more` |
| `PUT /api/reviews/mine` | Write or rewrite your review: `tmdb_id`, `type`, `content` (not blank, max 2000), `rating` 1 to 5, `has_spoilers` |
| `DELETE /api/reviews/mine?tmdb_id=&type=` | Remove the text; the star rating stays |
| `POST`, `DELETE /api/reviews/{review}/helpful` | Mark or unmark a review helpful; returns the `helpful` count. Not your own review, and only approved text reviews whose author's privacy shows them to you |
| `POST /api/reviews/{review}/report` | Report with a `reason` (`Spam`, `Links`, `Spoilers`, `Offensive language`); 201, or 200 when you had already reported it and the reason was replaced. It files the report through `ReportServiceInterface` (see [Report module](#report-module)) |

- **Moderation on save:** `ReviewModerationService` asks Groq (strict JSON schema) for the labels `offensive language`, `spoiler` and `spam`. No labels means the review is approved at once; any label sends it to the admin queue as pending. If Groq fails, the labels are null and the review waits for a moderator. `has_spoilers` is set when the author ticks it or Groq finds a spoiler.
- **Sentiment:** `SentimentClassifier` asks Groq for positive/neutral/negative and falls back to the star rating (4 to 5 positive, 3 neutral, 1 to 2 negative).
- `Review::reports()` is a `hasMany` of `Report` on `target_id` limited to `target_type = review`. A review is flagged while one of those reports is `open`. Approving a review dismisses its open reports and rejecting it resolves them (`ReviewService::approve()` and `reject()` call `ReportServiceInterface::resolveForTarget()` with the moderator); deleting a review deletes its reports, as the old foreign key did, and rewriting it clears them.
- Saving a movie review also remembers the film in the local catalogue. Marking helpful sends a `review_helpful` notification; taking it back removes it.
- Tests: `tests/Feature/Social/MovieReviewsTest.php`, `tests/Feature/Review/ReviewReportTest.php` and `tests/Feature/Admin/AdminReviewsTest.php`.

## User module

`app/User/` holds everything about the person behind the account, in the same layers as Auth: their own account (`/api/me`), public profiles and following (`/api/users`) and the Home page (`/api/home`). The `User`, `UserPreference` and `Follow` models stay in `app/Models/`. Sign-in, registration and tokens stay in the Auth module.

```
app/User/
  Controllers/          UserController (account under /me, profiles and following under /users, and /home)
  FormRequests/         UpdateProfileRequest, UpdatePreferencesRequest, UpdatePasswordRequest, SetPasswordRequest, UploadAvatarRequest, DeleteAccountRequest, UserSearchRequest, RequestEmailChangeRequest, ReportUserRequest
  InterfaceServices/    AccountServiceInterface, ProfileServiceInterface, HomeServiceInterface, EmailChangeServiceInterface, AccountDeletionServiceInterface
  Services/             AccountService (settings, password, avatar, social disconnect, the one account-erasing path), ProfileService (search, profile page, follow), HomeService, ProfilePrivacy (what a viewer may see of a profile), EmailChangeService (pending email, confirmation link, notices), AccountDeletionService (password check or emailed confirmation link, then AccountService::deleteAccount)
  InterfaceRepository/  AccountRepositoryInterface, ProfileRepositoryInterface, HomeRepositoryInterface, AccountDeletionRepositoryInterface
  Repository/           AccountRepository, ProfileRepository, HomeRepository, AccountDeletionRepository (pending deletion columns, compare-and-set clear)
  DTOs/Requests/        UpdateProfileData, UpdatePreferencesData, ChangePasswordData, SetPasswordData, DeleteAccountData, UserSearchQuery, RequestEmailChangeData, ConfirmEmailChangeData, AccountDeletionLinkData
  DTOs/Responses/       AccountResponse, ProfileResponse, UserSearchResponse, FollowResponse, HomeResponse, EmailChangeOutcome (enum: success, check-new-inbox, cancelled, invalid, expired, taken), DeleteAccountResponse (deleted or link sent), AccountDeletionOutcome (enum: deleted, cancelled, invalid, expired)
  Resources/            MemberResource (the small public card of a user, used by many modules)
  Exceptions/           IncorrectPassword (422 on the wrong field), ProfileSectionHidden (403 JSON), NoPendingEmailChange (422), CannotDisconnectLastLogin (422 on `provider`), PasswordAlreadySet (409 `password_already_set`), NoPasswordSet (409 `no_password_set`)
  Mail/                 ConfirmEmailChangeMail, EmailChangeRequestedMail, EmailChangedMail, PasswordAddedMail; account deletion: ConfirmAccountDeletionMail, AccountDeletedMail. All three services send through the shared `App\Support\SendsMailAfterResponse` trait (see [Email](#email))
  Policies/             UserPolicy (view, follow, report)
  Providers/            UserModuleServiceProvider (interface bindings, the me-email-change and me-email-confirm, me-account-deletion and me-delete-confirm rate limiters, plus user-search, user-follows, user-avatar and user-profile-update)
```

- Routes are in `routes/user.php`: `/api/home`; `/api/me` (show, update, delete), `/me/preferences`, `/me/password` and `/me/password/set` (`me.password.set`; see below), `/me/avatar` (upload/delete, public disk; see below), `/me/social/{google|github|discord}` (disconnect), `/me/follow-requests/{username}` (accept/decline), `/me/email` (see below); `/api/users` (search) and `/api/users/{username}` with `followers`, `following` and `follow`/unfollow. Account deletion adds `routes/account_deletion_signed_in.php` (`DELETE /api/me/delete-request`, in the verified group) and `routes/account_deletion.php` (the two signed links, outside it); see [Deleting the account](#deleting-the-account).
- `POST /api/users/{username}/report` (`ReportUserRequest`, `throttle:report`; `reason`: `spam`, `harassment`, `inappropriate` or `other`, optional `details` max 500) reports a profile. `UserPolicy::report` refuses your own profile and a deactivated member's (403) but not a private or followers-only one; `ProfileService::report()` files it through `ReportServiceInterface`: 201 when new, 200 with the earlier report when repeated. Tests: `tests/Feature/Report/ReportContentTest.php`.
- Disconnecting a provider (`DELETE /api/me/social/{provider}`, `AccountService::disconnectSocial`) is a no-op when it is not linked. An account without a password (`password` is NULL, `has_password: false`) cannot remove its last linked provider: it would be locked out. It gets 422 `CannotDisconnectLastLogin` (message on `errors.provider`) until it sets a password or links another provider. `AccountRepository::deleteSocialAccountsKeepingAnother` checks and deletes inside a transaction holding a row lock on the user, so two disconnects at once cannot remove both providers. Accounts with a password of their own (including a first one set in Settings) disconnect freely.
- `ProfilePrivacy` decides what a viewer may see of a profile. The profile page decides `canView` once and reads every section through `sectionsShownTo()`, so a followers-only profile does not repeat the follow query per section. `my_following` still lists everyone the viewer follows: "View all" loads the full followers/following lists and each person there needs a Follow/Following button. The 8 recent followers/following are the newest follows first, and the review count skips empty text like Home does.
- **Search input** (`GET /api/users?q=&limit=`): nothing is rejected. `UserSearchQuery::fromInput` treats a `q` that is not a string (`q[]=x`) as empty, trims it and cuts it to 100 characters, and clamps `limit` to 1-30 (a non-number falls back to 10).
- **No password:** `users.password` is nullable and `password IS NULL` is the only signal that an account has none (`User::hasPassword()`, `has_password` in `GET /api/me`). Google, GitHub and Discord sign-ups are created with NULL, and a social sign-in that takes over an unverified local account sets it back to NULL (`UserRepositoryInterface::clearPassword`, which also clears `password_set_without_proof_at`). Password sign-in refuses such an account with the same 401 as a wrong password (no `Hash::check` against NULL). "Forgot password" works for it and sets a first password that counts as proof of the inbox. Migration `2026_11_11_000000_make_users_password_nullable` makes the column nullable; `2026_11_11_000001_clear_social_placeholder_passwords` turns accounts that still held the old shared placeholder into NULL-password accounts (see [Security notes](#security-notes)).
- **Setting a first password** (`PUT /api/me/password/set`, `SetPasswordRequest` -> `SetPasswordData` -> `AccountService::setPassword`): body `password` + `password_confirmation` (the shared rule `App\Support\NewPasswordRules`: 8+ characters with letters and numbers, at most 72 bytes, no NUL). Only while the password is NULL, otherwise 409 `{"message", "code": "password_already_set"}`. `AccountRepository::setFirstPassword` re-checks NULL under a row lock, so two requests at once cannot both set one. It stamps `password_set_without_proof_at`, mails `PasswordAddedMail`, drops a pending email change and rotates the sessions like a change. Same response as a change.
- **Changing the password** (`PUT /api/me/password`, change only): `current_password` is always required. An account without a password gets 409 `{"message", "code": "no_password_set"}` (after validation) and must use `/me/password/set`. `AccountService::changePassword` revokes every refresh token and calls `AuthServiceInterface::replaceSessions()`, which issues a fresh token pair for the calling request; the controller sets it as the new cookies. The refresh cookie is only sent to `/api/auth`, so the password route cannot tell which session is current, hence the fresh session instead of "revoke all but mine". The fresh session keeps the calling session's "Remember me" choice, read from the `rem` claim of its access token (see [Remember me](#remember-me)). This device stays signed in, every other device is signed out at once (see `session_version` under [Security notes](#security-notes)). A pending email change is still discarded.
- **First password notice:** `PUT /api/me/password/set` mails `PasswordAddedMail` to the account address (deferred, errors only logged), saying a password was added and linking to forgot-password if it was not them. An ordinary change sends nothing.
- **Display names:** `UpdateProfileRequest` applies `App\Support\DisplayName` like registration (same character list).
- **Lowercase usernames:** handles are stored lowercase. The `User` model lowercases `username` on every write (a set mutator; generated handles were already lowercase), `UpdateProfileRequest::prepareForValidation` lowercases (and strips a leading `@`) before the uniqueness check, which compares `LOWER(username)` so even a handle stored before lowercasing (`Mali`) blocks `mali`, while the regex still accepts mixed-case input. `/api/users/{username}` route binding lowercases the value (`User::resolveRouteBindingQuery`) and search lowercases the text it matches against usernames. Migration `2026_11_11_000002_lowercase_usernames` lowercases existing handles; on a case-only collision the oldest account (by `created_at`, then id) keeps it and the others get the first free numeric suffix within 20 characters (`mali2`, `mali3`...).
- **Lowercase emails:** emails are stored lowercase (see "Form requests" for the Auth side and the migration). `RequestEmailChangeRequest` lowercases the new address before validating it, refuses your own address in any case with "That is already your email address." and checks uniqueness with `App\Support\UniqueEmail` (`LOWER(email)`, ignoring you). `AccountRepository::emailBelongsToAnotherUser`, checked again when the approval and confirmation links are opened, compares `LOWER(email)` too, so a legacy mixed-case row still answers `email_change=taken`. `pending_email` is stored lowercase by the `User` model. `UpdateProfileRequest` lowercases an unchanged `email` before comparing it. Admin user search lowercases the text it matches against emails. Tests: `EmailChangeTest.php` and `tests/Feature/Auth/LowercaseEmailTest.php`.
- **Avatars:** `users.avatar` holds a path on the public disk (`avatars/<file>`) for uploaded pictures, or the external `http(s)` URL copied by social sign-in. The `User` model's `avatar` accessor returns a full URL for a stored path and leaves external URLs untouched, so every response (`UserResponse`, `MemberResource`, Admin, notifications) shows a full URL and `APP_URL` is never stored. Uploading stores the new file first, updates the row, then deletes the old file (and deletes the new one again if the update fails). Migration `2026_11_10_075004_convert_user_avatars_to_storage_paths` converts old `.../storage/avatars/<file>` URLs to paths (`down()` restores URLs).
- `/api/home` returns the 3 busiest rooms, your next movie night (accepted guests and the leading vote) and `activity`: the 8 newest items from people you follow (reviews, ratings, public lists, watched titles and watchlist additions), each kind filtered by that person's privacy switches. Spoiler reviews have no excerpt. Activity on hidden films ([Genre and Movie modules](#genre-and-movie-modules)) is left out, and a list's cover is its first visible film. It also returns `featured`: the films admins featured and did not hide (see the same section).
- On another member's profile (`/api/users/{username}`), hidden films are left out of `lists` (items, cover, posters and `films` count), `watched`, `top4` and `counts.films`. On your own profile they stay. `counts.reviews` and `counts.lists` are unchanged.
- Tests: `tests/Feature/Social/ProfileTest.php`, `PrivacyTest.php`, `SearchTest.php`, `AccountRulesTest.php` and `tests/Feature/User/HomeActivityTest.php`, `HomeFeaturedTest.php`, `EmailChangeTest.php`, `PasswordProofBackfillTest.php`, `SetPasswordTest.php` (set vs change password), `LowercaseUsernameTest.php`, `ClearPlaceholderPasswordsMigrationTest.php`, `tests/Feature/Auth/NullPasswordTest.php` (login refused, reset sets a password), `AccountSafetyTest.php` (search input, password sessions, first-password notice, avatar storage) and `Repository/AccountRepositoryTest.php` (the compare-and-set email-change writes).

### Changing the sign-in email

`PATCH /api/me` does not change the email: it accepts `email` only when it equals the current one (forms send the whole profile) and rejects a different one with 422. The flow is:

| Method and path | Auth | Purpose |
| --- | --- | --- |
| `PUT /api/me/email` | `auth.jwt` + `verified.email`, `throttle:me-email-change` | `email` (+ `current_password` when the account has one). Stores the address in `users.pending_email` with a fresh random `pending_email_nonce`. With a password: sets `pending_email_authorized_at = now()`, mails a signed 60-minute link to the new address (`ConfirmEmailChangeMail`) and warns the current address (`EmailChangeRequestedMail`, new address masked as `j***@example.com`, with a cancel link). Without one (`password` is NULL), or while `password_set_without_proof_at` is set (see below): leaves `pending_email_authorized_at` null and mails only the current address (`AuthorizeEmailChangeMail`: a 60-minute approval link plus the cancel link). `email` and `email_verified_at` are untouched |
| `POST /api/me/email/resend` | `auth.jwt` + `verified.email`, `throttle:me-email-change` | Mails the link the change is waiting for again: the approval link to the current address while `pending_email_authorized_at` is null, otherwise the confirmation link to the new one (422 `NoPendingEmailChange` when nothing is pending) |
| `DELETE /api/me/email` | `auth.jwt` + `verified.email` | Clears `pending_email`, `pending_email_nonce` and `pending_email_authorized_at` |
| `GET /api/me/email/authorize/{id}/{hash}` (`me.email.authorize`) | signed link (no login; declared in `routes/email_change.php`), `throttle:me-email-confirm` | The approval link mailed to the current address. Changes nothing: redirects to the public page `FRONTEND_URL/account-link/email-authorize?id=&hash=&expires=&signature=` (the link's own values), whose button POSTs the same URL |
| `POST /api/me/email/authorize/{id}/{hash}` (`me.email.authorize.submit`) | same signed URL (no login), `throttle:me-email-confirm` | When the password proves nothing: the current address approves the change. Sets `pending_email_authorized_at` (compare-and-set) and mails the confirmation link to the new address. Answers 200 `{"outcome": "check-new-inbox\|invalid\|taken"}` (approving again, or twice at once, mails the new address only once and answers `check-new-inbox`) |
| `GET /api/me/email/confirm/{id}/{hash}` (`me.email.confirm`) | signed link (no login; declared in `routes/email_change.php` outside the verified group), `throttle:me-email-confirm` | The link mailed to the new address. Changes nothing: redirects to `FRONTEND_URL/account-link/email-confirm?id=&hash=&expires=&signature=`, whose button POSTs the same URL |
| `POST /api/me/email/confirm/{id}/{hash}` (`me.email.confirm.submit`) | same signed URL (no login), `throttle:me-email-confirm` | Refused (`invalid`) while `pending_email_authorized_at` is null. Otherwise swaps `email = pending_email`, sets `email_verified_at = now()`, clears the pending columns, revokes every session through `AuthServiceInterface::revokeSessions()` and mails the old address (`EmailChangedMail`). Answers 200 `{"outcome": "success\|invalid\|taken"}` |
| `GET /api/me/email/cancel/{id}/{hash}` (`me.email.cancel`) | signed link (no login; declared in `routes/email_change.php`), `throttle:me-email-confirm` | The "this wasn't me" link in the mails to the current address (valid 7 days). Cancelling is the safe direction, so it acts on GET: clears the pending columns of that request only and redirects to the public page `FRONTEND_URL/account-link/result?kind=email-cancel&outcome=cancelled\|invalid\|expired` (`expired` also when nothing is pending any more) |

- `current_password` is required unless the account has no password (`has_password: false`). Such accounts approve from their current address instead, so a stolen session alone cannot send a link to another inbox. Accounts with a password keep the single link to the new address.
- A first password chosen in Settings (`PUT /api/me/password/set`, where no current password exists to ask for) proves nothing, since a stolen session could set it. `AccountService::setPassword` stamps `users.password_set_without_proof_at`, and while it is set the email change takes the approval path even though `current_password` is now required too. It is cleared only when the owner proves the inbox: a password reset through the emailed link (`AccountServiceInterface::passwordResetThroughEmail()`, called by `PasswordResetService::reset`, which also marks an unverified address verified) or a completed email change (`applyPendingEmail`). An ordinary password change with the current password keeps it. Disconnecting the social login does not clear it. The migration backfilled existing accounts, failing closed: a user whose stored password was not the old placeholder (read from `SOCIAL_PLACEHOLDER_PASSWORD` with `env()` inside the migration) was stamped when they have a `social_accounts` row, or when they have none but `email_verified_at` is within 5 minutes of `created_at` (social sign-in verifies at creation, so this catches accounts whose social login was disconnected). The placeholder check was skipped, so these users were stamped regardless, when `SOCIAL_PLACEHOLDER_PASSWORD` was empty. Users on the placeholder (or with a NULL password) and email sign-ups verified later through the emailed link stayed null.
- The writes are compare-and-set (`AccountRepository::authorizePendingEmail`, `applyPendingEmail`, `clearPendingEmail($user, $nonce)`): one conditional `UPDATE` that only matches while the row still holds the same pending address, nonce and password hash (and, for approve/apply, the expected approval state). A request replaced or cancelled between the link check and the write is therefore never approved, applied or cancelled by an older link; zero affected rows give `invalid` (or `check-new-inbox` when a simultaneous click already approved the same request).
- The new address must differ from the current one and must not be any account's `email`. It is checked again when the approval or confirmation link is opened; if someone took it meanwhile, the pending change is dropped and the outcome is `taken`.
- Every link's `hash` is `hash_hmac('sha256', purpose|user id|pending_email|pending_email_nonce|password hash, APP_KEY)`, compared with `hash_equals`. So a link only works for its own purpose (an approval or cancel hash is not a confirmation hash), for the request it was mailed for (the nonce changes on every `PUT`, so asking A, then B, then A again leaves the first link for A dead), and only while the password is unchanged: changing it in Settings, resetting it through forgot-password, setting a first one, or a social sign-in clearing an unverified account's password voids every earlier link without any extra wiring. Expired or tampered signatures are handled in `bootstrap/app.php` for every link (`App\Support\AccountLink`, shared with the Auth module's verification link, maps the route name to its `kind`): a GET redirects to `FRONTEND_URL/account-link/result?kind={kind}&outcome=expired\|invalid`, a POST answers JSON `{"outcome": "expired"}` with 410 or `{"outcome": "invalid"}` with 403.
- Changing or setting the password (`AccountService::changePassword`, `setPassword`) and resetting it (`Auth\Services\PasswordResetService::reset`, inside its transaction, through `AccountServiceInterface::passwordResetThroughEmail()`) also clear the pending change with `EmailChangeServiceInterface::discardPendingChange()`, so Settings stops showing it. Auth only calls the User module's service interface for this, as module rules require; the password-hash binding is what actually voids the links.
- Columns: `pending_email` (migration `2026_11_09_000000_add_pending_email_to_users_table`), `pending_email_nonce` and `pending_email_authorized_at` (migration `2026_11_10_000000_add_email_change_state_to_users_table`, which also drops pending changes made with the old `sha1(pending_email)` links), and `password_set_without_proof_at` (migration `2026_11_10_000001_add_password_set_without_proof_to_users_table`). All are in the `User` model's `$hidden`.
- `pending_email_link_sent_at` (migration `2026_11_10_130000_add_pending_email_link_sent_at_to_users_table`) is the time the link the change waits for was last mailed (request, approval or resend). `users:prune-pending` first stamps pending addresses that have no stamp yet (made before the column existed) with now, one `UPDATE`, so they get a grace window, then clears every change whose stamp is older than `EmailChangeServiceInterface::EXPIRES_MINUTES` (60), plus a leftover nonce or timestamp with no address. The command counts only rows really cleared. Approval and resend refresh the stamp, so a live link is never pruned; `resend` goes through `markPendingEmailLinkSent` (filtered on the nonce) and answers 422 `NoPendingEmailChange` when no row matched, before mailing anything. The links already stop working on their own; this only cleans the columns. The cancel link (7 days) outlives a pruned change: with nothing pending it redirects to the result page with `outcome=expired` (a calm notice) instead of `invalid`, which stays for a wrong hash or a newer request.
- `GET /api/me` (`AccountResponse`) has a top-level `pending_email` (null when nothing is pending) and `pending_email_awaiting_current` (true while the current address still has to approve).
- Emails are sent after the response with up to 2 attempts through `App\Support\SendsMailAfterResponse`, like the Auth emails.

### Deleting the account

`AccountDeletionService` decides how `DELETE /api/me` is confirmed. A password of the member's own proves ownership; no password at all (`password` is NULL), or a first password set in Settings without a current one (`password_set_without_proof_at`, which a stolen session could do), does not, so those accounts confirm from their inbox. Both paths, and an admin deleting a member, end in `AccountService::deleteAccount` (stored avatar file, then the user row; foreign keys cascade), and the address then gets `AccountDeletedMail`.

| Method and path | Auth | Purpose |
| --- | --- | --- |
| `DELETE /api/me` | `auth.jwt` + `verified.email`, `throttle:me-account-deletion` | With a password that proves ownership: `password` is required (422 on `password` when missing or wrong), the account is deleted and the cookies cleared (200, `{"message": "Account deleted."}`). Otherwise `password` is ignored: stores `deletion_requested_at = now()` and a fresh random `deletion_nonce`, mails `ConfirmAccountDeletionMail` to the current address (a 60-minute confirmation link plus a "keep my account" link) and answers 202 (`message`, `deletion_pending: true`). Nothing is deleted yet; a new request voids the earlier links |
| `DELETE /api/me/delete-request` | `auth.jwt` + `verified.email` | Clears the pending deletion (no-op when none). Returns the account |
| `GET /api/me/delete/confirm/{id}/{hash}` (`me.delete.confirm`) | signed link (no login; `routes/account_deletion.php`), `throttle:me-delete-confirm` | Changes nothing: redirects to the public page `FRONTEND_URL/account-link/delete-confirm?id=&hash=&expires=&signature=`, whose button POSTs the same URL. A bad signature redirects to `FRONTEND_URL/account-link/result?kind=delete-confirm&outcome=invalid\|expired` |
| `POST /api/me/delete/confirm/{id}/{hash}` (`me.delete.confirm.submit`) | same signed URL (no login), `throttle:me-delete-confirm` | Consumes the request with a compare-and-set (`AccountDeletionRepository::clearDeletionRequest($user, $nonce)`: one conditional `UPDATE` on the same nonce, email and password hash), then deletes the account through `AccountService::deleteAccount`. Answers 200 `{"outcome": "deleted"}`; used, replaced, cancelled or stale links `{"outcome": "invalid"}`. A bad signature answers 403 `invalid`, an expired one 410 `expired` |
| `GET /api/me/delete/cancel/{id}/{hash}` (`me.delete.cancel`) | signed link (no login), `throttle:me-delete-confirm` | Acts on GET (the safe direction): drops the request it was mailed for only and redirects to the public page `FRONTEND_URL/account-link/result?kind=delete-cancel&outcome=cancelled\|invalid\|expired` |

- `throttle:me-account-deletion` (5 per minute and 10 per hour per account, `UserModuleServiceProvider`) also slows password guessing on the password path. It is applied on the `DELETE /api/me` route in `routes/user.php`. `me-delete-confirm` allows 20 per minute per IP for the two links and the confirming POST, one shared bucket.
- Every link's `hash` is `hash_hmac('sha256', purpose|user id|deletion_nonce|email|password hash, APP_KEY)`, compared with `hash_equals`, and the request must be younger than 60 minutes (`AccountDeletionServiceInterface::EXPIRES_MINUTES`, also the signed URL's lifetime). So a link only works for its own purpose (a cancel hash is not a confirmation hash, and the purposes differ from the email-change ones), for the request it was mailed for, and only while the email and password are unchanged.
- Any new password clears the pending deletion through a `saving` hook on the `User` model (whenever `password` is dirty), which covers `PUT /api/me/password`, `PUT /api/me/password/set`, a reset through forgot-password and a social takeover of an unverified account without touching those flows. Writes that bypass Eloquent are still covered by the password-hash binding, which is what actually voids the links.
- `GET /api/me` (`AccountResponse`) has `deletion_by_email` (true when `DELETE /api/me` mails a link instead of asking for the password) and `deletion_pending` (true while a link is valid: nonce set and requested less than 60 minutes ago).
- `users:prune-pending` also clears, in one `UPDATE`, every pending deletion whose `deletion_requested_at` is older than `AccountDeletionServiceInterface::EXPIRES_MINUTES` (60), the same lifetime as the confirm link (rule in `PendingAccountCleanupService`, queries in `AccountDeletionRepository::clearExpiredDeletionRequests`).
- Columns: `deletion_requested_at` and `deletion_nonce` (migration `2026_11_10_120000_add_deletion_request_to_users_table`), both in the `User` model's `$hidden`.
- Social accounts created before NULL passwords, while `SOCIAL_PLACEHOLDER_PASSWORD` was not configured, got a random password instead of the placeholder. The data migration leaves them alone (intended): they count as having a password (`deletion_by_email: false`) and use "Forgot password" before they can delete their account by password.

## Room module

`app/Room/` holds the discussion rooms (group chats about a film), in the same layers as Auth. The `DiscussionRoom` and `Message` models stay in `app/Models/`.

```
app/Room/
  Controllers/          RoomController (rooms, joining, invitations, messages, read receipts), RoomSocketController (socket channel auth)
  FormRequests/         StoreRoomRequest, InviteToRoomRequest, SendRoomMessageRequest, ReadRoomMessagesRequest, RoomSocketRequest, ReportRoomRequest, ReportRoomMessageRequest
  InterfaceServices/    RoomServiceInterface
  Services/             RoomService (visibility, membership, invitations, delivery, read receipts, deletion)
  InterfaceRepository/  RoomRepositoryInterface
  Repository/           RoomRepository
  DTOs/Requests/        CreateRoomData, InviteToRoomData, SendRoomMessageData, ReadRoomMessagesData
  DTOs/Responses/       RoomResponse, RoomListResponse, RoomMessageResponse, RoomMessagesResponse, ReadReceiptsResponse
  Events/               RoomMessageSent (`message.sent`), RoomMessagesRead (`messages.read`), RoomsChanged (`rooms.changed`)
  Policies/             DiscussionRoomPolicy
  Providers/            RoomModuleServiceProvider (bindings and the room-messages / room-reads / room-socket / room-invites limiters)
```

| Endpoint | Purpose |
| --- | --- |
| `GET /api/rooms` | The rooms you can see, yours first, then invitations, then other public rooms, with members and your unread count. A public room about a hidden film ([Genre and Movie modules](#genre-and-movie-modules)) is left out unless you are a member or invited (`RoomRepository::visibleTo()`); the room itself is never altered |
| `POST /api/rooms` | Create a public or private (`is_private`) room; the creator becomes its first member |
| `POST /api/rooms/{room}/join` | Join a public room, or accept the invitation to a private one |
| `POST /api/rooms/{room}/invites` | A member of a private room invites people by username |
| `DELETE /api/rooms/{room}` | Delete the room (its creator or an admin), with its messages, members and invitations |
| `DELETE /api/rooms/{room}/invite` | Decline your invitation to a private room |
| `GET /api/rooms/{room}/messages` | The messages you can still see, each with `read_by` (author first) and `unread_by` |
| `POST /api/rooms/{room}/messages` | Send a message (members only) |
| `POST /api/rooms/{room}/read` | The member has seen these messages; returns the ones now deleted |
| `POST /api/rooms/{room}/report` | Report a room you can see and did not create (`reason`: `spam`, `harassment`, `inappropriate`, `other`; optional `details`, max 500; `throttle:report`) |
| `POST /api/rooms/{room}/messages/{message}/report` | Report another member's message in a room you can see (`reason`: `spam`, `harassment`, `hate`, `spoilers`, `other`; optional `details`). The route uses scoped bindings, so a message from another room is a 404 |
| `POST /api/rooms/socket-auth` | Authorize `private-room.{id}`, `private-rooms.public` or `private-rooms.user.{id}` on Reverb |

- A room is public (anyone signed in can read it and join) or private (`discussion_rooms.is_private`): only its members and the people they invited can see and read it. A room's members (`room_members`) are its creator and everyone who joined; only members post, and joining is explicit (`POST /join`), posting never joins you.
- Any member of a private room can invite people (`room_invites`: room, user, invited_by). An invitee sees the room in their list and can read it, then joins (the invitation becomes membership) or declines. Invitees are not members, so messages never wait for them; neither do people reading a public room they have not joined (their `POST /read` counts for nothing).
- Rooms lists stay current over two more private channels: a public room's new messages (`message.sent`) and changes (`rooms.changed`: created, joined) go to `rooms.public`; a private room's go to `rooms.user.{id}` of each member and invitee (invited, joined, declined; the decliner too).
- Every member at the time a message is sent, except the sender, gets an unread `notifications` row of type `room_message` (`data`: room_id, message_id); `read_at` is set when they read it. A message sent with nobody else in the room has no rows and waits for nobody, not even people who join later.
- A message is stored and shown to everyone who can see the room for 24 hours after it is sent (`RoomService::MESSAGE_LIFETIME_HOURS`). After that the sender, the members who have read it and anyone else no longer get it (`GET /messages`, and the room's `last_message` and `message_count`); the members who have yet to read it still do.
- A message is deleted, with its rows, once its 24 hours are up and nobody still has to read it: by `POST /read` when the last of those members reads it late (say after 30 hours), or by `rooms:prune-messages` when everyone read it in time. That command is scheduled every minute in `routes/console.php`, so run `php artisan schedule:work` next to `php artisan serve` locally (a cron entry calling `schedule:run` on a server). Opening a room also cleans up its own expired messages.
- Every message is pushed as `message.sent` on `room.{id}` to whoever has the room open; their browser confirms with `POST /read`, which pushes `messages.read` (the reader, the ids, and `deleted_ids` for the messages it deleted). A failed push is only logged: the message stays stored, so members get it when they open the room.
- The browser keeps no copy: the Rooms page shows what `GET /messages` returns plus what the socket pushes, and takes a message off the screen once its 24 hours are up unless it still waits for that member. One the member reads after that stays on screen until they leave the room. Copies saved by the earlier localStorage version are deleted when the app loads.
- Only the room's creator (`created_by`) or an admin can delete it (`DiscussionRoomPolicy::delete`; the Rooms page shows "Delete room" in the room menu to them, with a confirm dialog). The foreign keys delete its messages, members and invitations; `RoomRepository::delete()` removes its read receipts, which the database can't cascade. Every list that showed the room gets `rooms.changed`, and its members and invitees get a `room_deleted` notification.
- `room_message` rows are read receipts, not notifications for the bell: `/api/me` and the [Notification module](#notification-module) leave them out. Invitations, messages, @mentions and reads also go to that module (see there).
- Reporting a room or a message is `DiscussionRoomPolicy::report` and `MessagePolicy::report` (403 for your own, or for a room you cannot see); `RoomService` files it through `ReportServiceInterface`, 201 when new and 200 with the earlier report when repeated. A message is reportable by anyone who can see its room, even after its 24 hours; its report outlives the deleted message (shown as removed in the staff queue). Tests are in `tests/Feature/Report/ReportContentTest.php`.
- Tests: `tests/Feature/Room/RoomMessagingTest.php`, `tests/Feature/Room/RoomMembershipTest.php`, `tests/Feature/Room/HiddenMoviesRoomTest.php` (the permission matrix is in `tests/Feature/Auth/PolicyTest.php`).

## Notification module

`app/Notification/` is the header bell, the Notifications page and the pop-ups, pushed live over Reverb. It needed no database change: notifications are rows of the existing `notifications` table (`type`, `data` JSON, `read_at`), which it shares with the Room module's read receipts (`room_message`, never shown).

```
app/Notification/
  Controllers/          NotificationController (feed, read/unread, read all, dismiss), NotificationSocketController (socket channel auth)
  FormRequests/         UpdateNotificationRequest, NotificationSocketRequest
  DTOs/Requests/        UpdateNotificationData
  DTOs/Responses/       NotificationResponse (one notification and its sentence), NotificationFeedResponse
  InterfaceServices/    NotificationServiceInterface (what other modules call), NotificationFeedServiceInterface (the page)
  Services/             NotificationService, NotificationFeedService, NotificationPresenter (actors and follow state in one go), LiveNotifications (pushes)
  InterfaceRepository/  NotificationRepositoryInterface
  Repository/           NotificationRepository (the only code touching the table for notifications)
  Events/               NotificationPushed (`notification.pushed`), NotificationsChanged (`notifications.changed`)
  Policies/             NotificationPolicy (moved from app/Policies/)
  Providers/            NotificationModuleServiceProvider (bindings and the notification-socket limiter)
```

| Endpoint | Purpose |
| --- | --- |
| `GET /api/notifications` | Your newest 100 history notifications plus all pending follow requests |
| `PATCH /api/notifications/{notification}` | Mark one read or unread (`read`) |
| `POST /api/notifications/read-all` | Mark them all read (never touches room read receipts) |
| `DELETE /api/notifications/{notification}` | Dismiss one; dismissing a follow request declines it |
| `POST /api/notifications/socket-auth` | Authorize `private-notifications.{your id}` on Reverb |
| `POST /api/me/follow-requests/{username}/accept`, `DELETE /api/me/follow-requests/{username}` | Accept or decline a follow request (User module) |

| Type | Who gets it, when | Sent from |
| --- | --- | --- |
| `follow` | Someone followed your public profile, or you accepted their request | `ProfileService` |
| `follow_request` | Someone asked to follow your followers-only or private profile | `ProfileService` |
| `follow_accepted` | The person you asked accepted | `ProfileService` |
| `room_invite` / `room_joined` | You were invited to a private room / someone you invited joined | `RoomService` |
| `room_deleted` | A room you were a member of or invited to was deleted (the notifications that opened it are removed) | `RoomService` |
| `new_messages` | New messages in a room you're a member of: one per room, counting up until you read the room | `RoomService` |
| `mention` | A message names your @username in a room you can see | `RoomService` |
| `night_invite` | The host invited you | `MovieNightService` |
| `night_rsvp` / `night_vote` | (host) A guest answered / voted; a later answer or vote replaces an unread one | `MovieNightService` |
| `night_update` | (guests who haven't declined) The host locked in the pick, cancelled, reopened voting or changed the time | `MovieNightService` |
| `review_helpful` | Someone marked your review helpful (taking it back removes it) | `MovieReviewService` |
| `review_removed` | Staff rejected or deleted your review. `data` holds `tmdb_id`, `media_type` and `action` (`removed`): never the review's text and never who acted. There is no actor, so it shows as from "WatchWise" (`actor.id` 0) with the sentence "removed your review" and the film below it, linking to the film page. Always delivered: it has no switch | `AdminService` (through `NotificationServiceInterface::reviewRemoved()`) |

- Other modules call `NotificationServiceInterface` after their change is saved. Nobody is notified about their own action, and the Notification module never calls back into them.
- `data` stores ids and the words of the moment (`actor_id`, room or night title, a quote). `NotificationResponse::fromModel()` turns a row into the sentence the page shows (**actor** `text` **target**, `detail`, `film`, `to`, `action`). A row whose actor deleted their account, or that lacks the fields its sentence needs (older rows), is left out; `review_removed` rows have no actor and are never left out for that reason. Film titles are looked up by the browser from `film` (`tmdb_id`, `media_type`).
- **Follow requests:** following a followers-only or private profile (`ProfilePrivacy::needsFollowApproval()`) stores a `follow_request` row instead of a follow. That row is the pending request. A nullable requester foreign key and a unique recipient/requester pair prevent concurrent duplicates; the migration consolidates existing duplicates. Pending requests stay in the feed beyond its 100-item history limit. Accepting creates the follow and turns the row into `follow` (so the owner can follow back). Declining, dismissing it, or the requester unfollowing deletes it. `POST /users/{username}/follow` answers `{following, requested, followers}`, and the profile and search responses carry `is_requested`.
- **Settings:** `settings.notifications` in `user_preferences` has one switch per type (`Notification::OPTIONAL_TYPES`, all on by default). A switched-off type is never stored or pushed. Follow requests and `review_removed` have no switch (they are in `FEED_TYPES` but not in `OPTIONAL_TYPES`): a follow request waits for an answer, and a notice that staff removed your review is about your own content.
- **Live:** each new or updated notification is pushed to `notifications.{user id}` as `notification.pushed` (`{notification, silent}`). `silent` is set for the room's "new messages" update when the same message also mentioned you, and for an accepted request. Reads, dismissals and withdrawn requests send `notifications.changed`, and the other tabs reload. A failed push is only logged: the bell catches up on its next load, and polls every minute until its private channel subscribes successfully, and resumes polling on connection or subscription errors.
- Data lookups compare the JSON in PHP (`NotificationRepository::matching()`), so they behave the same on MySQL, PostgreSQL and SQLite.
- Tests: `tests/Feature/Notification/` (feed, follow requests, every trigger, settings and the socket channel). `tests/TestCase.php` fakes the two events, so no test reaches a socket server; a test that fakes its own events has to list `NOTIFICATION_EVENTS` again.

## Report module

`app/Report/` holds content reports: members report reviews, room messages, movie lists, discussion rooms and member profiles, and staff triage them in the queue the [Admin module](#admin-module) serves. The `Report` model stays in `app/Models/`. The Report module owns the data and the rules for closing a report; the module that owns the content decides who may report it, files the report through `ReportServiceInterface` and exposes the route (Review, Room, MovieList and User), and Admin owns the staff endpoints.

```
app/Report/
  DTOs/Requests/        ReportContentData (reason, details max 500), ReportQueueQuery (status, target type, reason, reporter, page, per_page max 50)
  DTOs/Responses/       ReportContentResponse (the member's confirmation), ReportedTargetResponse (what a report is about, for staff)
  InterfaceServices/    ReportServiceInterface
  Services/             ReportService
  InterfaceRepository/  ReportRepositoryInterface
  Repository/           ReportRepository
  Support/              ReportTargetType (review, message, list, room, profile; the reasons of each), ReportStatus (open, resolved, dismissed)
  Exceptions/           ReportAlreadyClosed (409)
  Providers/            ReportModuleServiceProvider (bindings and the `report` limiter)
```

- **Table.** `reports` (migration `2026_11_12_090000`, `Report` model and `ReportFactory`): `reporter_id` (foreign key to `users`, cascade), `target_type` and `target_id` (polymorphic, no foreign key), `reason`, `details` (max 500), `target_label` (max 120) and `target_excerpt` (max 200) (a snapshot of the content taken when the report is filed), `status` (`open`, `resolved`, `dismissed`), `resolved_at`, `resolved_by` (foreign key to `users`, `nullOnDelete`), `resolution_note` (max 500) and timestamps. One report per (reporter, target type, target id) is a unique key; `(target_type, target_id, status)` and `(status, created_at)` are indexed for closing by target and for the queue. The migration moved every row of the old `review_reports` table into it (same id, reporter, reason and timestamps; `open` when `resolved_at` was null, else `resolved`) and dropped that table; `down()` restores the review reports and drops the rest. On PostgreSQL both directions advance the id sequence past the copied ids. `ReportsMigrationTest` covers both directions.
- **Reasons per kind** (`ReportTargetType::reasons()`): review `Spam`, `Links`, `Spoilers`, `Offensive language` (unchanged); message `spam`, `harassment`, `hate`, `spoilers`, `other`; list, room and profile `spam`, `harassment`, `inappropriate`, `other`. The staff `reason` filter accepts any of them.
- **Member endpoints** (each in the module of the content, see their sections): `POST /api/reviews/{review}/report`, `POST /api/rooms/{room}/messages/{message}/report`, `POST /api/rooms/{room}/report`, `POST /api/lists/{list}/report`, `POST /api/users/{username}/report`. The reporter has to see the target (a message, list or room) and may not report their own content or profile (the `report` ability of each policy: 403). A profile is the exception: any signed-in member can report any other active member's profile, private and followers-only ones included, because their name and handle are visible to everyone anyway; a deactivated member's profile and your own are refused (403), a repeat report of the same target answers 200 with the existing report and adds no row. While that report is open the first reason, details and snapshot stay (the exception is a review, where a repeat replaces the reason, as it always did); once staff closed it, reporting again reopens it as new under a row lock: open, `resolved_at`, `resolved_by` and `resolution_note` cleared, reason, details and snapshot replaced, `created_at` moved to now so it sorts as the newest. Two requests racing to create the first report are settled by the unique key (`createOrFirst`). `details` is optional (max 500). The body is `{message, report: {id, target_type, target_id, reason, details, status, created_at}}`; the review endpoint keeps its old `{message, reason}`. The message, list, room and profile endpoints share the `report` limiter, 20 an hour per user, defined in `ReportModuleServiceProvider`; the review endpoint keeps its own `throttle:10,1`.
- **`ReportServiceInterface`:** `report()` files a report (authorization is the caller's job); `resolveForTarget($type, $targetId, $by, $status)` closes every open report on a target as `resolved` or `dismissed` (for the staff actions that act on the content itself; `$by` may be null for a system closure; the review approve and reject paths use it); `close()` closes one report (row-locked, `ReportAlreadyClosed` when it is not open), optionally with every other open report on the same target; `queue()`, `summary()` and `targets()` serve the staff queue. Closing sets `status`, `resolved_at`, `resolved_by` and `resolution_note`.
- **Content that is gone.** The target has no foreign key, so deleting a message, list, room, review or account leaves its reports behind. They are not closed automatically: they stay in the queue and show `target.removed: true` (with no excerpt and no owner for a deleted row; a review whose text was removed by a moderator or cleared by its author also counts as removed), and staff close them with a normal decision. The one exception is a review deleted through the admin API, which deletes its reports with it, as the old foreign key did. The label and excerpt saved with each report (message text, list title, room name, profile name and handle, review text; cut to 120 and 200 characters) are what staff read when a message is long gone, since messages are deleted within a day or so.
- Tests: `tests/Feature/Report/ReportContentTest.php` (every member endpoint: success, 401, 403, own content, hidden content, 404, duplicates, reasons, details, throttle), `ReportsMigrationTest.php`, `tests/Feature/Review/ReviewReportTest.php` and `tests/Feature/Admin/AdminReportQueueTest.php`, `AdminReportDecisionTest.php`.

## Admin module

`app/Admin/` is the administrator and moderator dashboard API (brief objective 11). It uses the same layers as Auth. One `AdminController` serves the dashboard, users, reviews, movies and the audit log, `AdminReportController` the report queue, and the routes are in `routes/admin.php` under `/api/admin` with `auth.jwt` and `role:admin,moderator`. The `reviews`, `movies` and `users` data stay in their own modules and tables: Admin calls `ReviewServiceInterface`, `ReportServiceInterface`, `MovieCatalogServiceInterface`, `NotificationServiceInterface` (to tell a review's author it was removed) and `AccountServiceInterface` (to delete a user), and only its two repositories (`AdminStatsRepository`, `AdminUserRepository`) read the `users` and statistics tables.

```
app/Admin/
  Controllers/          AdminController, AdminReportController
  FormRequests/         AdminStatsRequest, AdminUserIndexRequest, UpdateUserRequest, AdminReviewIndexRequest, ModerateReviewRequest, AdminMovieIndexRequest, UpdateLocalMovieRequest, AdminAuditIndexRequest, AdminReportIndexRequest, UpdateReportRequest
  DTOs/Requests/        AdminStatsQuery, AdminUserQuery, UpdateUserData, ReviewModerationQuery, ModerateReviewData, AdminAuditQuery, UpdateReportData
  DTOs/Responses/       AdminStatsResponse, RankedMovieResponse, GenreShareResponse, ActivityPointResponse, AdminUserResponse, AdminReviewResponse, AdminMovieResponse, AdminAuditLogResponse, AdminReportResponse, AdminReportDecisionResponse, AdminPageResponse
  InterfaceServices/    AdminServiceInterface, AdminAuditServiceInterface, AdminReportServiceInterface
  Services/             AdminService, AdminAuditService, AdminReportService
  InterfaceRepository/  AdminStatsRepositoryInterface, AdminUserRepositoryInterface, AdminAuditRepositoryInterface
  Repository/           AdminStatsRepository, AdminUserRepository, AdminAuditRepository
  Support/              AdminAction, AdminSubjectType (backed enums of the audit log)
  Mail/                 AccountSuspendedMail, AccountReinstatedMail
  Exceptions/           CannotModerateSelf, CannotModerateStaff (both render a 403)
  Policies/             AdminPolicy
  Providers/            AdminModuleServiceProvider
```

**Who can do what** (a guest gets 401 everywhere, a normal member 403):

| Route | admin | moderator |
| --- | --- | --- |
| `GET /api/admin/stats` | yes | yes (read-only) |
| `GET /api/admin/users` | yes | yes (read-only) |
| `PATCH /api/admin/users/{user}` | `role` and `is_active` | `is_active` only, on a plain `user` account, never themselves |
| `DELETE /api/admin/users/{user}` | yes | no |
| `GET`, `PATCH`, `DELETE /api/admin/reviews*` | yes | yes |
| `GET /api/admin/movies`, `PATCH /api/admin/movies/{tmdbId}` | yes | no |
| `GET /api/admin/reports`, `GET`, `PATCH /api/admin/reports/{report}` | yes | yes |
| `GET /api/admin/audit-log` | yes (read-only) | no |

An admin cannot demote, deactivate or delete themselves (`CannotModerateSelf`). A moderator who targets themselves, an admin or another moderator, or sends a `role` that differs from the target's current one, gets `CannotModerateStaff` (sending the role the member already has changes nothing and is accepted). Deactivating a user revokes their sessions (`AuthServiceInterface::revokeSessions()`), so login, refresh and existing access tokens stop working. Deleting a user revokes their sessions and then calls `AccountServiceInterface::deleteAccount()`, the same removal the member's own account deletion uses, so the stored avatar file goes too (an external Google or GitHub avatar URL is just skipped). Every admin route sits behind `throttle:admin`, a per-user limiter of 120 requests a minute (keyed by user id) defined in `AdminModuleServiceProvider`; over the limit the API answers 429 and every admin operation documents it. The gates `admin.dashboard`, `admin.users`, `admin.reviews`, `admin.movies`, `admin.users.manage` (admin-only: delete users) and `admin.audit` (admin-only: the audit log) are defined in `AuthorizationServiceProvider` and backed by `AdminPolicy`.

**What the endpoints return:**

- `stats`: `users_total`, `new_users`, `reviews_total`, `reviews_pending`, `new_reviews`, `active_today`, `active_yesterday`, `flagged_reviews`, `most_watched`, `top_rated`, `popular_genres` and the daily `activity` series. `?range=7|30|90|year` (or `?days=1..90`) picks the window; `range` in the response carries the counts for that window and the equally long one before it, for the change figures.
- `stats` caching: `AdminService::stats()` keeps the built response in the cache for 60 seconds (`STATS_CACHE_TTL`), one entry per day, `days` and `range` (`STATS_CACHE_KEY`). It stores the JSON form as a plain array and rebuilds the response with `AdminStatsResponse::fromPayload()`, because `cache.serializable_classes` is `false` and objects would not survive the cache. Editing a film (`PATCH /api/admin/movies/{tmdbId}`), approving, rejecting or deleting a review, and deleting a user replace the version token in `STATS_VERSION_KEY`, which retires every cached window at once without cache tags, so those changes show on the next request. Everything else (new sign-ups, watches, ratings) shows within the 60 seconds.
- `popular_genres` is worked out from `AdminStatsRepository::engagementWeights()`, which ranks in SQL: watched movie entries and approved movie reviews are counted per `tmdb_id` in two grouped queries, joined with `UNION ALL` in a subquery, summed, ordered by weight (then `tmdb_id`, so ties are stable) and limited to the 1000 heaviest films. Only plain `count`, `sum` and `UNION ALL`, so it behaves the same on MySQL, PostgreSQL and SQLite.
- `users`: filters `q`, `role` (`user`, `moderator`, `admin`), `status` (`active`, `unverified`, `inactive`), `page`, `per_page`. Each row has `status`, `reviews_count` (reviews with text only) and `last_active_at`. A `summary` gives site-wide totals whatever the filters.
- `reviews`: `?status=pending|approved|flagged|removed` (default `pending`; the four never overlap). A row's `status` is `removed` if `removed_at` is set, else `flagged` if it has an open report, else `approved` if `is_approved`, else `pending`. Rows carry `reports_count`, `reasons`, the author and the film (`movie.title` is null for TV and films not in the catalogue). A `summary` gives the flagged and removed totals and the sentiment split. Approving or rejecting closes the review's open reports; rejecting clears the text and sentiment, keeps the stars and sets `removed_at`.
- `reports`: the staff queue of member reports (gate `admin.reports`, checked in `AdminReportIndexRequest` and `UpdateReportRequest::authorize()` and, for the single read, in the controller). `GET /api/admin/reports` filters `status` (`open` by default, `resolved`, `dismissed` or `all`), `target_type` (`review`, `message`, `list`, `room`, `profile`), `reason` (any reason of any kind), `reporter_id`, `page` and `per_page` (at most 50). Newest first, ties broken by id. Each row has `id`, `status`, `reason`, `details`, `reporter` {id, name, username}, `target` {`type`, `id`, `label`, `excerpt`, `owner` {id, name, username} or null, `removed`}, `resolved_by`, `resolution_note`, `created_at` and `resolved_at`. The excerpt is the message or review text cut to 200 characters, a list title, a room name or a username; the label reads like `Message by maya in Fight Club night`. The page's `summary` has `by_status` (every report) and `by_target_type` (open reports only, per kind). The targets are loaded by `ReportServiceInterface::targets()` in a constant number of queries (one per kind of content, owner eager-loaded), however many reports there are. `GET /api/admin/reports/{report}` returns the same row under `report`. `PATCH /api/admin/reports/{report}` takes `status` (`resolved` or `dismissed`), an optional `note` (max 500) and `apply_to_target` (default true: every other open report on the same content is closed with the same decision, note and staff member) and returns `report` and `reports_closed`. Closing an already closed report is `ReportAlreadyClosed` (409, nothing recorded). Inside the transaction `ReportService::close()` first locks the target's open reports in id order and then the report, so two staff members cannot both close it and two closing different reports of the same target with `apply_to_target` cannot deadlock. This is triage only: the reported content is never touched and the reporters are not told. A report whose content is gone (deleted, or a review whose text was removed) stays in the queue with `target.removed` true. A deleted row is shown from the label and excerpt saved with the report when it was filed (no owner); a removed review has no excerpt. Staff close it like any other.
- `movies`: local catalogue rows (`title`, `overview`, `director`, `runtime`, `release_date`, `genre_ids`, `is_hidden`, `is_featured`, `is_edited`, `synced_at`, `edited_at`) and a `summary.sync` block (`cached`, `overrides`, `last_synced_at`). `PATCH` takes any of those editable fields; `null` clears `director`, `runtime`, `release_date` and `overview`, and an empty `genre_ids` clears the genres. Editing content sets `edited_at` only when a submitted content field (`title`, `overview`, `director`, `runtime`, `release_date`, `genre_ids`) differs from what is stored (`UpdateLocalMovieData::changesFrom()`; a stored `null` genre list equals an empty one), so resending the same values changes nothing; hiding or featuring never sets it. `is_hidden` hides the film from members' browsing and `is_featured` puts it in the Home page's Featured row (see [Genre and Movie modules](#genre-and-movie-modules)); the admin endpoints themselves always list every row.
- `audit-log`: admins only (`admin.audit` gate in `AdminPolicy`, checked in `AdminAuditIndexRequest::authorize()`). Filters `action` (an `AdminAction` value), `actor_id`, `subject_type` (`user`, `review`, `movie`, `report`), `subject_id` (needs `subject_type`, since user 5 and review 5 are different records), `page`, `per_page` (at most 50). Newest first, ties broken by id; actors are eager-loaded, so the query count does not grow with the page. Entries use the same `data` + `meta` page as the other lists and carry `id`, `action`, `actor {id, name, username, role}`, `subject {type, id, label}`, `changes` and `created_at`.

**What members are told**

Staff actions are not silent for the member they affect. Both messages are sent only once the change and its audit entry have committed (never inside the transaction, so a refused attempt or a rolled-back one tells nobody), and neither names the staff member or gives a reason.

| Staff action | Member is told |
| --- | --- |
| `PATCH /api/admin/users/{user}` really changes `is_active` to `false` | Email `AccountSuspendedMail`: the WatchWise team suspended the account and they can't sign in; they'll be emailed if it is lifted. No button or link, and no support address, since the project has none configured |
| `PATCH /api/admin/users/{user}` really changes `is_active` to `true` | Email `AccountReinstatedMail`: the suspension was lifted, with a link to the sign-in page |
| A role change, an update that changes nothing (sending the state they already have), a refused attempt, a validation error | Nothing |
| `PATCH /api/admin/reviews/{review}` with `rejected` on a review that still has text, or `DELETE /api/admin/reviews/{review}` on one | In-app notification `review_removed` (see [Notification module](#notification-module)) |
| Approving a review, rejecting or deleting one that has no text (a star rating, or a review already rejected, whose author was told then) | Nothing |

- The email goes to the member's stored address (the one after the update), verified or not: a suspended member can't sign in, so the email is the only way they hear of it. It is sent with `SendsMailAfterResponse::mailLater()` after the response, so a mail failure is retried and reported as `MailNotDelivered` and never fails or undoes the admin action. A bulk suspension from the dashboard is one `PATCH` per member, so each member gets one email.
- The notification is created through `NotificationServiceInterface::reviewRemoved()` after the moderation committed, wrapped in `rescue()`: a failure to store it is reported and never fails or rolls back the moderation. It has no preference switch, so it always arrives.

**Audit log**

Staff actions are recorded in `admin_audit_logs` (`AdminAuditLog` model and factory, migration `2026_11_11_225656`) by `AdminAuditServiceInterface::record()`, which `AdminService` calls only after a role, active-state, review or film change went through, inside the same `DB::transaction` as that change, so a refused or failed attempt (self-moderation, a moderator touching staff, validation failure, unknown user, review or film) leaves nothing, and if the audit insert itself fails the change is rolled back. The old values are read inside that transaction from a row-locked copy (`lockForUpdate()`, through the user, review and catalogue repositories; the lock only has an effect on databases that support it, such as MySQL and PostgreSQL), so two identical concurrent requests cannot both log the same `from -> to`. Deleting a user writes its entry first and then removes the account, so a failing insert leaves the account and its avatar file alone; the avatar file removal itself cannot be rolled back, and a concurrent double delete or double review delete is not de-duplicated.

| Action (`AdminAction`) | Subject (`subject_id`, `subject_label`) | `changes` |
| --- | --- | --- |
| `user.role_changed` | user (id, username) | `role: {from, to}` |
| `user.suspended`, `user.reinstated` | user (id, username) | `is_active: {from, to}` |
| `user.deleted` | user (id, username) | none |
| `review.approved`, `review.rejected` | review (id, "Review by username") | `status: {from, to}` (`pending`, `approved` or `removed`; open reports only make a review show as `flagged`, so they are not a state here) and `reports_resolved`, the number of open reports the decision closed (0 when there were none) |
| `review.deleted` | review (id, "Review by username") | none |
| `report.resolved`, `report.dismissed` | report (id, "Report on <type> #<target id>", e.g. "Report on message #12") | `status: {from: open, to: resolved or dismissed}` `reports_closed`, how many reports the decision closed (1, or more with `apply_to_target`), and `closed_report_ids`, their ids (at most 100) |
| `movie.updated` | movie (`tmdb_id`, title after the edit) | each submitted field that really changed as `{from, to}`; `overview` is only `{changed: true}`, never the text |

- One update that changes both the role and the active state writes two entries. Sending a value the target already has (a moderator sending the member's current role, `is_active: true` for an active member, a movie field set to what it is) changes nothing and records nothing. A stored `null` genre list and an empty `genre_ids` count as the same. An approve or reject records nothing when the status stays the same and no open report was closed (approving an approved review with no open reports, rejecting a removed one); approving a flagged review whose status stays `approved` is recorded, with `reports_resolved` above 0. Approving or rejecting a review also closes its open reports (approving dismisses them, rejecting resolves them, both by the moderator); that is part of the review entry, not an entry of its own.
- Each row keeps `actor_role`, the role the staff member had at the time, and `subject_label`, a username or title, so it stays readable after the subject is deleted. Emails are never stored. `subject_id` has no foreign key.
- `actor_id` is a foreign key with `nullOnDelete`: deleting the staff account keeps its rows and the API then returns `null` for the actor's id, name and username (the recorded role stays).
- The log is append-only. The table has `created_at` only; `AdminAuditLog` has `$guarded = ['*']` (the repository inserts with `forceCreate`), saving a changed row or deleting one through the model throws a `LogicException`, and neither the repository interface nor the service has an update or delete method. Rows can still be removed by hand in the database.
- There is no retention: the log is not pruned, so the table grows with every staff action (a few rows per action, indexed by `created_at`, `action`, `actor_id` and subject). A prune command would be a delete path, so it was left out until a retention period is agreed.
- Not recorded: reads (the dashboard, lists and the log itself), anything members do to their own account, and review or user changes made outside `/api/admin/*` (a member editing or deleting their own review, deleting their own account).

**Supporting changes**

- `users.last_active_at` is stamped by the `auth.jwt` middleware at most once every 5 minutes per user. It is in `User::$hidden`, so public payloads and `/api/auth/me` do not expose it.
- `users.role` accepts `moderator` besides `user` and `admin` (migration `2026_10_29_000001`). `role` is guarded against mass assignment.
- Review reports live in the generic `reports` table (see the [Report module](#report-module)); migration `2026_10_29_000002` added `reviews.removed_at`, and its old `review_reports` table was moved into `reports` by `2026_11_12_090000`. A member reports a review with `POST /api/reviews/{review}/report` (`throttle:10,1`, in the Review module), with a reason of `Spam`, `Links`, `Spoilers` or `Offensive language`. It is refused (403) for your own review, an unapproved, text-less or removed review, and a review whose author's privacy hides it from you. Re-reporting changes the reason and returns 200; a new report returns 201. When the author rewrites the review, its reports and `removed_at` are cleared.
- `movies.director` and `movies.edited_at` (migration `2026_10_29_000003`). The director is filled from TMDB when a film is first cached.
- Tests: `tests/Feature/Admin/` (every route for guest, member, moderator and admin; stats windows and caching; film ranking; user insights; moderation; moderator permissions; the rate limit in `AdminRateLimitTest`; `AdminMemberNoticesTest` for the suspension and reinstatement emails and the removed-review notification (when each is sent, never sent, and that a failing audit insert or mail transport changes nothing else), `AdminAuditRecordingTest` for what is and is not recorded and the row's immutability, `AdminAuditLogTest` for the endpoint: access, shape, ordering, filters, paging and a constant query count, `AdminReportQueueTest` for the report queue (access, row shape, removed targets, filters, paging and order, summary, constant query count) and `AdminReportDecisionTest` for resolving and dismissing (`apply_to_target`, the 409, the audit rows, the rollback when the audit insert fails, review approval and rejection closing their reports)) and `tests/Feature/Review/ReviewReportTest.php`. The API paths are in `openapi/paths/admin.json` and `openapi/paths/social.json`.

## Removed code

`app/Http/` and `app/Services/` no longer exist. The base `Controller` class is gone too (controllers are plain classes), and so is everything that only lived in those two folders:

- the notifications API (`/api/notifications`) and the `Notifier` that created notifications when someone followed, invited, voted or mentioned another user, since rebuilt as the [Notification module](#notification-module);
- the old discussion rooms API (`/api/rooms`), since rebuilt as the [Room module](#room-module);
- the activity feed (`ActivityFeedService`), so `/api/users/{username}` no longer returns `activity` or the `reviews` list (the `counts.reviews` number is still there). `/api/home`'s `activity` has since been rebuilt from the reviews, lists and watchlist tables (see [User module](#user-module)); the old `activity_feed` table is unused.

The database tables, models and policies for notifications, rooms, messages, reviews and the activity feed are still in place (`app/Models/`, `app/Policies/`, the migrations), so no data was dropped.

## Gemini module

`app/Gemini/` contains a standalone Google Gemini client. Movie recommendations use Groq.

```
app/Gemini/
  InterfaceServices/  GeminiServiceInterface   ask(string $prompt): array
  Services/           GeminiService            sends the prompt, asks for JSON and returns it as an array
  Exceptions/         GeminiUnavailable        thrown on any failure
  Providers/          GeminiModuleServiceProvider (binding only)
```

- Setup: set `GEMINI_API_KEY` and `GEMINI_MODEL` in `.env`. `GEMINI_URL` has a default in `config/services.php`. The key is sent in a header, not in the URL.
- `GeminiUnavailable` only carries the HTTP status (for example "Gemini request failed (500)") or a short reason, never Gemini's response body. Callers log it and show the client a fixed message.
- Requests time out after 60 seconds and are retried up to 3 times.

## Groq module

The chatbot extends this module. `GET /api/chat/connection` returns public socket settings, `POST /api/chat/auth` authorizes only the signed-in user's private chat channel, and `POST /api/chat/messages` sends the current message plus bounded, completed frontend conversation turns to Groq. Replies stream through private Reverb `chat.delta` events, with the complete answer returned by the HTTP request. No transcripts are persisted and no queue worker is needed; the frontend resets history on page refresh or sign-out.

After installing Composer dependencies, run `php artisan reverb:install --no-interaction` once to generate local Reverb credentials. In the local environment, `php artisan serve` starts Reverb automatically on port `18080` and stops it when the API server exits. Existing local `.env` files should set `REVERB_PORT=18080`; if `REVERB_SERVER_PORT` is set, update that to `18080` too. The browser reads connection settings from the API, so it does not need a copy of the socket key in its own environment file. In production, run Reverb as its own managed process, configure `REVERB_PUBLIC_HOST`, `REVERB_PUBLIC_PORT`, `REVERB_PUBLIC_SCHEME` and the allowed frontend hostname, and proxy secure WebSocket traffic to Reverb. The existing `GROQ_API_KEY` and `GROQ_MODEL` are reused.

`app/Groq/` contains the Groq chat-completions client used by the chatbot, weather mood, recommendations and review moderation/sentiment. One `GroqService` implements three interfaces: `GroqServiceInterface::ask()` (the weather mood schema), `GroqStructuredOutputInterface::askWithSchema()` (strict JSON Schema responses, `reasoning_effort: low`) and `GroqChatServiceInterface::streamChat()` (server-sent events, 60 s and 8000-character reply limits). It sends the API key as a Bearer token.

```
app/Groq/
  Controllers/          ChatController (store), ChatSocketController (show = public socket settings, store = channel auth)
  FormRequests/         ChatMessageRequest (history must be completed user/assistant turns in order), ChatSocketRequest
  DTOs/Requests/        ChatMessageData (message, history and length limits)
  DTOs/Responses/       ChatResponse
  InterfaceServices/    GroqServiceInterface, GroqStructuredOutputInterface, GroqChatServiceInterface, ChatServiceInterface
  Services/             GroqService (HTTP client), ChatService (system prompt, batches deltas every 64 characters or 80 ms)
  Events/               ChatDelta (`chat.delta` on `chat.{userId}.{conversationId}`, broadcast now)
  Exceptions/           GroqUnavailable, ChatUnavailable
  Providers/            GroqModuleServiceProvider (bindings, the `ai` and `chat` limiters, loads routes/channels.php)
```

- Setup: set `GROQ_API_KEY` in `.env`. `GROQ_MODEL` defaults to `openai/gpt-oss-20b` and `GROQ_API_URL` defaults to `https://api.groq.com/openai/v1`.
- **Key profiles:** `askWithSchema(..., keyProfile: 'watched')` uses `GROQ_API_KEY_2` with a longer connect timeout and one retry; everything else uses `'default'` (`GROQ_API_KEY`). This keeps the heavy watched-history calls on a separate Groq quota.
- `GroqUnavailable` contains only a short failure reason; the provider response body is never returned to clients.
- Tests: `tests/Feature/Groq/ChatTest.php`, `GroqKeyTest.php`.

## Weather module

`app/Weather/` reads the current weather from WeatherAPI.com and turns it into a movie mood.

```
app/Weather/
  Controllers/          WeatherController (show, mood)
  FormRequests/         WeatherRequest, WeatherMoodRequest
  InterfaceServices/    WeatherServiceInterface, WeatherMoodServiceInterface
  Services/             WeatherService, WeatherMoodService (builds the Groq prompt)
  InterfaceRepository/  WeatherRepositoryInterface
  Repository/           WeatherRepository (the only code that calls WeatherAPI.com)
  DTOs/Requests/        WeatherQuery, WeatherMoodQuery
  DTOs/Responses/       WeatherResponse, CurrentWeatherResponse (maps WeatherAPI.com to the fields below), WeatherMoodResponse
  Exceptions/           WeatherUnavailable, WeatherMoodUnavailable (fixed 502 responses)
  Providers/            WeatherModuleServiceProvider (bindings and the `weather` limiter)
```

| Endpoint | Purpose |
| --- | --- |
| `GET /api/weather?latitude=&longitude=` | Current weather. Both coordinates are optional, but if one is sent the other is required. Without them, the default location (Beirut) is used and the response says `is_default: true` |
| `POST /api/weather-mood` | Body: `condition`, `temperature`, `humidity`, `wind`, `precipitation`. Returns `{"title": "WEATHER MOOD", "mood", "description"}` generated by Groq's `openai/gpt-oss-20b` model |

- `current` keeps the field names of the earlier Open-Meteo version (`time`, `temperature_2m`, `relative_humidity_2m`, `apparent_temperature`, `precipitation`, `weather_code`, `wind_speed_10m`) so the frontend and recommendations did not change. `CurrentWeatherResponse` translates WeatherAPI.com condition codes to WMO weather codes, and gives `time` in `WEATHERAPI_TIMEZONE`.
- Setup: set `WEATHERAPI_KEY`. The URL, default location and timezone have defaults in `config/services.php` (`weatherapi`). A missing key or failed request throws `WeatherUnavailable`; failures are logged to `stderr` (visible in Render's logs) without the URL, coordinates, key or body.
- Tests: `tests/Feature/Weather/` and `tests/Unit/Weather/`.

## MovieRecommendation module

`app/MovieRecommendation/` builds the recommendation shelves and the taste profile. Every AI shelf follows the same rule: real TMDB candidates are gathered first, Groq (strict JSON schema) may only pick candidate ids and add a reason, and unknown ids are discarded.

```
app/MovieRecommendation/
  Controllers/          RecommendationController (index, watched, people, following, taste)
  FormRequests/         RecommendationRequest
  InterfaceServices/    RecommendationServiceInterface, FollowingRecommendationServiceInterface, TasteProfileServiceInterface
  Services/             RecommendationService (weather + watched), PeopleRecommendationService, FollowingRecommendationService, TasteProfileService
  InterfaceRepository/  FollowingRecommendationRepositoryInterface, TasteProfileRepositoryInterface
  Repository/           FollowingRecommendationRepository, TasteProfileRepository
  Support/              WatchedPeople (most-watched director and actor, shared by the taste profile and the people shelf)
  DTOs/Requests/        RecommendationQuery
  DTOs/Responses/       RecommendationResponse, TasteProfileResponse
  Exceptions/           RecommendationUnavailable (fixed 502 responses)
  Providers/            MovieRecommendationModuleServiceProvider
```

| Endpoint | Auth, limiter | Returns |
| --- | --- | --- |
| `GET /api/movie-recommendations?latitude=&longitude=` | public, `ai` | Up to 10 movies that fit the current weather. Candidates come from TMDB Discover in weather-appropriate genres (by WMO weather code); coordinates are optional and validated like `/api/weather` |
| `GET /api/watched-recommendations` | `auth.jwt`, `ai` | `source` (a random title from your watch history) and 6 similar movies/shows from its TMDB "similar" list plus Discover in the same genres (TV and movie genres translated both ways), excluding what you've watched. Uses the `watched` Groq key; cached for an hour per user and watch-history fingerprint |
| `GET /api/people-recommendations` | `auth.jwt`, `ai` | Your most-watched `director` and `actor` and 6 unwatched, released films from their filmographies. Same key and caching as above |
| `GET /api/following-recommendations` | `auth.jwt`, `throttle:60,1` | Up to 6 titles that people you follow rated 5 stars, most raters first, excluding what you've watched. No AI. Re-checks follows, privacy (`ratings` switch, visibility), activity and moderation on every request, so it is never cached |
| `GET /api/taste-profile` | `auth.jwt`, `throttle:60,1` | Watched movie/show counts, 5-star rating count, following count, top 3 genres with percentages, and the most-watched director and actor |

- An empty watch history returns empty shelves rather than an error.
- Hidden films ([Genre and Movie modules](#genre-and-movie-modules)) never appear in a shelf. The weather and watched shelves gather candidates through `MovieService`, which already leaves them out. The watched and people shelves are cached per user for an hour, so `RecommendationResponse::without()` removes hidden films from the cached result on every request (a shelf can then hold fewer than 6). The following shelf excludes them in `FollowingRecommendationRepository::candidates()`, so the 18 candidates it fetches are all visible. The taste profile only counts your own history and is unchanged.
- Errors are fixed messages: `{"error": "Could not get the weather"}` or `{"error": "Could not generate recommendations"}`, both with status 502. The real failure is logged.
- Tests: `tests/Feature/MovieRecommendation/` and `tests/Unit/MovieRecommendation/`.

## Cinema module

`app/Cinema/` finds cinemas near the viewer with the Google Places API (new).

```
app/Cinema/
  Controllers/          NearbyCinemaController (index)
  FormRequests/         NearbyCinemasRequest (latitude and longitude required)
  InterfaceServices/    CinemaServiceInterface
  Services/             CinemaService (calls places:searchNearby, computes the distance)
  DTOs/Requests/        NearbyCinemasQuery
  DTOs/Responses/       CinemaResponse (id, name, address, latitude, longitude, distance_meters, website)
  Exceptions/           CinemaSearchUnavailable (503 when not configured or unreachable, 502 for a bad answer)
  Providers/            CinemaModuleServiceProvider
```

- `GET /api/nearby-cinemas?latitude=&longitude=` is public with `throttle:30,1`. It returns up to 20 `movie_theater` places within 30 km, nearest first.
- Setup: `GOOGLE_PLACES_API_KEY`. The key is sent as the `X-Goog-Api-Key` header with a field mask, so only the fields above are billed.

## Health module

`app/Health/` has a single invokable `HealthController`: `GET /api/health` returns `{"status": "ok"}` with `Cache-Control: no-store`, for uptime checks (Render). Laravel's own `/up` page is also enabled in `bootstrap/app.php`. Tests: `tests/Feature/Health/HealthTest.php`.

## API documentation (Swagger UI)

A Swagger UI page is served by the backend so every API can be tried in the browser.

1. Run `php artisan serve`.
2. Open `http://127.0.0.1:8000/api/documentation`.
3. Log in with `POST /api/auth/login` using "Try it out". The browser keeps the HttpOnly cookies, so protected endpoints such as `GET /api/auth/me` then work without pasting a token.

How it is built (no extra package is needed):

| Part | What it does |
| --- | --- |
| `openapi/base.json` | The title, security schemes (the `access_token` cookie and a Bearer token) and shared schemas |
| `openapi/paths/*.json` | One file per area: `admin`, `auth`, `chat`, `cinemas`, `health`, `notifications`, `recommendations`, `rooms`, `social`, `tmdb`, `weather` |
| `app/Docs/Services/OpenApiSpec.php` | Merges those files into one OpenAPI 3 document and fails on a duplicate path |
| `app/Docs/Controllers/DocumentationController.php` | Serves the page (`/api/documentation`) and the spec (`/api/documentation/openapi.json`) |
| `resources/views/docs/swagger.blade.php` | The page. Swagger UI 5.17.14 is loaded from a CDN |
| `tests/Feature/Docs/OpenApiCoverageTest.php` | Fails if an `/api` route is missing from the spec, or the spec lists a route that does not exist |

- The page and the spec only work when `APP_ENV` is `local` (or `testing`). In any other environment they return 404.
- When you add an endpoint, add it to the matching `openapi/paths/*.json` file. The coverage test fails until you do.
- Some endpoints need keys in `.env` to work for real: `TMDB_API_KEY` for the movie and person endpoints, `GROQ_API_KEY` (and `GROQ_API_KEY_2`) for chat, weather mood and recommendations, `WEATHERAPI_KEY` for weather, `GOOGLE_PLACES_API_KEY` for nearby cinemas, and `BREVO_API_KEY` or `MAIL_MAILER=log` for the account emails (with `log`, the verification link is in `storage/logs/laravel.log`).

## Deployment (Docker / Render)

The backend is deployed to Render from `backend/Dockerfile` (`php:8.4-apache-bookworm`, extensions `bcmath intl mbstring opcache pcntl pdo_mysql zip`, port `10000`, `APP_ENV=production`, logs to `stderr`).

| File | Purpose |
| --- | --- |
| `Dockerfile` | Installs Composer dependencies without dev packages, copies the app and the files below, prepares `storage/` and `bootstrap/cache` |
| `docker/start.sh` | Fails without `APP_KEY`, points Reverb at `127.0.0.1:18080`, sets `REVERB_PUBLIC_*` from `RENDER_EXTERNAL_HOSTNAME` (port 443, https), caches config/routes/views, links storage, then starts supervisord |
| `docker/supervisord.conf` | Runs two processes: `reverb:start` (private, port 18080) and Apache |
| `docker/apache.conf` | Serves `public/` and proxies WebSocket traffic on `/app/` to Reverb, so the API and sockets share one public port |

- Migrations are not run by the container; run `php artisan migrate --force` against the production database yourself, and run them **before** the new code serves traffic whenever a release adds columns the code reads (for example `refresh_tokens.family_started_at` and `grace_used_at`). The `family_started_at` migration backfills existing rows; `grace_used_at` starts null (unused) for them.
- The scheduler (`rooms:prune-messages`, `users:prune-pending`, `auth:prune-refresh-tokens`) is not started by the container either. Opening a room still cleans up its own expired messages, but a cron or worker calling `schedule:run` is needed for full pruning; without it `refresh_tokens` keeps growing (one row per refresh).
- The frontend (Vercel) calls the API through a same-origin proxy (see the frontend config).
- **Deferred work.** Account emails (and the reset token they carry) are sent with `defer()`. Only under PHP-FPM (`fastcgi_finish_request()`) does deferred work run after the response has reached the client. This image runs Apache with mod_php, where it runs after the response is built but before the connection closes, so mail latency can still show up in the timing of forgot-password and resend-verification (and registration), and a long Brevo outage holds an Apache worker for up to about 17 seconds per mail (2 attempts of at most 8 s, 1 s apart). Moving to PHP-FPM, or to queued mail with a queue worker, is recommended.
- **Trusted proxies.** Every request reaches the container through proxies, so without trusted proxies `$request->ip()` is a proxy's address and every IP-keyed limit (`auth-*`, `ai`, `weather`, `tmdb`, the plain `throttle:N,M` routes) and `refresh_tokens.ip` would be one bucket shared by all visitors. `TRUSTED_PROXIES` (via `config/trustedproxy.php`) says which peers are believed, and `bootstrap/app.php` believes only two of their headers: `X-Forwarded-For` (the client address) and `X-Forwarded-Proto` (so `$request->secure()` and incoming signed links see the original `https`). `X-Forwarded-Host` and `X-Forwarded-Port` are ignored on purpose: a caller could otherwise choose the host of URLs built from the request, such as the signed verification, email-change and account-deletion links mailed to a member, and replay the link with the same header. The AWS ELB and RFC 7239 `Forwarded` headers are never read either. On top of that, in production `AppServiceProvider::configureUrlGeneration` pins every generated URL to `APP_URL` over https (`URL::forceRootUrl`, `URL::forceScheme`), so the request's own `Host` header cannot choose a link's host either. Incoming signed links are still checked against the request URL (the `Host` the container receives, plus the scheme from `X-Forwarded-Proto`), so `APP_URL` must be the public backend address the links are opened on (today `https://watchwise-4rit.onrender.com`; the Vercel rewrite forwards to that host too). `TRUSTED_PROXIES` defaults to `*` when `APP_ENV=production`. In Laravel `*` trusts only the address that connected to the container, and Symfony then takes the right-most `X-Forwarded-For` entry that is not a trusted proxy, i.e. the address that peer saw. Two caveats: (1) this is only safe when the edge in front of the app sets `X-Forwarded-For` itself (overwrites it, or appends the address it saw); a proxy that forwards a client-supplied header unchanged lets a client choose its own IP and dodge every per-IP limit. (2) With several hops (browser, Vercel's rewrite, Render's load balancer, the container), `*` yields the address of the hop in front of the last proxy, which may be a Vercel egress address rather than the visitor. In that case list the inner proxies' ranges, or `0.0.0.0/0,::/0` to trust every hop, which makes the left-most entry the client and is only safe when the outermost edge (Vercel) overwrites `X-Forwarded-For` with the visitor's address. Check once in production (log `$request->ip()` and the raw header for a known client) before relying on the per-IP limits.

## Security notes

- Passwords are hashed by the `User` model cast. Every new password (register, reset, set, change) follows one rule, `App\Support\NewPasswordRules`: 8+ characters with letters and numbers, at most 72 bytes (bcrypt ignores the rest), no NUL byte (bcrypt ignores everything after one). Sign-in caps the password at 1024 bytes. Refresh tokens and reset tokens are stored as SHA-256 (both are long random values; reset rows stored as bcrypt before the switch are accepted until they expire). A successful sign-in upgrades a hash made with an older bcrypt cost. Unknown emails and accounts without a password are checked against a dummy hash, so sign-in timing does not reveal whether an account exists.
- Refresh tokens are rotated on every use (`TokenFactory::rotate`). Each belongs to a family (one login) and carries `family_started_at` (migration `2026_11_11_120001_add_family_started_at_to_refresh_tokens_table`: set at login, copied on every rotation, backfilled with the earliest `created_at` of each existing family; a row the backfill missed falls back to the earliest `created_at` left in its family, read only in that case). Rules, all checked under the owner's row lock:
  - Presenting a revoked token revokes the whole family (reuse detection), with **one grace** per rotated token: when all of these hold, a new sibling token of the same family is issued and nothing is revoked (two tabs refreshing with the same cookie, or a retry after a lost response): the token was revoked by rotation (`replaced_by` set) less than `jwt.refresh_reuse_grace_seconds` (20) ago; it has not used its grace yet (`grace_used_at` is null, migration `2026_11_11_120002_add_grace_used_at_to_refresh_tokens_table`); the request's user agent equals the one stored on the token; and its successor is still unrevoked and unexpired (not rotated, logged out or revoked since). The grace is then spent (`grace_used_at` set) and both the successor and the sibling stay valid. Anything else is reuse and revokes the family: a second grace attempt, another user agent, a successor that already rotated, or a token revoked by logout, "sign out everywhere" or a family revocation (none of these sets `replaced_by`, and they revoke the successor too). Remaining trade-off: within the 20 seconds after the owner's refresh, someone holding the old token and sending the same user agent (a header anyone can copy) gets one extra session in the family, which reuse detection does not flag; it ends with the family (logout, sign-out-everywhere, the absolute lifetime). The cost of the rule is that a third concurrent tab, or a second retry, signs the whole login out.
  - **Absolute lifetime**: a family can be refreshed for at most `jwt.refresh_family_max_days` (90) after `family_started_at`; past that, `rotate` revokes the family and answers 401. Each new token expires at min(now + `jwt.refresh_ttl_days`, `family_started_at` + `jwt.refresh_family_max_days`) (`TokenFactory::refreshExpiresAt`, the one place that computes it), and the refresh cookie expires with it. A login without "Remember me" uses `jwt.refresh_session_hours` (24) and `jwt.refresh_session_family_max_days` (7) instead, with session cookies (see [Remember me](#remember-me)).
  - An expired token, or one whose owner is gone or inactive, also revokes the family.
  - Logout (`TokenFactory::revoke`) takes the owner's row lock before revoking its family, the same order as rotation, so a rotation running at the same time either commits first (and its new token is revoked by the logout) or waits and then finds its token revoked. `TokenFactoryTest` checks the call order.
  - `auth:prune-refresh-tokens` (hourly) deletes, 1000 rows per `DELETE`: tokens that expired more than `jwt.refresh_prune_retention_days` (7) ago; and tokens revoked that long ago other than by rotation (logout, sign-out-everywhere, family revocation, i.e. `replaced_by` null). A token rotated away (`replaced_by` set) is kept until it has been expired that long, so replaying a stolen old token is detected as reuse for its whole lifetime (up to 30 days) plus the retention. The retention is never less than the grace window. Indexes on `expires_at` and `revoked_at`: migration `2026_11_11_120000_add_prune_indexes_to_refresh_tokens_table`. Only a token presented after its row was pruned gets a plain 401 without revoking its family.
- Access tokens are HS256 JWTs with `iss` (`jwt.issuer`), `aud` (`jwt.audience`), `sub`, `iat`, `exp` and `sv`. `TokenFactory::parseAccessToken` accepts only HS256 with our secret (`alg=none` and other algorithms are refused) and requires `iss` and `aud` to equal the configured values, so a token minted by another environment that shares the secret is refused. Tokens issued before `aud` existed are refused too while `jwt.require_iss_aud` is `true` (the default): they live at most 15 minutes and the frontend refreshes on a 401, so upgrading costs one silent refresh per signed-in tab. Set `JWT_REQUIRE_ISS_AUD=false` for the deploy if that is not acceptable, and remove it 15 minutes later; a claim that is present must match either way. The token carries no role (`JwtCurrentUser` reads it from the database).
- Tokens travel in HttpOnly cookies (the access token is also accepted as a Bearer header).
- Signing out everywhere ends access tokens too, not only refresh tokens. `users.session_version` (unsigned integer, default 0, in `$hidden`; migration `2026_11_11_000003_add_session_version_to_users_table`) is a per-account counter, and every access token carries the version it was issued with as the `sv` claim (`TokenFactory::issueAccessToken`; refresh-token rotation loads the user inside its transaction and issues the current version). `CurrentUserFactory::fromToken` accepts a token only when `sv` (0 when missing, for tokens issued before the claim) exactly equals the stored version. `RefreshTokenRepository::revokeAllForUser` is the one path behind `TokenFactory::revokeAllForUser`, `AuthService::revokeSessions`/`replaceSessions`, `PasswordResetService::reset` and the social takeover: in one transaction it increments the version with a single `UPDATE`, reads it back while the increment still holds the row lock (so it is this call's value, never a later revocation's), revokes the refresh tokens, sets the new version on the model and returns it. The flows that bump it (password set, change and reset, completed email change, admin deactivation or deletion, social takeover of an unverified account; a role change does not bump it, since the role is read from the database on every request) therefore end every earlier access token at once, including one minted in the same second. Set and change pass the returned version to `TokenFactory::issue`, so the calling device's new tokens carry it and that device stays signed in. Logout only revokes its own refresh family and bumps nothing. Refresh-token rotation (`TokenFactory::rotate`) locks the owner's row first (`RefreshTokenRepositoryInterface::findUserForUpdate`, the row the increment locks) and only then re-reads its token under lock, so it cannot interleave with a revocation: a revocation that committed while the rotation waited leaves the token revoked (reuse detection, 401), and one that starts later waits for the rotation to commit and then revokes the refresh token it created too, while the access token it issued carries the old version. `TokenFactoryTest` checks the lock order and the post-lock re-check.
- Social login refuses emails the provider has not verified. If an unverified local account already uses that email, its password is removed (set to NULL) and its sessions are revoked, including the squatter's still-valid access token (`session_version`), which could otherwise set a password on the now-verified account, so a pre-registered account cannot be taken over.
- That takeover of an unverified account by social login is intentional (`SocialAuthService::resolveUser`, tested by `AccountFlowsTest::test_social_login_takes_over_unverified_local_account_by_clearing_its_password`). Someone can register an account with another person's email before that person signs up. When the real owner later signs in with Google, GitHub or Discord and the provider vouches for the email, they get the account: it is marked verified, the squatter's password stops working and the squatter's sessions are revoked. Because every signed-in route outside `/api/auth` needs a verified email, an unverified account holds no app data or access, so handing it to the proven owner loses nothing.
- Unverified accounts can sign in but reach only `/api/auth/*`. Broadcasting channel auth goes only through `/api/chat/auth`, `/api/rooms/socket-auth` and `/api/notifications/socket-auth`, which are in the verified group; there is no `Broadcast::routes()` (`/broadcasting/auth` is a 404), so an unverified account cannot subscribe to any private channel.
- Changing the sign-in email only takes effect once the signed link mailed to the new address is opened and confirmed; until then the old address keeps working for sign-in and password resets. The old address is told when a change is requested (with a signed link that cancels it without signing in) and when it happens, and confirming revokes every refresh token (see [Changing the sign-in email](#changing-the-sign-in-email)).
- A stolen session cannot finish an email change on its own. Accounts with a password must type it. Accounts without a password (`password` is NULL) have none to type, so their current address has to approve the change before the new address receives anything, and the confirmation route refuses new-address links until that approval is recorded. The same applies to a first password set in Settings without a current one (which a stolen session could do) until the owner proves the inbox by a password reset through email or a completed email change (`password_set_without_proof_at`). The flag survives disconnecting the social login. Accounts that existed before the flag are backfilled failing closed (social accounts with a non-placeholder password, or every social account when the placeholder was not configured), so their next email change needs current-inbox approval once, or a password reset by email first. Because an earlier disconnect deleted the `social_accounts` row, the backfill also stamps accounts without one whose email was verified within 5 minutes of creation, as social sign-in does. This heuristic is approximate. It also stamps an email sign-up that clicked its verification link within 5 minutes, which costs that member one current-inbox approval. It misses an unverified email sign-up later claimed through social sign-in (verified at that moment, not at creation) that set a first password and then disconnected the social login before this change. An account whose verification time was moved by a completed email change is also missed, but that change already counts as inbox proof. It also misses accounts with no `email_verified_at`, which cannot reach the email-change routes anyway.
- Opening an emailed link never changes anything. A GET on the verification, approval, confirmation or deletion link only redirects to a public frontend page (`/account-link/{kind}`, no session needed, since confirming an email change signs every device out), and only that page's button POSTs the same signed URL. So a mail scanner, antivirus or link preview that prefetches the link cannot verify an address, approve an attacker's email change, apply one, or delete the account. The cancel links still act on GET, because cancelling is the safe direction; a prefetch can at worst cancel a pending change or deletion, which the owner can request again. Results land on the public `/account-link/result` page, never on a signed-in page that would bounce a browser without a session to sign-in.
- Approving, applying and cancelling are compare-and-set updates bound to the exact request, so a request replaced or cancelled while a link is being opened cannot end up approved or applied, and two clicks at once mail the new address once.
- Email-change links are HMACs over the pending address, a per-request nonce and the password hash. Changing or resetting the password therefore voids any change an attacker started with a stolen session, even though the link needs no login; it also clears the pending change. Before this, the attacker's link stayed valid for up to an hour after the victim changed their password.
- Remaining trade-offs: (1) A stolen session on a social-only account can still set a first password (`PUT /api/me/password/set`). It is marked, so it does not help with email changes or account deletion, but it gives the attacker password sign-in until the owner resets it; the owner is mailed a "password added" notice with a forgot-password link. (2) The cancel link and the approval link are opened by whoever reads the current inbox, so they are only as safe as that inbox, and an owner who approves without reading defeats the approval step. (3) The cancel link lives 7 days so it outlasts resends; it can only cancel the request it was mailed for. (4) Rotating `APP_KEY` voids every pending email-change link (members resend). (5) `GET /api/me` does not say beforehand that a marked account will go through the approval step, so the hint under the Settings form still promises a link to the new address; once the change is stored, the pending box shows the real next step (`pending_email_awaiting_current`).
- Accounts made by social sign-in store no password (`password` is NULL; there is no shared placeholder any more). Password sign-in refuses them with the same 401 as a wrong password, `Hash::check` is never run against NULL (a dummy hash is checked instead, so the refusal takes as long), and every "has a password" decision reads `User::hasPassword()`. Migration `2026_11_11_000001_clear_social_placeholder_passwords` nulls the password (and `password_set_without_proof_at`, the pending email change and the pending deletion, whose links were bound to the old hash anyway) of users with a `social_accounts` row whose hash matches the old placeholder. It nulls any user whose hash matches, with or without a `social_accounts` row. It reads `SOCIAL_PLACEHOLDER_PASSWORD` from the process environment, falling back to parsing `.env` itself (so a cached config does not hide it); `.env.example` keeps a commented-out line for it. It never skips silently: with no placeholder found while social accounts with a password exist it throws, asking the operator to set the variable and re-run, or to set `SKIP_SOCIAL_PLACEHOLDER_CLEANUP=true` when the environment never used a placeholder. Once it has run, the variable can be removed. `down()` is a no-op.
- An account without a password of its own cannot disconnect its last social provider (it would have no way left to sign in), and the check runs under a row lock so concurrent disconnects cannot bypass it.
- A stolen session cannot delete an account on its own. Accounts with a password must type it (the endpoint is rate limited per account). Accounts without a password, or with a first password set in Settings and not yet proven (`password_set_without_proof_at`), confirm through a signed link mailed to the current address (opening it only shows a page; its button POSTs the link); the link is single-use (compare-and-set), expires after 60 minutes and is voided by a newer request, a cancellation, or any email or password change. Remaining trade-offs: (1) the confirmation link is only as safe as the inbox; (2) a stolen session can still trigger the mail (rate limited), which the owner can ignore or cancel; (3) rotating `APP_KEY` voids pending deletion links.
- Registration ignores any `role` in the request, so every new user is a `user`. Admins and moderators are set in the database or by an admin through `PATCH /api/admin/users/{user}`.

## Known follow-ups

- GitHub emails are treated as verified without checking. Check the verified flag from GitHub's email list before launch.
- The real SMTP delivery and the Google and GitHub logins have only been tested with fakes and mocks.
- `MovieNightPolicy` runs a database query (`invites()->exists()`) inside the policy. Leave it until the movie night feature exists, then move that check into a repository.
- `MessagePolicy::view` follows the room: any logged-in user can read a public room's messages, only members and invitees a private room's.
- A few places do not follow the module rules in `CLAUDE.md` yet:
  - `PeopleRecommendationService` has no interface and is injected as a concrete class; it and `RecommendationService` use other modules' repositories (`WatchlistRepositoryInterface`, `PersonRepositoryInterface`) instead of their service interfaces.
  - `CinemaService` calls Google Places with `Http::` directly instead of through a repository.
  - `MovieController::show` reads `type` from the raw `Request` instead of a FormRequest.
  - `PeopleRecommendationService` builds poster URLs with a hard-coded `https://image.tmdb.org/t/p` instead of `TMDB_IMAGE_URL`.
- `config/services.php` still has unused `postmark`, `resend`, `ses` and `slack` entries from the Laravel skeleton.

## Adding a new entity

Follow the same layout, for example `app/Review/` with `Controllers/`, `FormRequests/`, `InterfaceServices/`, `Services/`, `InterfaceRepository/`, `Repository/`, and its own `ReviewModuleServiceProvider` registered in `bootstrap/providers.php`. Keep the Eloquent model in `app/Models`.

Give the entity its own routes file under `routes/` (snake_case, for example `routes/reviews.php`) and `require` it from the right group in `routes/api.php`: inside the `['auth.jwt', 'verified.email']` group for signed-in routes (write bare routes, no `auth.jwt` wrapper), or after that group for public guest routes. Do not add entity routes to `api.php` itself.
