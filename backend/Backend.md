# Backend Auth Overview

What each file in the auth work does. Paths are relative to `backend/`.

## Contents

- [How a request flows](#how-a-request-flows)
- [Routes](#routes)
- [Config](#config)
- [Tokens and current user](#tokens-and-current-user)
- [Controllers](#controllers)
- [Middleware](#middleware)
- [Form requests](#form-requests)
- [Email](#email)
- [Authorization](#authorization)
- [Refresh tokens (database)](#refresh-tokens-database)
- [Wiring](#wiring)
- [Tests](#tests)

## How a request flows

1. A request hits a route in `routes/api.php` or `routes/auth_flows.php`.
2. A middleware (`auth.jwt`, `auth.optional`, `verified.email` or `role`) asks `CurrentUserFactory` who is calling.
3. The factory reads the access token cookie and returns a `JwtCurrentUser`, or a `GuestUser` if the token is missing or invalid.
4. The controller runs. Policies receive the `CurrentUser` to decide what it may do.

## Routes

| File | Purpose |
| --- | --- |
| `routes/api.php` | `/api/auth` register, login, refresh, logout and `me`, with rate limits |
| `routes/auth_flows.php` | Email verification, password reset and Google/GitHub login routes |

## Config

| File | Purpose |
| --- | --- |
| `.env.example` | SMTP mail with `CHANGE_ME` placeholders, `FRONTEND_URL`, Google and GitHub OAuth variables |
| `config/jwt.php` | Signing secret, issuer, access lifetime (15 min), refresh lifetime (30 days), cookie names |
| `config/cors.php` | CORS rules for the frontend |
| `config/services.php` | Google and GitHub OAuth credentials, frontend URL |
| `phpunit.xml` | Test-only `JWT_SECRET` and `FRONTEND_URL` |

## Tokens and current user

All in `app/Auth/` unless noted.

| File | Purpose |
| --- | --- |
| `TokenFactory.php` | Issues HS256 access tokens and refresh tokens, parses access tokens |
| `TokenPair.php` | Holds a user's access and refresh tokens with their expiry times |
| `AuthCookies.php` | Sets and clears the httpOnly access and refresh cookies |
| `CurrentUserFactory.php` | Resolves the current user once per request, or a guest |
| `JwtCurrentUser.php` | The logged-in user. The role is read from the database, so a demotion applies immediately |
| `GuestUser.php` | The anonymous user, never authenticated |
| `Exceptions/InvalidRefreshToken.php` | Thrown when a refresh token is bad |
| `app/Contracts/CurrentUser.php` | Interface that policies and middleware depend on |
| `app/Exceptions/UnverifiedProviderEmail.php` | Thrown when a social provider returns an unverified email |

## Controllers

All in `app/Http/Controllers/`.

| File | Purpose |
| --- | --- |
| `Auth/AuthController.php` | Register, login, refresh, logout and `me` |
| `EmailVerificationController.php` | Verifies the emailed link and resends it |
| `PasswordResetController.php` | Forgot-password and reset-password |
| `SocialAuthController.php` | Google and GitHub login redirect and callback |

## Middleware

All in `app/Http/Middleware/`.

| Alias | File | Behavior |
| --- | --- | --- |
| `auth.jwt` | `AuthenticateJwt.php` | Requires a logged-in user, otherwise 401 |
| `auth.optional` | `AuthenticateOptional.php` | Lets anonymous requests through as a guest |
| `verified.email` | `EnsureEmailVerified.php` | 403 if the email is unverified |
| `role` | `EnsureRole.php` | 403 unless the user has one of the required roles |

## Form requests

In `app/Http/Requests/Auth/`. Each validates the matching endpoint:
`RegisterRequest`, `LoginRequest`, `ForgotPasswordRequest`, `ResetPasswordRequest`, `ResendVerificationRequest`.

## Email

| File | Purpose |
| --- | --- |
| `app/Services/EmailVerificationService.php` | Sends a signed verification link that expires in 60 minutes |
| `app/Listeners/SendEmailVerification.php` | Sends that link when a user registers |
| `app/Mail/VerifyEmailMail.php` | The verification email |
| `app/Mail/ResetPasswordMail.php` | The password reset email |
| `resources/views/mail/verify-email.blade.php` | Template for the verification email |
| `resources/views/mail/reset-password.blade.php` | Template for the reset email |

## Authorization

| File | Purpose |
| --- | --- |
| `app/Providers/AuthorizationServiceProvider.php` | Maps models to policies, binds the guest as the default current user, makes Gate use `CurrentUser` |

Policies in `app/Policies/`:

| Policy | Rule |
| --- | --- |
| `ReviewPolicy`, `WatchlistPolicy`, `UserPreferencePolicy` | Owner-based access |
| `MovieListPolicy`, `MovieListItemPolicy` | Private lists are visible to their owner only |
| `MovieNightPolicy` | Organizer and invitees can view a night; only the organizer can change it |
| `MessagePolicy` | Authenticated users can read and post in discussion rooms; author edits, author or admin deletes |
| `NotificationPolicy` | Access to notifications |
| `UserPolicy` | Private profiles are visible to the owner and admins |

## Refresh tokens (database)

| File | Purpose |
| --- | --- |
| `app/Models/RefreshToken.php` | The stored refresh token row |
| `database/migrations/…create_refresh_tokens_table.php` | The refresh tokens table |
| `database/factories/RefreshTokenFactory.php` | Test factory |
| `database/factories/UserFactory.php` | Adds `role`, `profile_visibility`, `is_active` defaults and `admin()` / `private()` states |

## Wiring

| File | Purpose |
| --- | --- |
| `bootstrap/app.php` | Registers the API routes and the four middleware aliases |
| `bootstrap/providers.php` | Registers `AuthorizationServiceProvider` |

## Tests

In `tests/Feature/Auth/`.

| File | Covers |
| --- | --- |
| `AuthenticationTest.php` | Register, login, refresh, logout |
| `AccountFlowsTest.php` | Email verification, password reset |
| `MiddlewareTest.php` | The four middleware |
| `PolicyTest.php` | The policy rules, including messages |
