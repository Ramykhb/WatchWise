# Backend Overview

What each module does. Paths are relative to `backend/`. The backend is split into modules under `app/`: `Auth`, `Genre`, `Movie`, `Gemini`, `Groq`, `Weather` and `MovieRecommendation`. Everything up to "Tests" describes the Auth module, and each other module has its own section after it.

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
- [Admin module](#admin-module)
- [Gemini module](#gemini-module)
- [Groq module](#groq-module)
- [Weather module](#weather-module)
- [MovieRecommendation module](#movierecommendation-module)
- [API documentation (Swagger UI)](#api-documentation-swagger-ui)
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
| `routes/api.php` | `/api/auth` register, login, refresh, logout and `me`, with rate limits. It also holds the routes of the other modules (TMDB, weather, weather mood and movie recommendations) |
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
| `app/Providers/AppServiceProvider.php` | Empty on purpose. Every module registers its own bindings, listeners and rate limiters in its own provider |

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
| `TMDB_API_KEY` | Needed by the Genre and Movie modules. `TMDB_URL` and `TMDB_IMAGE_URL` have defaults |
| `GROQ_API_KEY` | Needed by weather mood and movie recommendations. `GROQ_MODEL` defaults to `openai/gpt-oss-20b`; `GROQ_API_URL` has a default |
| `GEMINI_API_KEY`, `GEMINI_MODEL` | Optional for the standalone Gemini client. `GEMINI_URL` has a default |
| `OPEN_METEO_*` | Optional. The URL, default location and timezone have defaults in `config/services.php` |

Then run `composer install`, `php artisan key:generate` and `php artisan migrate`.

## Rate limits

Every limiter is a named limiter defined in the provider of the module it protects, and routes use it with `throttle:<name>`. The plain `throttle:N,M` middleware is avoided because it shares one bucket per IP across routes.

| Limiter | Defined in | Per minute | Used by |
| --- | --- | --- | --- |
| `auth-register`, `auth-login`, `auth-reset-password` | `AuthModuleServiceProvider` | 5 | the matching auth route |
| `auth-refresh` | `AuthModuleServiceProvider` | 30 | `POST /api/auth/refresh` |
| `auth-verify-email` | `AuthModuleServiceProvider` | 20 | the verification link |
| `auth-resend-verification`, `auth-forgot-password` | `AuthModuleServiceProvider` | 3 | the matching auth route |
| `ai` | `GroqModuleServiceProvider` | 10 | `POST /api/weather-mood`, `GET /api/movie-recommendations` (both call paid AI APIs) |
| `weather` | `WeatherModuleServiceProvider` | 30 | `GET /api/weather` |
| `tmdb` | `AppServiceProvider` | 60 | the five TMDB routes |

Each auth limiter is checked in two buckets: one per IP, and one per email when the request contains an email. Changing the email therefore cannot reset the IP bucket. The other limiters count per IP.

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
- `app/Movie/FormRequests/`: `MovieListRequest`, `MovieSearchRequest`, `MovieDiscoverRequest`.

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

Most policies stay in `app/Policies/` because each belongs to the entity it protects. The Movie List, Movie Night, Watchlist and User policies live in their own module folders (`app/MovieList/Policies/`, `app/MovieNight/Policies/`, `app/Watchlist/Policies/`, `app/User/Policies/`):

| Policy | Rule |
| --- | --- |
| `ReviewPolicy` | Owner-based access |
| `UserPreferencePolicy` (`app/User/Policies/`) | Owner-based access |
| `WatchlistPolicy` (`app/Watchlist/Policies/`) | Owner-based access |
| `MovieListPolicy`, `MovieListItemPolicy` (`app/MovieList/Policies/`) | Private lists are visible to their owner only |
| `MovieNightPolicy` (`app/MovieNight/Policies/`) | Organizer and invitees can view a night; only the organizer can change it |
| `MessagePolicy` | Authenticated users can read and post in discussion rooms; author edits, author or admin deletes |
| `NotificationPolicy` | Access to notifications |
| `UserPolicy` (`app/User/Policies/`) | Private profiles are visible to the owner and admins |

## Wiring

| File | Purpose |
| --- | --- |
| `bootstrap/app.php` | Registers the API routes and the four middleware aliases |
| `bootstrap/providers.php` | Registers `AppServiceProvider`, `AuthorizationServiceProvider` and the module service providers, including `GroqModuleServiceProvider` |

## Tests

| Location | Covers |
| --- | --- |
| `tests/Feature/Auth/AuthenticationTest.php` | Register, login, refresh, logout, throttling |
| `tests/Feature/Auth/AccountFlowsTest.php` | Email verification, password reset, social login |
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
  FormRequests/         MovieListRequest, MovieSearchRequest, MovieDiscoverRequest
  InterfaceServices/    MovieServiceInterface
  Services/             MovieService
  InterfaceRepository/  MovieRepositoryInterface
  Repository/           MovieRepository
  DTOs/Requests/        MovieListQuery, MovieSearchQuery, MovieDiscoverQuery
  DTOs/Responses/       MovieResponse, MoviePageResponse, MovieDetailsResponse (+ CastMemberResponse, PersonResponse, TrailerResponse)
  Exceptions/           MovieNotFound (renders 404 "Movie not found.")
  Providers/            MovieModuleServiceProvider (interface bindings)
app/Support/            Shared by both: TmdbHttpClient (adds the API key, timeout, wraps failures)
  Exceptions/           TmdbUnavailable (renders a fixed 502 so TMDB's own error never reaches the client), TmdbNotFound (TMDB answered 404)
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
- Tests: `tests/Feature/Movie/`, `tests/Feature/Genre/` and `tests/Feature/Support/` (the endpoints, repositories and `TmdbHttpClient`), and `tests/Unit/Movie/` and `tests/Unit/Genre/` (the services).

## MovieList and MovieNight modules

`app/MovieList/` and `app/MovieNight/` use the same layers as Auth. Their Eloquent models stay in `app/Models/`, and `MovieListResource` stays in `app/MovieList/Resources/` because the profile also uses it.

```
app/MovieList/
  Controllers/          MovieListController
  FormRequests/         MovieListIndexRequest, StoreMovieListRequest, UpdateMovieListRequest
  InterfaceServices/    MovieListServiceInterface
  Services/             MovieListService (transactions)
  InterfaceRepository/  MovieListRepositoryInterface
  Repository/           MovieListRepository (scopes, search, syncing films)
  DTOs/Requests/        MovieListSearchQuery, CreateMovieListData, UpdateMovieListData
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

## User module

`app/User/` holds everything about the person behind the account, in the same layers as Auth: their own account (`/api/me`), public profiles and following (`/api/users`) and the Home page (`/api/home`). The `User`, `UserPreference` and `Follow` models stay in `app/Models/`. Sign-in, registration and tokens stay in the Auth module.

```
app/User/
  Controllers/          UserController (account under /me, profiles and following under /users, and /home)
  FormRequests/         UpdateProfileRequest, UpdatePreferencesRequest, UpdatePasswordRequest, UploadAvatarRequest, DeleteAccountRequest, UserSearchRequest
  InterfaceServices/    AccountServiceInterface, ProfileServiceInterface, HomeServiceInterface
  Services/             AccountService (settings, password, avatar, delete), ProfileService (search, profile page, follow), HomeService, ProfilePrivacy (what a viewer may see of a profile)
  InterfaceRepository/  AccountRepositoryInterface, ProfileRepositoryInterface, HomeRepositoryInterface
  Repository/           AccountRepository, ProfileRepository, HomeRepository
  DTOs/Requests/        UpdateProfileData, UpdatePreferencesData, ChangePasswordData, DeleteAccountData, UserSearchQuery
  DTOs/Responses/       AccountResponse, ProfileResponse, UserSearchResponse, FollowResponse, HomeResponse
  Resources/            MemberResource (the small public card of a user, used by many modules)
  Exceptions/           IncorrectPassword (422 on the wrong field), ProfileSectionHidden (403)
  Policies/             UserPolicy, UserPreferencePolicy
  Providers/            UserModuleServiceProvider (interface bindings)
```

- The routes did not change (`routes/social.php`).
- `ProfilePrivacy` decides what a viewer may see of a profile.
- Tests: `tests/Feature/Social/ProfileTest.php`, `PrivacyTest.php`, `SearchTest.php` and `AccountRulesTest.php`.

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
- `room_message` rows are read receipts, not notifications for the bell: `/api/me` leaves them out of `counts.unread_notifications`, and a future notifications feed must too.
- Tests: `tests/Feature/Room/RoomMessagingTest.php`, `tests/Feature/Room/RoomMembershipTest.php` (the permission matrix is in `tests/Feature/Auth/PolicyTest.php`).

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

- the notifications API (`/api/notifications`) and the `Notifier` that created notifications when someone followed, invited, voted or mentioned another user;
- the old discussion rooms API (`/api/rooms`), since rebuilt as the [Room module](#room-module);
- the activity feed (`ActivityFeedService`), so `/api/home` no longer returns `activity` and `/api/users/{username}` no longer returns `activity` or the `reviews` list (the `counts.reviews` number is still there).

The database tables, models and policies for notifications, rooms, messages, reviews and the activity feed are still in place (`app/Models/`, `app/Policies/`, the migrations), so no data was dropped. `/api/home` still returns the busiest rooms and the next movie night.

## Gemini module

`app/Gemini/` contains a standalone Google Gemini client. Movie recommendations use Groq.

```
app/Gemini/
  InterfaceServices/  GeminiServiceInterface   ask(string $prompt): array
  Services/           GeminiService            sends the prompt, asks for JSON and returns it as an array
  Exceptions/         GeminiUnavailable        thrown on any failure
  Providers/          GeminiModuleServiceProvider (binding and the `ai` rate limiter)
```

- Setup: set `GEMINI_API_KEY` and `GEMINI_MODEL` in `.env`. `GEMINI_URL` has a default in `config/services.php`. The key is sent in a header, not in the URL.
- `GeminiUnavailable` only carries the HTTP status (for example "Gemini request failed (500)") or a short reason, never Gemini's response body. Callers log it and show the client a fixed message.
- Requests time out after 60 seconds and are retried up to 3 times.

## Groq module

The chatbot extends this module. `GET /api/chat/connection` returns public socket settings, `POST /api/chat/auth` authorizes only the signed-in user's private chat channel, and `POST /api/chat/messages` sends the current message plus bounded, completed frontend conversation turns to Groq. Replies stream through private Reverb `chat.delta` events, with the complete answer returned by the HTTP request. No transcripts are persisted and no queue worker is needed; the frontend resets history on page refresh or sign-out.

After installing Composer dependencies, run `php artisan reverb:install --no-interaction` once to generate local Reverb credentials. In the local environment, `php artisan serve` starts Reverb automatically on port `18080` and stops it when the API server exits. Existing local `.env` files should set `REVERB_PORT=18080`; if `REVERB_SERVER_PORT` is set, update that to `18080` too. The browser reads connection settings from the API, so it does not need a copy of the socket key in its own environment file. In production, run Reverb as its own managed process, configure `REVERB_PUBLIC_HOST`, `REVERB_PUBLIC_PORT`, `REVERB_PUBLIC_SCHEME` and the allowed frontend hostname, and proxy secure WebSocket traffic to Reverb. The existing `GROQ_API_KEY` and `GROQ_MODEL` are reused.

`app/Groq/` contains the Groq chat-completions client used by Weather mood and movie recommendations. It sends the API key as a Bearer token and supports strict JSON Schema responses through `GroqStructuredOutputInterface`; the simpler `GroqServiceInterface` uses the weather mood schema.

- Setup: set `GROQ_API_KEY` in `.env`. `GROQ_MODEL` defaults to `openai/gpt-oss-20b` and `GROQ_API_URL` defaults to `https://api.groq.com/openai/v1`.
- `GroqUnavailable` contains only a short failure reason; the provider response body is never returned to clients.

## Weather module

`app/Weather/` reads the current weather from Open-Meteo and turns it into a movie mood.

```
app/Weather/
  Controllers/          WeatherController (show, mood)
  FormRequests/         WeatherRequest, WeatherMoodRequest
  InterfaceServices/    WeatherServiceInterface, WeatherMoodServiceInterface
  Services/             WeatherService, WeatherMoodService (builds the Groq prompt)
  InterfaceRepository/  WeatherRepositoryInterface
  Repository/           WeatherRepository (the only code that calls Open-Meteo)
  DTOs/Requests/        WeatherQuery, WeatherMoodQuery
  DTOs/Responses/       WeatherResponse, WeatherMoodResponse
  Exceptions/           WeatherUnavailable, WeatherMoodUnavailable (fixed 502 responses)
  Providers/            WeatherModuleServiceProvider (bindings and the `weather` limiter)
```

| Endpoint | Purpose |
| --- | --- |
| `GET /api/weather?latitude=&longitude=` | Current weather. Both coordinates are optional, but if one is sent the other is required. Without them, the default location (Beirut) is used and the response says `is_default: true` |
| `POST /api/weather-mood` | Body: `condition`, `temperature`, `humidity`, `wind`, `precipitation`. Returns `{"title": "WEATHER MOOD", "mood", "description"}` generated by Groq's `openai/gpt-oss-20b` model |

Setup: the Open-Meteo URL, default location and timezone have defaults in `config/services.php` and can be overridden with the `OPEN_METEO_*` variables.

## MovieRecommendation module

`app/MovieRecommendation/` retrieves real TMDB candidates in weather-appropriate genres, then asks Groq to select up to 10 that fit the current weather.

```
app/MovieRecommendation/
  Controllers/          RecommendationController (index)
  FormRequests/         RecommendationRequest
  InterfaceServices/    RecommendationServiceInterface
  Services/             RecommendationService (weather description, TMDB candidates, Groq selection)
  DTOs/Requests/        RecommendationQuery
  DTOs/Responses/       RecommendationResponse
  Exceptions/           RecommendationUnavailable (fixed 502 responses)
  Providers/            MovieRecommendationModuleServiceProvider
```

- `GET /api/movie-recommendations?latitude=&longitude=` returns up to 10 TMDB movie records with `id`, title, poster, rating, release date and a recommendation reason. The coordinates are validated like `/api/weather` and optional.
- It reuses `WeatherServiceInterface` for the weather, `MovieServiceInterface` for TMDB candidates and `GroqStructuredOutputInterface` for candidate selection. Groq can only select candidate IDs; unknown IDs are discarded. It has no HTTP code of its own.
- Errors are fixed messages: `{"error": "Could not get the weather"}` or `{"error": "Could not generate recommendations"}`, both with status 502. The real failure is logged.
- The `ai` rate limiter applies (10 a minute per IP).

## API documentation (Swagger UI)

A Swagger UI page is served by the backend so every API can be tried in the browser.

1. Run `php artisan serve`.
2. Open `http://127.0.0.1:8000/api/documentation`.
3. Log in with `POST /api/auth/login` using "Try it out". The browser keeps the HttpOnly cookies, so protected endpoints such as `GET /api/auth/me` then work without pasting a token.

How it is built (no extra package is needed):

| Part | What it does |
| --- | --- |
| `openapi/base.json` | The title, security schemes (the `access_token` cookie and a Bearer token) and shared schemas |
| `openapi/paths/*.json` | One file per module: `auth.json`, `tmdb.json`, `weather.json`, `recommendations.json` |
| `app/Docs/Services/OpenApiSpec.php` | Merges those files into one OpenAPI 3 document and fails on a duplicate path |
| `app/Docs/Controllers/DocumentationController.php` | Serves the page (`/api/documentation`) and the spec (`/api/documentation/openapi.json`) |
| `resources/views/docs/swagger.blade.php` | The page. Swagger UI 5.17.14 is loaded from a CDN |
| `tests/Feature/Docs/OpenApiCoverageTest.php` | Fails if an `/api` route is missing from the spec, or the spec lists a route that does not exist |

- The page and the spec only work when `APP_ENV` is `local` (or `testing`). In any other environment they return 404.
- When you add an endpoint, add it to the matching `openapi/paths/*.json` file. The coverage test fails until you do.
- Some endpoints need keys in `.env` to work for real: `TMDB_API_KEY` for the movie endpoints, `GROQ_API_KEY` for weather mood and movie recommendations, and SMTP or `MAIL_MAILER=log` for the account emails (with `log`, the verification link is in `storage/logs/laravel.log`).

## Security notes

- Passwords are hashed by the `User` model cast. Refresh tokens and reset tokens are stored hashed. The refresh token is rotated on every use, and presenting an already-used one revokes the whole session chain.
- Tokens travel in HttpOnly cookies (the access token is also accepted as a Bearer header).
- Social login refuses emails the provider has not verified. If an unverified local account already uses that email, its password is replaced and its sessions are revoked, so a pre-registered account cannot be taken over.
- Registration ignores any `role` in the request, so every new user is a `user`. Admins and moderators are set in the database or by an admin through `PATCH /api/admin/users/{user}`.

## Known follow-ups

- Add a captcha to login, register and forgot-password (there is a `TODO` in `AuthController::login`).
- GitHub emails are treated as verified without checking. Check the verified flag from GitHub's email list before launch.
- The real SMTP delivery and the Google and GitHub logins have only been tested with fakes and mocks.
- `MovieNightPolicy` runs a database query (`invites()->exists()`) inside the policy. Leave it until the movie night feature exists, then move that check into a repository.
- `MessagePolicy::view` follows the room: any logged-in user can read a public room's messages, only members and invitees a private room's.

## Adding a new entity

Follow the same layout, for example `app/Review/` with `Controllers/`, `FormRequests/`, `InterfaceServices/`, `Services/`, `InterfaceRepository/`, `Repository/`, and its own `ReviewModuleServiceProvider` registered in `bootstrap/providers.php`. Keep the Eloquent model in `app/Models`.
