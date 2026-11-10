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
  Middleware/           auth.jwt, auth.optional, verified.email, role
  InterfaceServices/    Service contracts
  Services/             Business logic
  InterfaceRepository/  Repository contracts
  Repository/           Eloquent queries
  DTOs/
    Requests/           Input data objects built by the form requests
    Responses/          Output data objects returned by the services
  Factories/            TokenFactory, CurrentUserFactory
  CurrentUser/          CurrentUser interface, JwtCurrentUser, GuestUser
  Support/              AuthCookies, TokenPair, DiscordOAuthClient, GooglePhoto
  Mail/                 Verification and reset emails, BrevoTransport
  Listeners/            Sends the verification email on registration
  Exceptions/           Domain exceptions the controllers map to responses
  Providers/            AuthModuleServiceProvider (interface bindings)
```

## How a request flows

1. A request hits a route in `routes/api.php` or one of the files it `require`s.
2. A middleware (`auth.jwt`, `auth.optional`, `verified.email` or `role`) asks `CurrentUserFactory` who is calling.
3. The factory reads the access token (cookie or Bearer header) and returns a `JwtCurrentUser`, or a `GuestUser` if the token is missing or invalid.
4. On a signed-in route outside `/api/auth`, `auth.jwt` rejects a guest with 401, then `verified.email` rejects an unverified account with 403 `{"message": "Verify your email address to continue.", "code": "email_unverified"}`. Both run before rate limiting and route model binding (the priority list in `bootstrap/app.php`), so a refused request spends no rate-limit slot and learns nothing about the bound record.
5. The controller validates the input and calls a service through its interface.
6. The service applies the rules and uses repositories for data.
7. Policies receive the `CurrentUser` to decide what it may do.

## Routes

| File | Purpose |
| --- | --- |
| `routes/api.php` | `/api/health`, `/api/auth` register, login, refresh, logout and `me`, with rate limits. It also holds the public routes of the other modules (TMDB movies/people/genres, weather, weather mood, movie recommendations, nearby cinemas, documentation), `require`s the three files below, holds the signed `GET /api/me/email/confirm/{id}/{hash}` link (outside the verified group, because it must work without a session), and holds the one `['auth.jwt', 'verified.email']` group that wraps `social.php`, `admin.php`, `/api/chat/*`, `/api/watched-recommendations`, `/api/people-recommendations`, `/api/following-recommendations` and `/api/taste-profile` |
| `routes/auth_flows.php` | Email verification, password reset and Google/GitHub/Discord login routes |
| `routes/social.php` | Loaded inside the verified group: home, `/me` (including `/me/email`), users and following, lists, watchlist, ratings, reviews, movie nights, rooms (with `rooms/socket-auth`), notifications (with `notifications/socket-auth`) |
| `routes/admin.php` | `/api/admin/*`, loaded inside the verified group and adding `role:admin` or `role:admin,moderator` (see [Admin module](#admin-module)) |
| `routes/channels.php` | Reverb private-channel rules (`chat.*`, `room.*`, `rooms.*`, `notifications.*`). Loaded by `GroqModuleServiceProvider` when broadcasting resolves |
| `routes/console.php` | The `rooms:prune-messages` command and its every-minute schedule |

All auth endpoints:

| Method and path | Auth | Purpose |
| --- | --- | --- |
| `POST /api/auth/register` | none | Create an account, send the verification email, set the token cookies |
| `POST /api/auth/login` | none | Log in and set the token cookies |
| `POST /api/auth/refresh` | refresh cookie | Rotate the refresh token and issue new tokens |
| `POST /api/auth/logout` | refresh cookie | Revoke the session and clear the cookies |
| `GET /api/auth/me` | `auth.jwt` | The current user. Works for an unverified account (`email_verified_at` is `null`), so the frontend can show the verify screen |
| `GET /api/auth/verify-email/{id}/{hash}` | signed link | Verify the email, then redirect to the frontend. A bad or expired signature redirects to `{FRONTEND_URL}/verify-email?status=invalid\|expired` (handled in `bootstrap/app.php`) |
| `POST /api/auth/resend-verification` | none | Resend the verification email |
| `POST /api/auth/forgot-password` | none | Send a reset link (same response whether or not the account exists) |
| `POST /api/auth/reset-password` | reset token | Set a new password and revoke all sessions |
| `GET /api/auth/social/{google\|github\|discord}` | none | Start the OAuth login |
| `GET /api/auth/social/{google\|github\|discord}/callback` | state cookie | Finish the OAuth login, then redirect to the frontend |

## Config

| File | Purpose |
| --- | --- |
| `.env.example` | Every variable the app reads, with placeholders (see [Setup](#setup)) |
| `config/jwt.php` | Signing secret, issuer, access lifetime (15 min), refresh lifetime (30 days), cookie names |
| `config/cors.php` | CORS rules for the frontend |
| `config/services.php` | Google, GitHub and Discord OAuth credentials, frontend URL, `tmdb`, `groq` (`key`, `watched_key`, `model`, `url`), `gemini`, `weatherapi`, `google_places`, `social_password` |
| `config/mail.php` | Default mailer `brevo` (HTTP API) and `brevo-smtp` (SMTP relay) |
| `config/broadcasting.php`, `config/reverb.php` | The Reverb connection, its public host/port/scheme for browsers, and the allowed origins (`REVERB_ALLOWED_ORIGINS`, default the `FRONTEND_URL` host) |
| `phpunit.xml` | Test-only `JWT_SECRET` and `FRONTEND_URL` |
| `app/Providers/AppServiceProvider.php` | Defines the `tmdb` limiter, and in the local environment starts Reverb automatically when `php artisan serve` runs. Module bindings, listeners and other limiters live in each module's provider |

## Setup

Copy `.env.example` to `.env` (it is gitignored) and set:

| Variable | Notes |
| --- | --- |
| `DB_*` | Database connection. `.env.example` defaults to SQLite (`database/database.sqlite`); the Docker image ships `pdo_mysql` for MySQL |
| `JWT_SECRET` | Required, at least 32 characters. Generate one with `php -r "echo bin2hex(random_bytes(32));"`. Without it, login and register return a 500 |
| `JWT_ACCESS_TTL`, `JWT_REFRESH_TTL_DAYS` | Token lifetimes, default 15 minutes and 30 days |
| `FRONTEND_URL` | Where emails link to and where the social login redirects. Also the CORS origin |
| `MAIL_MAILER`, `BREVO_API_KEY`, `MAIL_FROM_*` | The default mailer is `brevo` (Brevo's transactional HTTP API, `app/Auth/Mail/BrevoTransport.php`); the sender must be verified in Brevo. `MAIL_MAILER=brevo-smtp` uses `BREVO_SMTP_USERNAME`/`BREVO_SMTP_KEY` instead. For local work, `MAIL_MAILER=log` writes emails to `storage/logs/laravel.log` |
| `GOOGLE_*`, `GITHUB_*`, `DISCORD_*` | OAuth client id, secret and redirect URI. The redirect URI must match the callback route, for example `http://localhost:8000/api/auth/social/google/callback` |
| `SOCIAL_PLACEHOLDER_PASSWORD` | Optional shared password given to social sign-ups so Settings can let them set a first one (`app/Support/SocialPlaceholderPassword.php`). It is refused at login and as a new password. Empty = a random password per user |
| `TMDB_API_KEY` | Needed by the Genre, Movie and Person modules. `TMDB_URL` and `TMDB_IMAGE_URL` have defaults |
| `GROQ_API_KEY` | Needed by chat, weather mood, weather recommendations and review moderation/sentiment. `GROQ_MODEL` defaults to `openai/gpt-oss-20b`; `GROQ_API_URL` has a default |
| `GROQ_API_KEY_2` | A second Groq account key, used only by the watched-history and favourite-people recommendations (`keyProfile: 'watched'`) |
| `GEMINI_API_KEY`, `GEMINI_MODEL` | Optional for the standalone Gemini client. `GEMINI_URL` has a default |
| `WEATHERAPI_KEY` | Needed by `/api/weather` and weather recommendations (WeatherAPI.com). `WEATHERAPI_URL`, `WEATHERAPI_DEFAULT_LATITUDE`/`_LONGITUDE` (Beirut) and `WEATHERAPI_TIMEZONE` (`Asia/Beirut`) have defaults |
| `GOOGLE_PLACES_API_KEY` | Needed by `/api/nearby-cinemas` (Google Places API, new) |
| `REVERB_*` | Socket server credentials (generate with `php artisan reverb:install`), `REVERB_PORT=18080`, and the optional `REVERB_PUBLIC_*` and `REVERB_ALLOWED_ORIGINS` |

Then run `composer install`, `php artisan key:generate` and `php artisan migrate`. `composer run dev` is recommended locally (`PHP_CLI_SERVER_WORKERS=4`) so slow API calls don't block other pages; run `php artisan schedule:work` too for room message pruning.

## Rate limits

Every limiter is a named limiter defined in the provider of the module it protects, and routes use it with `throttle:<name>`. The plain `throttle:N,M` middleware is avoided because it shares one bucket per IP across routes.

| Limiter | Defined in | Per minute | Used by |
| --- | --- | --- | --- |
| `auth-register`, `auth-login`, `auth-reset-password` | `AuthModuleServiceProvider` | 5 | the matching auth route |
| `auth-refresh` | `AuthModuleServiceProvider` | 30 | `POST /api/auth/refresh` |
| `auth-verify-email` | `AuthModuleServiceProvider` | 20 | the verification link |
| `auth-resend-verification`, `auth-forgot-password` | `AuthModuleServiceProvider` | 3 | the matching auth route |
| `ai` | `GroqModuleServiceProvider` | 10 | `POST /api/weather-mood`, `GET /api/movie-recommendations`, `/api/watched-recommendations`, `/api/people-recommendations` (all call paid AI APIs) |
| `chat` | `GroqModuleServiceProvider` | 10 (and 100 per hour) | `POST /api/chat/messages`, per signed-in user |
| `me-email-change` | `UserModuleServiceProvider` | 3 (and 10 per hour), per account | `PUT /api/me/email`, `POST /api/me/email/resend` |
| `me-email-confirm` | `UserModuleServiceProvider` | 20, per IP | the three signed email-change links (`me.email.confirm`, `me.email.authorize`, `me.email.cancel`), one shared bucket |
| `weather` | `WeatherModuleServiceProvider` | 30 | `GET /api/weather` |
| `tmdb` | `AppServiceProvider` | 60 | the eight TMDB routes (movies, trending, search, discover, movie details, person, collaborators, genres) |
| `room-messages`, `room-reads`, `room-socket`, `room-invites` | `RoomModuleServiceProvider` | 30, 120, 60, 30 | the matching room routes, per signed-in user |
| `notification-socket` | `NotificationModuleServiceProvider` | 30 | `POST /api/notifications/socket-auth`, per signed-in user |

Each auth limiter is checked in two buckets: one per IP, and one per email when the request contains an email. Changing the email therefore cannot reset the IP bucket. `ai`, `weather` and `tmdb` count per IP; the chat, room and notification limiters count per user (`bootstrap/app.php` runs `auth.jwt` and `verified.email` before throttling, so the user is known and an unverified account never spends a slot).

A few routes still use plain `throttle:N,M`: `PUT /api/me/password` (6), `PUT /api/ratings/{tmdbId}` (60), `PUT /api/reviews/mine` (20), `POST /api/reviews/{review}/helpful` (60), `POST /api/reviews/{review}/report` (10), `/api/chat/connection` and `/api/chat/auth` (60), `/api/following-recommendations` and `/api/taste-profile` (60), `/api/nearby-cinemas` (30).

The per-email bucket is shared across IPs, so someone who knows an email can block logins for that account for up to a minute. A captcha would remove that (see [Known follow-ups](#known-follow-ups)).

## DTOs

Plain `final readonly` classes in `app/Auth/DTOs/`. Services take Request DTOs and return Response DTOs, so they never see raw arrays, `Request` bodies or Socialite types.

| File | Purpose |
| --- | --- |
| `Requests/RegisterData`, `LoginData` | Built by `RegisterRequest::toDto()` and `LoginRequest::toDto()` |
| `Requests/ForgotPasswordData`, `ResetPasswordData`, `ResendVerificationData` | Built by the matching form requests |
| `Requests/VerifyEmailData` | The `id` and `hash` from the signed verification link |
| `Requests/SocialLoginData` | What the module needs from an OAuth provider, built by `fromSocialite()` in the controller |
| `Responses/UserResponse` | The public user JSON (same fields as before) |
| `Responses/AuthResponse` | The user plus the tokens. `body()` is the JSON, and `AuthCookies` turns the tokens into cookies |

`TokenFactory` still returns the internal `TokenPair`. The services convert it with `AuthResponse::fromTokenPair()`.

## Services

Contracts in `app/Auth/InterfaceServices/`, implementations in `app/Auth/Services/`.

| Service | Purpose |
| --- | --- |
| `AuthService` | Register, login, refresh and logout. Throws `InvalidCredentials` or `InvalidRefreshToken` |
| `PasswordResetService` | Sends the reset link (deferred until after the response) and resets the password. Revokes all refresh tokens on reset |
| `EmailVerificationService` | Sends the signed verification link (60 minutes), resends it, and verifies a link |
| `SocialAuthService` | Builds the provider redirect URL, fetches the provider's user (Discord through `Support/DiscordOAuthClient`), and Google/GitHub/Discord login. Refuses unverified provider emails and takes over unverified local accounts by resetting their password |

## Repositories

Contracts in `app/Auth/InterfaceRepository/`, implementations in `app/Auth/Repository/`.

| Repository | Model | Purpose |
| --- | --- | --- |
| `UserRepository` | `User` | Find by id or email, create, mark verified, update password |
| `RefreshTokenRepository` | `RefreshToken` | Create, find (with row lock for rotation), mark rotated, revoke a family or all of a user's tokens |
| `PasswordResetRepository` | `PasswordReset` | Replace, find latest and delete the reset row for an email |
| `SocialAccountRepository` | `SocialAccount` | Find by provider, create, update the access token |

## Tokens and current user

| File | Purpose |
| --- | --- |
| `Factories/TokenFactory.php` | Issues HS256 access tokens and refresh tokens, rotates and revokes refresh tokens, parses access tokens |
| `Factories/CurrentUserFactory.php` | Resolves the current user once per request, or a guest |
| `CurrentUser/CurrentUser.php` | Interface that policies and middleware depend on |
| `CurrentUser/JwtCurrentUser.php` | The logged-in user. The role is read from the database, so a demotion applies immediately |
| `CurrentUser/GuestUser.php` | The anonymous user, never authenticated |
| `Support/TokenPair.php` | Holds a user's access and refresh tokens with their expiry times |
| `Support/AuthCookies.php` | Sets and clears the httpOnly access and refresh cookies |

## Controllers

All in `app/Auth/Controllers/`. They contain no database queries.

| File | Purpose |
| --- | --- |
| `AuthController.php` | Register, login, refresh, logout and `me`. Has the captcha TODO |
| `EmailVerificationController.php` | Verifies the emailed link and resends it |
| `PasswordResetController.php` | Forgot-password and reset-password |
| `SocialAuthController.php` | Google, GitHub and Discord redirect and callback (state cookie check, redirects) |

## Middleware

All in `app/Auth/Middleware/`.

| Alias | File | Behavior |
| --- | --- | --- |
| `auth.jwt` | `AuthenticateJwt.php` | Requires a logged-in user, otherwise 401. Does not look at the email, so `/api/auth/me` works while unverified |
| `auth.optional` | `AuthenticateOptional.php` | Lets anonymous requests through as a guest |
| `verified.email` | `EnsureEmailVerified.php` | 401 for a guest, 403 `{"message": "Verify your email address to continue.", "code": "email_unverified"}` if the email is unverified |
| `role` | `EnsureRole.php` | 403 unless the user has one of the required roles |

**A verified email is required everywhere.** Every route that needs a signed-in user, except `/api/auth/*`, needs a verified email too, admins and moderators included. `routes/api.php` applies `['auth.jwt', 'verified.email']` once, to a group that `require`s `social.php` and `admin.php` and holds the chat and signed-in recommendation routes, so a route added there inherits it. `/api/auth/*` (register, login, refresh, logout, `me`, the verification link, resend, forgot/reset password, social login) stays open so an unverified account can verify, resend the link or sign out; login, register and refresh still issue tokens to it. Public guest routes (TMDB, weather, weather mood, movie recommendations, nearby cinemas, health, documentation) are unchanged. `tests/Feature/Auth/RequireVerifiedEmailTest.php` fails if a route with `auth.jwt` outside `/api/auth` lacks `verified.email`. The OpenAPI spec adds the `EmailUnverified` 403 to every secured operation outside `/api/auth` (`OpenApiSpec::documentEmailVerification`).

## Form requests

Each module keeps its Laravel form requests in a `FormRequests/` folder:

- `app/Auth/FormRequests/`: `RegisterRequest`, `LoginRequest`, `ForgotPasswordRequest`, `ResetPasswordRequest`, `ResendVerificationRequest`.
- `app/Movie/FormRequests/`: `MovieListRequest`, `MovieTrendingRequest`, `MovieSearchRequest`, `MovieDiscoverRequest`.
- Every other module with input does the same (listed in each module's section below).

A form request checks the incoming HTTP request before the controller runs, and turns it into a clean object for the service.

| Part | What it does |
| --- | --- |
| `rules()` | The validation rules for one endpoint. For example, `MovieSearchRequest` requires `q` (1 to 100 characters) and allows `page` from 1 to 500 and `year` from 1874 to 2100. `RegisterRequest` checks that the email is unique and that the password is at least 8 characters with letters and numbers |
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

Do not confuse `FormRequests/` with `DTOs/Requests/`. The form request validates the HTTP input. The DTO is the plain data object it produces and hands to the service.

## Email

| File | Purpose |
| --- | --- |
| `Listeners/SendEmailVerification.php` | Sends the verification link when a user registers. Registered in `AuthModuleServiceProvider` |
| `Mail/VerifyEmailMail.php` | The verification email |
| `Mail/ResetPasswordMail.php` | The password reset email |
| `Mail/BrevoTransport.php` | The `brevo` mail transport (registered with `Mail::extend` in `AuthModuleServiceProvider`). Posts to `https://api.brevo.com/v3/smtp/email`; its errors never include the email body, links or key |
| `resources/views/mail/layout.blade.php` | The shared WatchWise-branded HTML layout (images in `public/images/mail/`) |
| `resources/views/mail/verify-email.blade.php`, `reset-password.blade.php` | HTML templates |
| `resources/views/mail/text/*.blade.php` | Plain-text versions of both emails |
| `app/User/Mail/*`, `resources/views/mail/confirm-email-change`, `authorize-email-change`, `email-change-requested`, `email-changed` | The email change emails (User module), on the same layout, with plain-text versions in `resources/views/mail/text/`. The layout takes an optional secondary link (`secondaryPrompt`, `secondaryAction`, `secondaryUrl`), used for the "cancel this change" link |

Emails are sent directly, not queued, so no queue worker is needed. The work for an existing account (creating the token, hashing it, sending the mail) runs after the response is sent using `defer()`. This keeps the response time the same for known and unknown emails, and a broken mail server is logged without failing the request.

## Exceptions

In `app/Auth/Exceptions/`. Services throw them and controllers turn them into responses.

`InvalidCredentials` and `InvalidResetToken` render their own 401 and 422 responses, as does `SocialLoginNotConfigured` (503). `InvalidRefreshToken`, `UnverifiedProviderEmail`, `EmailRequired`, `AccountDisabled` and `SocialProviderFailed` are caught in the controllers that need to clear cookies or redirect.

## Authorization

| File | Purpose |
| --- | --- |
| `app/Providers/AuthorizationServiceProvider.php` | Maps models to policies, binds the guest as the default current user, makes the Gate use `CurrentUser` |

Most policies stay in `app/Policies/` because each belongs to the entity it protects. The Movie List, Movie Night, Watchlist and User policies live in their own module folders (`app/MovieList/Policies/`, `app/MovieNight/Policies/`, `app/Watchlist/Policies/`, `app/User/Policies/`):

| Policy | Rule |
| --- | --- |
| `ReviewPolicy` | Owner-based access |
| `UserPreferencePolicy` (`app/User/Policies/`) | Owner-based access |
| `WatchlistPolicy` (`app/Watchlist/Policies/`) | Owner-based access |
| `MovieListPolicy`, `MovieListItemPolicy` (`app/MovieList/Policies/`) | Private lists are visible to their owner only |
| `MovieNightPolicy` (`app/MovieNight/Policies/`) | Organizer and invitees can view a night; only the organizer can change it |
| `MessagePolicy` | Authenticated users can read and post in discussion rooms; author edits, author or admin deletes |
| `NotificationPolicy` (`app/Notification/Policies/`) | Members see and change only their own feed notifications, never room read receipts |
| `UserPolicy` (`app/User/Policies/`) | Private profiles are visible to the owner and admins |

## Wiring

| File | Purpose |
| --- | --- |
| `bootstrap/app.php` | Registers the API and console routes, the four middleware aliases, runs `AuthenticateJwt` before `ThrottleRequests`, redirects bad verification links to the frontend, and renders JSON errors for every `api/*` request |
| `bootstrap/providers.php` | Registers `AppServiceProvider`, `AuthorizationServiceProvider` and all 18 module service providers |

## Tests

| Location | Covers |
| --- | --- |
| `tests/Feature/Auth/AuthenticationTest.php` | Register, login, refresh, logout, throttling |
| `tests/Feature/Auth/AccountFlowsTest.php` | Email verification, password reset, social login |
| `tests/Feature/Auth/BrevoVerificationTest.php` | The Brevo transport and the verification email end to end |
| `tests/Feature/Auth/GooglePhotoTest.php` | Detecting Google's generated placeholder avatar |
| `tests/Feature/Auth/MiddlewareTest.php` | The four middleware |
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
                        MediaType (`movie`/`tv` and the frontend key "550" / "tv-1399"), LikePattern (escaped LIKE search), SocialPlaceholderPassword
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
- **Local catalogue:** `MovieCatalogService::remember()` stores a film in the `movies` table (`LocalMovie`, with its first director) the first time someone rates or reviews it. Admins edit, hide or feature these rows (see [Admin module](#admin-module)); hidden films are left out of the app's own lists and stats. TV shows are never stored.
- Movie lists are paginated with `page` from 1 to 500 and 20 movies per page. Responses include `page`, `total_pages` (capped at 500, TMDB's limit) and `total_results`. Genres are a short fixed list, so they are not paginated.
- Ratings are converted from TMDB's 0 to 10 scale to 0 to 5. Adult content is always excluded.
- Setup: set `TMDB_API_KEY` in `.env`. `TMDB_URL` and `TMDB_IMAGE_URL` have working defaults in `config/services.php`.
- Routes are in `routes/api.php` and the config is the `tmdb` entry in `config/services.php`.
- Tests: `tests/Feature/Movie/`, `tests/Feature/Genre/` and `tests/Feature/Support/` (the endpoints, repositories, catalogue and `TmdbHttpClient`), `tests/Feature/Social/TvShowsTest.php`, and `tests/Unit/Movie/` and `tests/Unit/Genre/` (the services).

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

- Both are public, under the `tmdb` limiter, and cached for 6 hours (`tmdb.person.{id}`, `tmdb.person.{id}.collaborators`). A TMDB outage makes collaborators an empty list (not cached) rather than an error.
- Tests: `tests/Feature/Person/`.

## MovieList and MovieNight modules

`app/MovieList/` and `app/MovieNight/` use the same layers as Auth. Their Eloquent models stay in `app/Models/`, and `MovieListResource` stays in `app/MovieList/Resources/` because the profile also uses it.

```
app/MovieList/
  Controllers/          MovieListController
  FormRequests/         MovieListIndexRequest, StoreMovieListRequest, UpdateMovieListRequest, AddMovieListItemRequest, RemoveMovieListItemRequest
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

- The routes did not change (`/api/lists` and `/api/movie-nights`, in `routes/social.php`).
- The form requests run the policy check in `authorize()`, so a forbidden request is rejected before validation, as before.
- `GET /api/lists` with an unknown `scope` now fails validation (422 with an `errors` object) instead of returning the message "Unknown scope.".
- A public list whose owner's profile is followers-only or private is for that owner's followers only: `scope=public` leaves it out for everyone else, and `GET /api/lists/{list}` answers 403 (`MovieListPolicy::view`; the owner and admins always see it). `scope=following` already only lists people you follow.
- Tests: `tests/Feature/Social/ContentTest.php` and `tests/Feature/Social/MovieNightRulesTest.php`.

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

- The routes did not change (`/api/watchlist`, in `routes/social.php`).
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

`app/Review/` holds the Movie page's Reviews section and the review data the Admin module moderates. The `Review`, `ReviewVote` and `ReviewReport` models stay in `app/Models/`, and `ReviewPolicy` in `app/Policies/`.

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
| `POST /api/reviews/{review}/report` | Report with a `reason` (see [Admin module](#admin-module)) |

- **Moderation on save:** `ReviewModerationService` asks Groq (strict JSON schema) for the labels `offensive language`, `spoiler` and `spam`. No labels means the review is approved at once; any label sends it to the admin queue as pending. If Groq fails, the labels are null and the review waits for a moderator. `has_spoilers` is set when the author ticks it or Groq finds a spoiler.
- **Sentiment:** `SentimentClassifier` asks Groq for positive/neutral/negative and falls back to the star rating (4 to 5 positive, 3 neutral, 1 to 2 negative).
- Saving a movie review also remembers the film in the local catalogue. Marking helpful sends a `review_helpful` notification; taking it back removes it.
- Tests: `tests/Feature/Social/MovieReviewsTest.php`, `tests/Feature/Review/ReviewReportTest.php` and `tests/Feature/Admin/AdminReviewsTest.php`.

## User module

`app/User/` holds everything about the person behind the account, in the same layers as Auth: their own account (`/api/me`), public profiles and following (`/api/users`) and the Home page (`/api/home`). The `User`, `UserPreference` and `Follow` models stay in `app/Models/`. Sign-in, registration and tokens stay in the Auth module.

```
app/User/
  Controllers/          UserController (account under /me, profiles and following under /users, and /home)
  FormRequests/         UpdateProfileRequest, UpdatePreferencesRequest, UpdatePasswordRequest, UploadAvatarRequest, DeleteAccountRequest, UserSearchRequest, RequestEmailChangeRequest
  InterfaceServices/    AccountServiceInterface, ProfileServiceInterface, HomeServiceInterface, EmailChangeServiceInterface
  Services/             AccountService (settings, password, avatar, delete), ProfileService (search, profile page, follow), HomeService, ProfilePrivacy (what a viewer may see of a profile), EmailChangeService (pending email, confirmation link, notices)
  InterfaceRepository/  AccountRepositoryInterface, ProfileRepositoryInterface, HomeRepositoryInterface
  Repository/           AccountRepository, ProfileRepository, HomeRepository
  DTOs/Requests/        UpdateProfileData, UpdatePreferencesData, ChangePasswordData, DeleteAccountData, UserSearchQuery, RequestEmailChangeData, ConfirmEmailChangeData
  DTOs/Responses/       AccountResponse, ProfileResponse, UserSearchResponse, FollowResponse, HomeResponse, EmailChangeOutcome (enum: success, invalid, expired, taken)
  Resources/            MemberResource (the small public card of a user, used by many modules)
  Exceptions/           IncorrectPassword (422 on the wrong field), ProfileSectionHidden (403), NoPendingEmailChange (422)
  Mail/                 ConfirmEmailChangeMail, EmailChangeRequestedMail, EmailChangedMail
  Policies/             UserPolicy, UserPreferencePolicy
  Providers/            UserModuleServiceProvider (interface bindings, the me-email-change and me-email-confirm rate limiters)
```

- Routes are in `routes/social.php`: `/api/home`; `/api/me` (show, update, delete), `/me/preferences`, `/me/password` (revokes the other devices' sessions), `/me/avatar` (upload/delete, public disk), `/me/social/{google|github|discord}` (disconnect), `/me/follow-requests/{username}` (accept/decline), `/me/email` (see below); `/api/users` (search) and `/api/users/{username}` with `followers`, `following` and `follow`/unfollow.
- `ProfilePrivacy` decides what a viewer may see of a profile.
- `/api/home` returns the 3 busiest rooms, your next movie night (accepted guests and the leading vote) and `activity`: the 8 newest items from people you follow (reviews, ratings, public lists, watched titles and watchlist additions), each kind filtered by that person's privacy switches. Spoiler reviews have no excerpt.
- Tests: `tests/Feature/Social/ProfileTest.php`, `PrivacyTest.php`, `SearchTest.php`, `AccountRulesTest.php`, `PlaceholderPasswordTest.php` and `tests/Feature/User/HomeActivityTest.php`, `EmailChangeTest.php`, `PasswordProofBackfillTest.php` and `Repository/AccountRepositoryTest.php` (the compare-and-set email-change writes).

### Changing the sign-in email

`PATCH /api/me` does not change the email: it accepts `email` only when it equals the current one (forms send the whole profile) and rejects a different one with 422. The flow is:

| Method and path | Auth | Purpose |
| --- | --- | --- |
| `PUT /api/me/email` | `auth.jwt` + `verified.email`, `throttle:me-email-change` | `email` (+ `current_password` when the account has one). Stores the address in `users.pending_email` with a fresh random `pending_email_nonce`. With a password: sets `pending_email_authorized_at = now()`, mails a signed 60-minute link to the new address (`ConfirmEmailChangeMail`) and warns the current address (`EmailChangeRequestedMail`, new address masked as `j***@example.com`, with a cancel link). Without one (social placeholder), or while `password_set_without_proof_at` is set (see below): leaves `pending_email_authorized_at` null and mails only the current address (`AuthorizeEmailChangeMail`: a 60-minute approval link plus the cancel link). `email` and `email_verified_at` are untouched |
| `POST /api/me/email/resend` | `auth.jwt` + `verified.email`, `throttle:me-email-change` | Mails the link the change is waiting for again: the approval link to the current address while `pending_email_authorized_at` is null, otherwise the confirmation link to the new one (422 `NoPendingEmailChange` when nothing is pending) |
| `DELETE /api/me/email` | `auth.jwt` + `verified.email` | Clears `pending_email`, `pending_email_nonce` and `pending_email_authorized_at` |
| `GET /api/me/email/authorize/{id}/{hash}` (`me.email.authorize`) | signed link (no login; declared in `routes/api.php` next to `me.email.confirm`), `throttle:me-email-confirm` | When the password proves nothing: the current address approves the change. Sets `pending_email_authorized_at` (compare-and-set) and mails the confirmation link to the new address. Redirects to `FRONTEND_URL/edit-profile?email_change=check-new-inbox\|invalid\|expired\|taken` (opening it again, or twice at once, mails the new address only once) |
| `GET /api/me/email/confirm/{id}/{hash}` (`me.email.confirm`) | signed link (no login; declared in `routes/api.php` outside the verified group), `throttle:me-email-confirm` | Refused (`invalid`) while `pending_email_authorized_at` is null. Otherwise swaps `email = pending_email`, sets `email_verified_at = now()`, clears the pending columns, revokes every refresh token through `AuthServiceInterface::revokeSessions()` and mails the old address (`EmailChangedMail`). Redirects to `FRONTEND_URL/edit-profile?email_change=success\|invalid\|expired\|taken` |
| `GET /api/me/email/cancel/{id}/{hash}` (`me.email.cancel`) | signed link (no login; declared in `routes/api.php` next to `me.email.confirm`), `throttle:me-email-confirm` | The "this wasn't me" link in the mails to the current address (valid 7 days). Clears the pending columns of that request only. Redirects to `FRONTEND_URL/edit-profile?email_change=cancelled\|invalid\|expired` |

- `current_password` is required unless the account still has the social placeholder password (`has_password: false`). Such accounts approve from their current address instead, so a stolen session alone cannot send a link to another inbox. Accounts with a password keep the single link to the new address.
- A first password chosen in Settings (`PUT /api/me/password` while the placeholder is stored, so no current password is asked) proves nothing, since a stolen session could set it. `AccountService::changePassword` stamps `users.password_set_without_proof_at`, and while it is set the email change takes the approval path even though `current_password` is now required too. It is cleared only when the owner proves the inbox: a password reset through the emailed link (`AccountServiceInterface::passwordResetThroughEmail()`, called by `PasswordResetService::reset`) or a completed email change (`applyPendingEmail`). An ordinary password change with the current password keeps it. Disconnecting the social login does not clear it. The migration backfills existing accounts, failing closed: a user whose stored password is not the placeholder is stamped when they have a `social_accounts` row, or when they have none but `email_verified_at` is within 5 minutes of `created_at` (social sign-in verifies at creation, so this catches accounts whose social login was disconnected). The placeholder check is skipped, so these users are stamped regardless, when `SOCIAL_PLACEHOLDER_PASSWORD` is empty. Users on the placeholder and email sign-ups verified later through the emailed link stay null.
- The writes are compare-and-set (`AccountRepository::authorizePendingEmail`, `applyPendingEmail`, `clearPendingEmail($user, $nonce)`): one conditional `UPDATE` that only matches while the row still holds the same pending address, nonce and password hash (and, for approve/apply, the expected approval state). A request replaced or cancelled between the link check and the write is therefore never approved, applied or cancelled by an older link; zero affected rows give `invalid` (or `check-new-inbox` when a simultaneous click already approved the same request).
- The new address must differ from the current one and must not be any account's `email`. It is checked again when the approval or confirmation link is opened; if someone took it meanwhile, the pending change is dropped and the redirect says `taken`.
- Every link's `hash` is `hash_hmac('sha256', purpose|user id|pending_email|pending_email_nonce|password hash, APP_KEY)`, compared with `hash_equals`. So a link only works for its own purpose (an approval or cancel hash is not a confirmation hash), for the request it was mailed for (the nonce changes on every `PUT`, so asking A, then B, then A again leaves the first link for A dead), and only while the password is unchanged: changing it in Settings, resetting it through forgot-password, or a social sign-in replacing an unverified account's password voids every earlier link without any extra wiring. Expired or tampered signatures get the same redirect from `bootstrap/app.php` for all three routes.
- Changing the password (`AccountService::changePassword`) and resetting it (`Auth\Services\PasswordResetService::reset`, inside its transaction, through `AccountServiceInterface::passwordResetThroughEmail()`) also clear the pending change with `EmailChangeServiceInterface::discardPendingChange()`, so Settings stops showing it. Auth only calls the User module's service interface for this, as module rules require; the password-hash binding is what actually voids the links.
- Columns: `pending_email` (migration `2026_11_09_000000_add_pending_email_to_users_table`), `pending_email_nonce` and `pending_email_authorized_at` (migration `2026_11_10_000000_add_email_change_state_to_users_table`, which also drops pending changes made with the old `sha1(pending_email)` links), and `password_set_without_proof_at` (migration `2026_11_10_000001_add_password_set_without_proof_to_users_table`). All are in the `User` model's `$hidden`.
- `GET /api/me` (`AccountResponse`) has a top-level `pending_email` (null when nothing is pending) and `pending_email_awaiting_current` (true while the current address still has to approve).
- Emails are sent with `defer()` + `rescue()`, like the Auth emails.

## Room module

`app/Room/` holds the discussion rooms (group chats about a film), in the same layers as Auth. The `DiscussionRoom` and `Message` models stay in `app/Models/`.

```
app/Room/
  Controllers/          RoomController (rooms, joining, invitations, messages, read receipts), RoomSocketController (socket channel auth)
  FormRequests/         StoreRoomRequest, InviteToRoomRequest, SendRoomMessageRequest, ReadRoomMessagesRequest, RoomSocketRequest
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
| `GET /api/rooms` | The rooms you can see, yours first, then invitations, then other public rooms, with members and your unread count |
| `POST /api/rooms` | Create a public or private (`is_private`) room; the creator becomes its first member |
| `POST /api/rooms/{room}/join` | Join a public room, or accept the invitation to a private one |
| `POST /api/rooms/{room}/invites` | A member of a private room invites people by username |
| `DELETE /api/rooms/{room}` | Delete the room (its creator or an admin), with its messages, members and invitations |
| `DELETE /api/rooms/{room}/invite` | Decline your invitation to a private room |
| `GET /api/rooms/{room}/messages` | The messages you can still see, each with `read_by` (author first) and `unread_by` |
| `POST /api/rooms/{room}/messages` | Send a message (members only) |
| `POST /api/rooms/{room}/read` | The member has seen these messages; returns the ones now deleted |
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
- Tests: `tests/Feature/Room/RoomMessagingTest.php`, `tests/Feature/Room/RoomMembershipTest.php` (the permission matrix is in `tests/Feature/Auth/PolicyTest.php`).

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

- Other modules call `NotificationServiceInterface` after their change is saved. Nobody is notified about their own action, and the Notification module never calls back into them.
- `data` stores ids and the words of the moment (`actor_id`, room or night title, a quote). `NotificationResponse::fromModel()` turns a row into the sentence the page shows (**actor** `text` **target**, `detail`, `film`, `to`, `action`). A row whose actor deleted their account, or that lacks the fields its sentence needs (older rows), is left out. Film titles are looked up by the browser from `film` (`tmdb_id`, `media_type`).
- **Follow requests:** following a followers-only or private profile (`ProfilePrivacy::needsFollowApproval()`) stores a `follow_request` row instead of a follow. That row is the pending request. A nullable requester foreign key and a unique recipient/requester pair prevent concurrent duplicates; the migration consolidates existing duplicates. Pending requests stay in the feed beyond its 100-item history limit. Accepting creates the follow and turns the row into `follow` (so the owner can follow back). Declining, dismissing it, or the requester unfollowing deletes it. `POST /users/{username}/follow` answers `{following, requested, followers}`, and the profile and search responses carry `is_requested`.
- **Settings:** `settings.notifications` in `user_preferences` has one switch per type (`Notification::OPTIONAL_TYPES`, all on by default). A switched-off type is never stored or pushed. Follow requests have no switch.
- **Live:** each new or updated notification is pushed to `notifications.{user id}` as `notification.pushed` (`{notification, silent}`). `silent` is set for the room's "new messages" update when the same message also mentioned you, and for an accepted request. Reads, dismissals and withdrawn requests send `notifications.changed`, and the other tabs reload. A failed push is only logged: the bell catches up on its next load, and polls every minute until its private channel subscribes successfully, and resumes polling on connection or subscription errors.
- Data lookups compare the JSON in PHP (`NotificationRepository::matching()`), so they behave the same on MySQL, PostgreSQL and SQLite.
- Tests: `tests/Feature/Notification/` (feed, follow requests, every trigger, settings and the socket channel). `tests/TestCase.php` fakes the two events, so no test reaches a socket server; a test that fakes its own events has to list `NOTIFICATION_EVENTS` again.

## Admin module

`app/Admin/` is the administrator and moderator dashboard API (brief objective 11). It uses the same layers as Auth. One `AdminController` serves the dashboard, users, reviews and movies, and the routes are in `routes/admin.php` under `/api/admin` with `auth.jwt` and `role:admin,moderator`. The `reviews`, `movies` and `users` data stay in their own modules and tables: Admin calls `ReviewServiceInterface` and `MovieCatalogServiceInterface`, and only its two repositories (`AdminStatsRepository`, `AdminUserRepository`) read the `users` and statistics tables.

```
app/Admin/
  Controllers/          AdminController
  FormRequests/         AdminStatsRequest, AdminUserIndexRequest, UpdateUserRequest, AdminReviewIndexRequest, ModerateReviewRequest, AdminMovieIndexRequest, UpdateLocalMovieRequest
  DTOs/Requests/        AdminStatsQuery, AdminUserQuery, UpdateUserData, ReviewModerationQuery, ModerateReviewData
  DTOs/Responses/       AdminStatsResponse, RankedMovieResponse, GenreShareResponse, ActivityPointResponse, AdminUserResponse, AdminReviewResponse, AdminMovieResponse, AdminPageResponse
  InterfaceServices/    AdminServiceInterface
  Services/             AdminService
  InterfaceRepository/  AdminStatsRepositoryInterface, AdminUserRepositoryInterface
  Repository/           AdminStatsRepository, AdminUserRepository
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

An admin cannot demote, deactivate or delete themselves (`CannotModerateSelf`). A moderator who targets themselves, an admin or another moderator, or sends `role`, gets `CannotModerateStaff`. Deactivating a user revokes their sessions (`AuthServiceInterface::revokeSessions()`), so login, refresh and existing access tokens stop working. The gates `admin.dashboard`, `admin.users`, `admin.reviews`, `admin.movies` and `admin.users.manage` (admin-only: delete users) are defined in `AuthorizationServiceProvider` and backed by `AdminPolicy`.

**What the endpoints return:**

- `stats`: `users_total`, `new_users`, `reviews_total`, `reviews_pending`, `new_reviews`, `active_today`, `active_yesterday`, `flagged_reviews`, `most_watched`, `top_rated`, `popular_genres` and the daily `activity` series. `?range=7|30|90|year` (or `?days=1..90`) picks the window; `range` in the response carries the counts for that window and the equally long one before it, for the change figures.
- `users`: filters `q`, `role` (`user`, `moderator`, `admin`), `status` (`active`, `unverified`, `inactive`), `page`, `per_page`. Each row has `status`, `reviews_count` (reviews with text only) and `last_active_at`. A `summary` gives site-wide totals whatever the filters.
- `reviews`: `?status=pending|approved|flagged|removed` (default `pending`; the four never overlap). A row's `status` is `removed` if `removed_at` is set, else `flagged` if it has an open report, else `approved` if `is_approved`, else `pending`. Rows carry `reports_count`, `reasons`, the author and the film (`movie.title` is null for TV and films not in the catalogue). A `summary` gives the flagged and removed totals and the sentiment split. Approving or rejecting closes the review's open reports; rejecting clears the text and sentiment, keeps the stars and sets `removed_at`.
- `movies`: local catalogue rows (`title`, `overview`, `director`, `runtime`, `release_date`, `genre_ids`, `is_hidden`, `is_featured`, `is_edited`, `synced_at`, `edited_at`) and a `summary.sync` block (`cached`, `overrides`, `last_synced_at`). `PATCH` takes any of those editable fields; `null` clears `director`, `runtime`, `release_date` and `overview`, and an empty `genre_ids` clears the genres. Editing content sets `edited_at`; hiding or featuring does not.

**Supporting changes**

- `users.last_active_at` is stamped by the `auth.jwt` middleware at most once every 5 minutes per user. It is in `User::$hidden`, so public payloads and `/api/auth/me` do not expose it.
- `users.role` accepts `moderator` besides `user` and `admin` (migration `2026_10_29_000001`). `role` is guarded against mass assignment.
- `review_reports` (migration `2026_10_29_000002`) holds one report per member per review with a reason (`Spam`, `Links`, `Spoilers`, `Offensive language`) and `resolved_at`. The same migration adds `reviews.removed_at`. A member reports a review with `POST /api/reviews/{review}/report` (`throttle:10,1`, in the Review module). It is refused (403) for your own review, an unapproved, text-less or removed review, and a review whose author's privacy hides it from you. Re-reporting changes the reason and returns 200; a new report returns 201. When the author rewrites the review, its reports and `removed_at` are cleared.
- `movies.director` and `movies.edited_at` (migration `2026_10_29_000003`). The director is filled from TMDB when a film is first cached.
- Tests: `tests/Feature/Admin/` (every route for guest, member, moderator and admin; stats windows; user insights; moderation; moderator permissions) and `tests/Feature/Review/ReviewReportTest.php`. The API paths are in `openapi/paths/admin.json` and `openapi/paths/social.json`.

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

- Migrations are not run by the container; run `php artisan migrate --force` against the production database yourself.
- The scheduler (`rooms:prune-messages`) is not started by the container either. Opening a room still cleans up its own expired messages, but a cron or worker calling `schedule:run` is needed for full pruning.
- The frontend (Vercel) calls the API through a same-origin proxy (see the frontend config).

## Security notes

- Passwords are hashed by the `User` model cast. Refresh tokens and reset tokens are stored hashed. The refresh token is rotated on every use, and presenting an already-used one revokes the whole session chain.
- Tokens travel in HttpOnly cookies (the access token is also accepted as a Bearer header).
- Social login refuses emails the provider has not verified. If an unverified local account already uses that email, its password is replaced and its sessions are revoked, so a pre-registered account cannot be taken over.
- That takeover of an unverified account by social login is intentional (`SocialAuthService::resolveUser`, tested by `AccountFlowsTest::test_social_login_takes_over_unverified_local_account_by_resetting_its_password`). Someone can register an account with another person's email before that person signs up. When the real owner later signs in with Google, GitHub or Discord and the provider vouches for the email, they get the account: it is marked verified, the squatter's password stops working and the squatter's sessions are revoked. Because every signed-in route outside `/api/auth` needs a verified email, an unverified account holds no app data or access, so handing it to the proven owner loses nothing.
- Unverified accounts can sign in but reach only `/api/auth/*`. Broadcasting channel auth goes only through `/api/chat/auth`, `/api/rooms/socket-auth` and `/api/notifications/socket-auth`, which are in the verified group; there is no `Broadcast::routes()` (`/broadcasting/auth` is a 404), so an unverified account cannot subscribe to any private channel.
- Changing the sign-in email only takes effect once the signed link mailed to the new address is opened; until then the old address keeps working for sign-in and password resets. The old address is told when a change is requested (with a signed link that cancels it without signing in) and when it happens, and confirming revokes every refresh token (see [Changing the sign-in email](#changing-the-sign-in-email)).
- A stolen session cannot finish an email change on its own. Accounts with a password must type it. Accounts still on the social placeholder have none, so their current address has to approve the change before the new address receives anything, and the confirmation route refuses new-address links until that approval is recorded. The same applies to a first password set in Settings without a current one (which a stolen session could do) until the owner proves the inbox by a password reset through email or a completed email change (`password_set_without_proof_at`). The flag survives disconnecting the social login. Accounts that existed before the flag are backfilled failing closed (social accounts with a non-placeholder password, or every social account when the placeholder is not configured), so their next email change needs current-inbox approval once, or a password reset by email first. Because an earlier disconnect deleted the `social_accounts` row, the backfill also stamps accounts without one whose email was verified within 5 minutes of creation, as social sign-in does. This heuristic is approximate. It also stamps an email sign-up that clicked its verification link within 5 minutes, which costs that member one current-inbox approval. It misses an unverified email sign-up later claimed through social sign-in (verified at that moment, not at creation) that set a first password and then disconnected the social login before this change. An account whose verification time was moved by a completed email change is also missed, but that change already counts as inbox proof. It also misses accounts with no `email_verified_at`, which cannot reach the email-change routes anyway.
- Approving, applying and cancelling are compare-and-set updates bound to the exact request, so a request replaced or cancelled while a link is being opened cannot end up approved or applied, and two clicks at once mail the new address once.
- Email-change links are HMACs over the pending address, a per-request nonce and the password hash. Changing or resetting the password therefore voids any change an attacker started with a stolen session, even though the link needs no login; it also clears the pending change. Before this, the attacker's link stayed valid for up to an hour after the victim changed their password.
- Remaining trade-offs: (1) A stolen session on a social-only account can still set a first password. It is marked, so it does not help with email changes, but it gives the attacker password sign-in until the owner resets it, and the owner is not mailed about it (a "password set" notice would close the gap). (2) The cancel link and the approval link are opened by whoever reads the current inbox, so they are only as safe as that inbox, and an owner who approves without reading defeats the approval step. (3) The cancel link lives 7 days so it outlasts resends; it can only cancel the request it was mailed for. (4) Rotating `APP_KEY` voids every pending email-change link (members resend). (5) `GET /api/me` does not say beforehand that a marked account will go through the approval step, so the hint under the Settings form still promises a link to the new address; once the change is stored, the pending box shows the real next step (`pending_email_awaiting_current`).
- Registration ignores any `role` in the request, so every new user is a `user`. Admins and moderators are set in the database or by an admin through `PATCH /api/admin/users/{user}`.

## Known follow-ups

- Add a captcha to login, register and forgot-password (there is a `TODO` in `AuthController::login`).
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
