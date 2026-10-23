# Backend Auth Overview

What each file in the auth module does. Paths are relative to `backend/`.

## Contents

- [Architecture](#architecture)
- [Folder layout](#folder-layout)
- [How a request flows](#how-a-request-flows)
- [Routes](#routes)
- [Config](#config)
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
  Requests/             Form request validation
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
| `routes/api.php` | `/api/auth` register, login, refresh, logout and `me`, with rate limits |
| `routes/auth_flows.php` | Email verification, password reset and Google/GitHub login routes |

## Config

| File | Purpose |
| --- | --- |
| `.env.example` | SMTP mail placeholders, `FRONTEND_URL`, Google and GitHub OAuth variables, JWT settings |
| `config/jwt.php` | Signing secret, issuer, access lifetime (15 min), refresh lifetime (30 days), cookie names |
| `config/cors.php` | CORS rules for the frontend |
| `config/services.php` | Google and GitHub OAuth credentials, frontend URL |
| `phpunit.xml` | Test-only `JWT_SECRET` and `FRONTEND_URL` |
| `app/Providers/AppServiceProvider.php` | Per-route rate limiters (IP bucket plus per-email bucket) and the `Registered` listener |

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

In `app/Auth/Requests/`. Each validates the matching endpoint:
`RegisterRequest`, `LoginRequest`, `ForgotPasswordRequest`, `ResetPasswordRequest`, `ResendVerificationRequest`.

## Email

| File | Purpose |
| --- | --- |
| `Listeners/SendEmailVerification.php` | Sends the verification link when a user registers |
| `Mail/VerifyEmailMail.php` | The verification email |
| `Mail/ResetPasswordMail.php` | The password reset email |
| `resources/views/mail/verify-email.blade.php` | Template for the verification email |
| `resources/views/mail/reset-password.blade.php` | Template for the reset email |

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

## Adding a new entity

Follow the same layout, for example `app/Review/` with `Controllers/`, `Requests/`, `InterfaceServices/`, `Services/`, `InterfaceRepository/`, `Repository/`, and its own `ReviewModuleServiceProvider` registered in `bootstrap/providers.php`. Keep the Eloquent model in `app/Models`.
