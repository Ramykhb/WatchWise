# Backend Overview

What each file in the Auth and Tmdb modules does. Paths are relative to `backend/`. Everything up to "Tests" describes the Auth module, and the Tmdb module has its own section.

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
- [Tmdb module](#tmdb-module)
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
- Interfaces are bound to implementations in `app/Auth/Providers/AuthModuleServiceProvider.php`.

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
  Support/              AuthCookies, TokenPair
  Mail/                 Verification and reset emails
  Listeners/            Sends the verification email on registration
  Exceptions/           Domain exceptions the controllers map to responses
  Providers/            AuthModuleServiceProvider (interface bindings)
```

## How a request flows

1. A request hits a route in `routes/api.php` or `routes/auth_flows.php`.
2. A middleware (`auth.jwt`, `auth.optional`, `verified.email` or `role`) asks `CurrentUserFactory` who is calling.
3. The factory reads the access token (cookie or Bearer header) and returns a `JwtCurrentUser`, or a `GuestUser` if the token is missing or invalid.
4. The controller validates the input and calls a service through its interface.
5. The service applies the rules and uses repositories for data.
6. Policies receive the `CurrentUser` to decide what it may do.

## Routes

| File | Purpose |
| --- | --- |
| `routes/api.php` | `/api/auth` register, login, refresh, logout and `me`, with rate limits. It also holds the weather and movie-recommendation routes, which are not part of the auth module |
| `routes/auth_flows.php` | Email verification, password reset and Google/GitHub login routes |

All auth endpoints:

| Method and path | Auth | Purpose |
| --- | --- | --- |
| `POST /api/auth/register` | none | Create an account, send the verification email, set the token cookies |
| `POST /api/auth/login` | none | Log in and set the token cookies |
| `POST /api/auth/refresh` | refresh cookie | Rotate the refresh token and issue new tokens |
| `POST /api/auth/logout` | refresh cookie | Revoke the session and clear the cookies |
| `GET /api/auth/me` | `auth.jwt` | The current user |
| `GET /api/auth/verify-email/{id}/{hash}` | signed link | Verify the email, then redirect to the frontend |
| `POST /api/auth/resend-verification` | none | Resend the verification email |
| `POST /api/auth/forgot-password` | none | Send a reset link (same response whether or not the account exists) |
| `POST /api/auth/reset-password` | reset token | Set a new password and revoke all sessions |
| `GET /api/auth/social/{google\|github}` | none | Start the OAuth login |
| `GET /api/auth/social/{google\|github}/callback` | state cookie | Finish the OAuth login, then redirect to the frontend |

## Config

| File | Purpose |
| --- | --- |
| `.env.example` | SMTP mail placeholders, `FRONTEND_URL`, Google and GitHub OAuth variables, JWT settings |
| `config/jwt.php` | Signing secret, issuer, access lifetime (15 min), refresh lifetime (30 days), cookie names |
| `config/cors.php` | CORS rules for the frontend |
| `config/services.php` | Google and GitHub OAuth credentials, frontend URL |
| `phpunit.xml` | Test-only `JWT_SECRET` and `FRONTEND_URL` |
| `app/Providers/AppServiceProvider.php` | Per-route rate limiters (IP bucket plus per-email bucket) and the `Registered` listener |

## Setup

Copy `.env.example` to `.env` (it is gitignored) and set:

| Variable | Notes |
| --- | --- |
| `DB_*` | Database connection (MySQL) |
| `JWT_SECRET` | Required, at least 32 characters. Generate one with `php -r "echo bin2hex(random_bytes(32));"`. Without it, login and register return a 500 |
| `JWT_ACCESS_TTL`, `JWT_REFRESH_TTL_DAYS` | Token lifetimes, default 15 minutes and 30 days |
| `FRONTEND_URL` | Where emails link to and where the social login redirects. Also the CORS origin |
| `MAIL_*` | SMTP settings for the verification and reset emails. For local work, `MAIL_MAILER=log` writes emails to `storage/logs/laravel.log` instead of sending them |
| `GOOGLE_*`, `GITHUB_*` | OAuth client id, secret and redirect URI. The redirect URI must match the callback route, for example `http://localhost:8000/api/auth/social/google/callback` |

Then run `composer install`, `php artisan key:generate` and `php artisan migrate`.

## Rate limits

Defined in `app/Providers/AppServiceProvider.php`. Each route has its own named limiter. A request must pass two buckets: one per IP, and one per email when the request contains an email. Changing the email therefore cannot reset the IP bucket.

| Limiter | Per minute |
| --- | --- |
| `auth-register`, `auth-login`, `auth-reset-password` | 5 |
| `auth-refresh` | 30 |
| `auth-verify-email` | 20 |
| `auth-resend-verification`, `auth-forgot-password` | 3 |
| `tmdb` (all movie and genre routes, per IP only) | 60 |

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
| `SocialAuthService` | Google/GitHub login. Refuses unverified provider emails and takes over unverified local accounts by resetting their password |

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
| `SocialAuthController.php` | Google and GitHub redirect and callback (state cookie check, redirects) |

## Middleware

All in `app/Auth/Middleware/`.

| Alias | File | Behavior |
| --- | --- | --- |
| `auth.jwt` | `AuthenticateJwt.php` | Requires a logged-in user, otherwise 401 |
| `auth.optional` | `AuthenticateOptional.php` | Lets anonymous requests through as a guest |
| `verified.email` | `EnsureEmailVerified.php` | 403 if the email is unverified |
| `role` | `EnsureRole.php` | 403 unless the user has one of the required roles |

## Form requests

Each module keeps its Laravel form requests in a `FormRequests/` folder:

- `app/Auth/FormRequests/`: `RegisterRequest`, `LoginRequest`, `ForgotPasswordRequest`, `ResetPasswordRequest`, `ResendVerificationRequest`.
- `app/Tmdb/FormRequests/`: `MovieListRequest`, `MovieSearchRequest`, `MovieDiscoverRequest`.

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
| `Listeners/SendEmailVerification.php` | Sends the verification link when a user registers. Registered in `AppServiceProvider` |
| `Mail/VerifyEmailMail.php` | The verification email |
| `Mail/ResetPasswordMail.php` | The password reset email |
| `resources/views/mail/verify-email.blade.php` | Template for the verification email |
| `resources/views/mail/reset-password.blade.php` | Template for the reset email |

Emails are sent directly, not queued, so no queue worker is needed. The work for an existing account (creating the token, hashing it, sending the mail) runs after the response is sent using `defer()`. This keeps the response time the same for known and unknown emails, and a broken mail server is logged without failing the request.

## Exceptions

In `app/Auth/Exceptions/`. Services throw them and controllers turn them into responses.

`InvalidCredentials`, `InvalidRefreshToken`, `InvalidResetToken`, `UnverifiedProviderEmail`, `EmailRequired`, `AccountDisabled`.

## Authorization

| File | Purpose |
| --- | --- |
| `app/Providers/AuthorizationServiceProvider.php` | Maps models to policies, binds the guest as the default current user, makes the Gate use `CurrentUser` |

Policies stay in `app/Policies/` because each belongs to the entity it protects:

| Policy | Rule |
| --- | --- |
| `ReviewPolicy`, `WatchlistPolicy`, `UserPreferencePolicy` | Owner-based access |
| `MovieListPolicy`, `MovieListItemPolicy` | Private lists are visible to their owner only |
| `MovieNightPolicy` | Organizer and invitees can view a night; only the organizer can change it |
| `MessagePolicy` | Authenticated users can read and post in discussion rooms; author edits, author or admin deletes |
| `NotificationPolicy` | Access to notifications |
| `UserPolicy` | Private profiles are visible to the owner and admins |

## Wiring

| File | Purpose |
| --- | --- |
| `bootstrap/app.php` | Registers the API routes and the four middleware aliases |
| `bootstrap/providers.php` | Registers `AppServiceProvider`, `AuthorizationServiceProvider` and `AuthModuleServiceProvider` |

## Tests

| Location | Covers |
| --- | --- |
| `tests/Feature/Auth/AuthenticationTest.php` | Register, login, refresh, logout, throttling |
| `tests/Feature/Auth/AccountFlowsTest.php` | Email verification, password reset, social login |
| `tests/Feature/Auth/MiddlewareTest.php` | The four middleware |
| `tests/Feature/Auth/PolicyTest.php` | The policy rules, including messages |
| `tests/Feature/Auth/Repository/` | Each repository against the database |
| `tests/Unit/Auth/` | Each service with fake repositories, and `UserResponse` matching the model's JSON |

## Tmdb module

`app/Tmdb/` uses the same layers as Auth. TMDB has no database, so the repositories call the TMDB web API instead of Eloquent.

```
app/Tmdb/
  Controllers/          MovieController, GenreController
  FormRequests/         MovieListRequest, MovieSearchRequest, MovieDiscoverRequest
  InterfaceServices/    MovieServiceInterface, GenreServiceInterface
  Services/             MovieService, GenreService (maps TMDB data to DTOs, caches genres for a day)
  InterfaceRepository/  MovieRepositoryInterface, GenreRepositoryInterface
  Repository/           MovieRepository, GenreRepository (the only code that talks to TMDB)
  DTOs/Requests/        MovieListQuery, MovieSearchQuery, MovieDiscoverQuery
  DTOs/Responses/       MovieResponse, MoviePageResponse, GenreResponse, MovieDetailsResponse (+ CastMemberResponse, PersonResponse, TrailerResponse)
  Support/              TmdbHttpClient (adds the API key, timeout, wraps failures)
  Exceptions/           TmdbUnavailable (renders a fixed 502 so TMDB's own error never reaches the client), TmdbNotFound (TMDB answered 404), MovieNotFound (renders 404 "Movie not found.")
  Providers/            TmdbModuleServiceProvider (interface bindings)
```

| Endpoint | Purpose |
| --- | --- |
| `GET /api/movies?list=&page=` | A page of `popular` (default), `top_rated`, `now_playing` or `upcoming` movies |
| `GET /api/movies/search?q=&page=&year=` | Search movies by title. `q` is required (max 100 characters) |
| `GET /api/movies/discover` | Filtered browsing: `genres`, `year_from`, `year_to`, `min_rating` (0 to 5), `runtime_min`, `runtime_max`, `language`, `sort` (`popularity`, `rating`, `release_date`, `title`), `page` |
| `GET /api/movies/{id}` | One movie with genres, US certification, runtime, original language, directors, top 12 cast, a YouTube trailer, budget, box office and 10 recommended movies (fetched in one TMDB call). Unknown ids return 404 |
| `GET /api/genres` | The movie genres (id and name), cached for a day |

- All five are public. They share the `tmdb` rate limit: 60 requests per minute per IP (see [Rate limits](#rate-limits)), so one client cannot use up the TMDB quota.
- Movie details are cached for 6 hours per movie (`tmdb.movie.{id}`); unknown movies and TMDB failures are not cached.
- Movie lists are paginated with `page` from 1 to 500 and 20 movies per page. Responses include `page`, `total_pages` (capped at 500, TMDB's limit) and `total_results`. Genres are a short fixed list, so they are not paginated.
- Ratings are converted from TMDB's 0 to 10 scale to 0 to 5. Adult content is always excluded.
- Setup: set `TMDB_API_KEY` in `.env`. `TMDB_URL` and `TMDB_IMAGE_URL` have working defaults in `config/services.php`.
- Routes are in `routes/api.php` and the config is the `tmdb` entry in `config/services.php`.
- Tests: `tests/Feature/Tmdb/` (the endpoints and `Repository/`) and `tests/Unit/Tmdb/` (the services).

## Security notes

- Passwords are hashed by the `User` model cast. Refresh tokens and reset tokens are stored hashed. The refresh token is rotated on every use, and presenting an already-used one revokes the whole session chain.
- Tokens travel in HttpOnly cookies (the access token is also accepted as a Bearer header).
- Social login refuses emails the provider has not verified. If an unverified local account already uses that email, its password is replaced and its sessions are revoked, so a pre-registered account cannot be taken over.
- Registration ignores any `role` in the request, so every new user is a `user`. Admins are set in the database.

## Known follow-ups

- Add a captcha to login, register and forgot-password (there is a `TODO` in `AuthController::login`).
- GitHub emails are treated as verified without checking. Check the verified flag from GitHub's email list before launch.
- The real SMTP delivery and the Google and GitHub logins have only been tested with fakes and mocks.
- `MessagePolicy::view` lets any logged-in user read any message. This is fine while discussion rooms are public per movie, and needs a membership check if private rooms are added.

## Adding a new entity

Follow the same layout, for example `app/Review/` with `Controllers/`, `FormRequests/`, `InterfaceServices/`, `Services/`, `InterfaceRepository/`, `Repository/`, and its own `ReviewModuleServiceProvider` registered in `bootstrap/providers.php`. Keep the Eloquent model in `app/Models`.
