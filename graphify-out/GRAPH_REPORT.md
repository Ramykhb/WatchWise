# Graph Report - Web2-Project  (2026-10-24)

## Corpus Check
- 279 files · ~89,843 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 74 file(s) not represented in the graph (top: .css 50, (none) 20, .example 2)

## Summary
- 2185 nodes · 5183 edges · 136 communities (85 shown, 51 thin omitted)
- Extraction: 95% EXTRACTED · 5% INFERRED · 0% AMBIGUOUS · INFERRED: 271 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `18c30403`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- ForgotPasswordData
- MovieDetails
- AccountFlowsTest.php
- Settings
- Tests: AccountFlowsTest
- User
- PosterCardMini: SignUp
- UserRepositoryInterface
- AuthService #8
- Auth: RefreshToken
- Cinemas
- Rooms.tsx
- adminData
- AdminMovies
- Discover
- Profile.tsx
- AdminOverview
- router.tsx
- CurrentUser
- Illuminate\Http\Request
- AdminUsers
- react
- AuthResponse
- ButtonPrimary: react-router
- ForYou
- Home.tsx
- MovieNights.tsx
- Tests: GuestUser
- AuthController.php
- YearInFilmCard: TrendingCard
- Watchlist.tsx
- Message
- Notifications.tsx
- ProfileController.php
- SocialAccountRepository
- AuthShell: react
- compilerOptions #36
- EmailVerificationServiceInterface
- Illuminate\Http\JsonResponse
- Illuminate\Database\Eloquent\Relations\BelongsTo
- frontend/package
- SignIn.tsx
- ListDialog.tsx
- Reactbits: Magnet
- compilerOptions #44
- npm: package
- ResetPasswordData
- Auth: TokenFactory
- npm: backend/package
- Tests: AuthenticationTest
- AdminLayout
- RuntimeException
- Reactbits: SplitHeadline
- UserFactory
- Illuminate\Database\Schema\Blueprint
- ProfileTest
- Landing
- .loginWithProvider
- PasswordResetRepository
- ContentTest
- MovieNight
- 0001_01_01_000000_create_users_table.php
- devDependencies
- UserResponse
- TestCase
- 0001_01_01_000002_create_jobs_table.php
- 2026_10_24_000001_add_profile_fields_to_users_table.php
- Backend CI Workflow
- .id
- Controller
- AuthService #70
- AuthProvider / useAuth (AuthContext
- Laravel Boost Guidelines
- Tests: MiddlewareTest
- Docs: Frontend Stack (React 19, Vite 8, CSS Mo
- composer
- Composer: config
- Composer: scripts
- Tests: WeatherTest
- WatchWise Handoff
- Policy: owns
- Backend Auth Module (app/Auth)
- TokenFactory
- Composer: require-dev
- dependencies
- PillButton
- MovieListItem
- Auth Middleware (auth.jwt, auth.optional
- Docs: Laravel 12 Backend
- Composer: require
- npm: devDependencies
- SocialAuthController #100
- Composer: psr-4
- Migration: 0001_01_01_000001_create_cache_table
- Illuminate\Database\Migrations\Migration
- Migration
- Tests: GeminiWeatherRecommendation
- scripts
- Checkbox
- Docs: Auth Page Swap (View Transitions
- Migration: 2026_10_22_000003_create_social_accounts
- Migration: 2026_10_22_000004_create_reviews_table
- Illuminate\Support\Facades\Schema
- Migration: 2026_10_22_000006_create_user_preference
- Migration: 2026_10_22_000008_create_messages_table
- Migration: 2026_10_22_000011_create_follows_table
- Migration: 2026_10_22_000012_create_activity_feed_t
- Migration: 2026_10_22_000013_create_movie_nights_ta
- Migration: 2026_10_22_000014_create_movie_night_inv
- Migration: 2026_10_22_000015_create_movie_night_can
- Migration: 2026_10_22_000017_create_calendar_events
- Migration: 2026_10_22_000018_create_notifications_t
- Migration: 2026_10_22_000019_create_refresh_tokens_
- Composer: autoload-dev
- Composer: extra
- laravel-boost
- tsconfig
- vite.config
- Docs: In-memory Stores (myListsStore, nightsSt
- Google Fonts (DM Sans, Outfit
- Docs: Working Agreements (user commits/pushes,
- Docs: Movie Nights (invitations, voting, RSVP
- Docs: FitToViewport

## God Nodes (most connected - your core abstractions)
1. `User` - 135 edges
2. `CurrentUser` - 123 edges
3. `react` - 50 edges
4. `AppNav()` - 39 edges
5. `react-router` - 38 edges
6. `SplitHeadline()` - 38 edges
7. `gradientCss()` - 36 edges
8. `useEntranceAnimation()` - 35 edges
9. `TestCase` - 34 edges
10. `MovieNight` - 31 edges

## Surprising Connections (you probably didn't know these)
- `RequireAuth / RequireGuest Route Guards` --semantically_similar_to--> `Auth Middleware (auth.jwt, auth.optional, verified.email, role)`  [INFERRED] [semantically similar]
  HANDOFF.md → backend/Backend.md
- `TypeScript Check and Build job (npm ci, tsc --noEmit, npm run build)` --conceptually_related_to--> `Frontend Stack (React 19, Vite 8, CSS Modules)`  [INFERRED]
  .github/workflows/frontend-ci.yml → HANDOFF.md
- `Roadmap` --conceptually_related_to--> `Backend Auth Module (app/Auth)`  [AMBIGUOUS]
  README.md → backend/Backend.md
- `AuthProvider / useAuth (AuthContext)` --references--> `AuthController`  [INFERRED]
  HANDOFF.md → backend/Backend.md
- `Laravel Checks job (PHP 8.4, composer install, artisan --version)` --references--> `Laravel Framework (default README)`  [INFERRED]
  .github/workflows/backend-ci.yml → backend/README.md

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Auth Repository Layer** — backend_backend_userrepository, backend_backend_refreshtokenrepository, backend_backend_passwordresetrepository, backend_backend_socialaccountrepository [EXTRACTED 1.00]
- **JWT Auth Request Flow** — backend_backend_auth_middleware, backend_backend_currentuserfactory, backend_backend_currentuser, backend_backend_policies, backend_backend_tokenfactory [EXTRACTED 1.00]
- **Backend agent coding rules (Laravel Boost)** — backend_claude_php_rules, backend_claude_laravel_way, backend_claude_laravel12_structure, backend_claude_pint, backend_claude_phpunit [EXTRACTED 1.00]
- **Frontend Cookie Session Auth Flow** — handoff_authprovider, handoff_route_guards, backend_backend_authcontroller, backend_backend_authcookies [INFERRED 0.85]

## Communities (136 total, 51 thin omitted)

### Community 0 - "ForgotPasswordData"
Cohesion: 0.15
Nodes (5): PasswordResetController, ForgotPasswordData, InvalidResetToken, PasswordResetServiceInterface, ForgotPasswordRequest

### Community 1 - "MovieDetails"
Cohesion: 0.05
Nodes (22): RatingStars(), RatingStarsProps, Review, ReviewCard(), Sentiment(), SentimentType, VARIANTS, AdminReviews() (+14 more)

### Community 2 - "AccountFlowsTest.php"
Cohesion: 0.11
Nodes (3): PasswordResetRepositoryInterface, {closure#2}(), PasswordReset

### Community 3 - "Settings"
Cohesion: 0.07
Nodes (18): PROVIDERS, SocialButtonProps, SocialProvider, backendUrl, fieldErrors(), INTEGRATION_ICONS, maskIcon(), MENU (+10 more)

### Community 4 - "Tests: AccountFlowsTest"
Cohesion: 0.10
Nodes (4): ResetPasswordMail, VerifyEmailMail, AccountFlowsTest, {closure#3}()

### Community 5 - "User"
Cohesion: 0.05
Nodes (11): JwtCurrentUser, UserRepository, {closure#4}(), ProfileController, {closure#1}(), User, {closure#2}(), {closure#3}() (+3 more)

### Community 6 - "PosterCardMini: SignUp"
Cohesion: 0.13
Nodes (7): MiniPoster, PosterCardMiniProps, LAST_LAP, NEON_TIDE, PAPER_MOON, Errors, PROVIDERS

### Community 7 - "UserRepositoryInterface"
Cohesion: 0.11
Nodes (12): SocialLoginData, AccountDisabled, UnverifiedProviderEmail, RefreshTokenRepositoryInterface, SocialAccountRepositoryInterface, UserRepositoryInterface, SocialAuthServiceInterface, {closure#3}() (+4 more)

### Community 8 - "AuthService #8"
Cohesion: 0.27
Nodes (3): LoginData, AuthService, AuthServiceTest

### Community 9 - "Auth: RefreshToken"
Cohesion: 0.17
Nodes (4): RefreshTokenRepository, RefreshToken, {closure#1}(), RefreshTokenRepositoryTest

### Community 10 - "Cinemas"
Cohesion: 0.08
Nodes (14): directionsUrl(), Mode, placeAt(), Cinema, CINEMAS, DEFAULT_LOCATION, Feature, FEATURES (+6 more)

### Community 11 - "Rooms.tsx"
Cohesion: 0.08
Nodes (22): apiErrorMessage(), ApiActivity, ApiHome, ApiMe, ApiMember, ApiMessage, ApiNotification, ApiReview (+14 more)

### Community 12 - "adminData"
Cohesion: 0.09
Nodes (19): AdminMovie, FLAG_REASONS, FlagReason, GUIDELINES, MODERATION_REVIEWS, MODERATION_WEEK, ModerationReview, ModerationStatus (+11 more)

### Community 13 - "AdminMovies"
Cohesion: 0.09
Nodes (17): Toggle(), ToggleProps, ADMIN_MOVIES, MOVIE_SYNC, MovieFields, AdminMovies(), CONTENT_KEYS, EditorProps (+9 more)

### Community 14 - "Discover"
Cohesion: 0.10
Nodes (14): fraction(), pageItems(), DEFAULT_FILTERS, Filters, GENRES, INITIAL_FILTERS, LANGUAGES, RESULTS (+6 more)

### Community 15 - "Profile.tsx"
Cohesion: 0.09
Nodes (34): Movie, AVATAR_COLORS, longDate(), look(), shortName(), updatedLabel(), ApiList, ApiProfile (+26 more)

### Community 16 - "AdminOverview"
Cohesion: 0.09
Nodes (14): StatCard(), StatCardProps, ACTIVITY_LABELS, HIGHEST_RATED, MOST_WATCHED, POPULAR_GENRES, RANGES, RankedMovie (+6 more)

### Community 17 - "router.tsx"
Cohesion: 0.07
Nodes (30): LINKS, Logo(), LogoProps, gradientCss(), PosterCollage(), PosterCollageProps, RequireAuth(), RequireGuest() (+22 more)

### Community 18 - "CurrentUser"
Cohesion: 0.07
Nodes (12): CurrentUser, {closure#1}(), WatchlistController, Review, UserPreference, Watchlist, MessagePolicy, ReviewPolicy (+4 more)

### Community 19 - "Illuminate\Http\Request"
Cohesion: 0.06
Nodes (16): CurrentUserFactory, AuthenticateJwt, AuthenticateOptional, EnsureEmailVerified, EnsureRole, AuthModuleServiceProvider, MovieRecommendationController, WeatherController (+8 more)

### Community 20 - "AdminUsers"
Cohesion: 0.09
Nodes (13): AdminUserRow, USER_ROLES, USER_STATUSES, USER_TOTALS, UserRole, USERS, UserStatus, AdminUsers() (+5 more)

### Community 21 - "react"
Cohesion: 0.16
Nodes (13): AuthProvider(), AuthContext, AuthContextValue, RootLayout(), authApi, AuthPayload, AuthUser, backendUrl (+5 more)

### Community 23 - "ButtonPrimary: react-router"
Cohesion: 0.13
Nodes (5): ButtonProps, CommonProps, LinkProps, TextFieldProps, STEPS

### Community 24 - "ForYou"
Cohesion: 0.11
Nodes (10): SHELF_ICONS, weatherCondition(), WeatherMood, WeatherResponse, PROFILE, Shelf, SHELVES, TASTE (+2 more)

### Community 25 - "Home.tsx"
Cohesion: 0.06
Nodes (21): HeadlineLine, Part(), SplitHeadlineProps, greeting(), ACTIVITY, BECAUSE_YOU_LOVED, G, MOVIE_NIGHT (+13 more)

### Community 26 - "MovieNights.tsx"
Cohesion: 0.05
Nodes (43): ApiNight, ApiRsvp, FormErrors, HOW_IT_WORKS, maskIcon(), movieBySlug(), MovieNights(), NightCard() (+35 more)

### Community 27 - "Tests: GuestUser"
Cohesion: 0.20
Nodes (3): GuestUser, {closure#1}(), PolicyTest

### Community 28 - "AuthController.php"
Cohesion: 0.08
Nodes (7): AuthController, RegisterData, AuthServiceInterface, LoginRequest, RegisterRequest, ResetPasswordRequest, AuthCookies

### Community 29 - "YearInFilmCard: TrendingCard"
Cohesion: 0.11
Nodes (8): Chip(), TrendingCard(), TrendingCardProps, TrendingMovie, GENRES, Month, MONTHS, YearInFilmCard()

### Community 30 - "Watchlist.tsx"
Cohesion: 0.11
Nodes (35): AppNav(), ButtonPrimary(), MovieTile(), SplitHeadline(), useEntranceAnimation(), colorOf(), initialsOf(), movieFor() (+27 more)

### Community 31 - "Message"
Cohesion: 0.09
Nodes (10): {closure#1}(), {closure#1}(), {closure#4}(), RoomController, DiscussionRoom, Message, {closure#1}(), Notifier (+2 more)

### Community 32 - "Notifications.tsx"
Cohesion: 0.09
Nodes (31): PillButton(), api, ApiState, backendUrl, dayGroup(), timeAgo(), KIND_STYLE, maskIcon() (+23 more)

### Community 33 - "ProfileController.php"
Cohesion: 0.08
Nodes (9): HomeController, {closure#15}(), {closure#17}(), MemberResource, MovieListResource, NotificationResource, ReviewResource, ActivityFeedService (+1 more)

### Community 35 - "AuthShell: react"
Cohesion: 0.20
Nodes (8): ARTBOARD_WIDTH, AuthShellProps, FitToViewport(), FitToViewportProps, useFitScale(), measure(), useViewportFit(), ViewportFit

### Community 36 - "compilerOptions #36"
Cohesion: 0.10
Nodes (19): compilerOptions, allowArbitraryExtensions, allowImportingTsExtensions, erasableSyntaxOnly, jsx, lib, module, moduleDetection (+11 more)

### Community 37 - "EmailVerificationServiceInterface"
Cohesion: 0.08
Nodes (8): ResendVerificationData, VerifyEmailData, EmailVerificationServiceInterface, SendEmailVerification, ResendVerificationRequest, {closure#2}(), EmailVerificationService, EmailVerificationServiceTest

### Community 38 - "Illuminate\Http\JsonResponse"
Cohesion: 0.15
Nodes (3): MeController, MovieNightController, NotificationController

### Community 39 - "Illuminate\Database\Eloquent\Relations\BelongsTo"
Cohesion: 0.09
Nodes (6): ActivityFeed, CalendarEvent, Follow, MovieNightCandidate, MovieNightInvite, MovieNightVote

### Community 40 - "frontend/package"
Cohesion: 0.15
Nodes (15): vite, name, private, type, version, eslint, @eslint/js, eslint-plugin-react-hooks (+7 more)

### Community 41 - "SignIn.tsx"
Cohesion: 0.13
Nodes (20): AuthShell(), AuthWaves(), AuthWavesProps, bandPath(), LAYERS, Point, WaveSide, Checkbox() (+12 more)

### Community 42 - "ListDialog.tsx"
Cohesion: 0.12
Nodes (18): PICKABLE_MOVIES, Dialog(), EditDialog(), ListDialog(), ListDialogProps, MyList, toMyList(), createList() (+10 more)

### Community 43 - "Reactbits: Magnet"
Cohesion: 0.17
Nodes (16): arbitrate(), attach(), DEFAULT_SPRING, Entry, field, Magnet(), wake(), MagnetAxis (+8 more)

### Community 44 - "compilerOptions #44"
Cohesion: 0.12
Nodes (16): compilerOptions, allowImportingTsExtensions, erasableSyntaxOnly, lib, module, moduleDetection, noEmit, noFallthroughCasesInSwitch (+8 more)

### Community 45 - "npm: package"
Cohesion: 0.12
Nodes (16): author, description, devDependencies, husky, keywords, license, main, name (+8 more)

### Community 48 - "npm: backend/package"
Cohesion: 0.13
Nodes (14): vite, optionalDependencies, @laravel/multiplex, private, $schema, scripts, build, dev (+6 more)

### Community 50 - "AdminLayout"
Cohesion: 0.13
Nodes (4): ADMIN_USER, LINKS, MaskIcon(), UPCOMING

### Community 51 - "RuntimeException"
Cohesion: 0.13
Nodes (3): EmailRequired, InvalidCredentials, InvalidRefreshToken

### Community 52 - "Reactbits: SplitHeadline"
Cohesion: 0.16
Nodes (14): DEFAULT_FROM, DEFAULT_TO, fontsAreLoaded(), getEffect(), Kick, Look, SplitText(), SplitTextEffect (+6 more)

### Community 54 - "Illuminate\Database\Schema\Blueprint"
Cohesion: 0.27
Nodes (9): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}() (+1 more)

### Community 56 - "Landing"
Cohesion: 0.12
Nodes (4): HEADLINE, NAV_LINKS, STATS, TRENDING

### Community 60 - "MovieNight"
Cohesion: 0.09
Nodes (11): {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#14}(), {closure#3}(), {closure#4}(), {closure#6}() (+3 more)

### Community 61 - "0001_01_01_000000_create_users_table.php"
Cohesion: 0.33
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 62 - "devDependencies"
Cohesion: 0.15
Nodes (13): devDependencies, eslint, @eslint/js, eslint-plugin-react-hooks, eslint-plugin-react-refresh, globals, @types/node, @types/react (+5 more)

### Community 64 - "TestCase"
Cohesion: 0.24
Nodes (3): SocialTestCase, TestCase, UserResponseTest

### Community 65 - "0001_01_01_000002_create_jobs_table.php"
Cohesion: 0.33
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 66 - "2026_10_24_000001_add_profile_fields_to_users_table.php"
Cohesion: 0.33
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 67 - "Backend CI Workflow"
Cohesion: 0.50
Nodes (4): Backend CI Workflow, Laravel Checks job (PHP 8.4, composer install, artisan --version), Frontend CI Workflow, TypeScript Check and Build job (npm ci, tsc --noEmit, npm run build)

### Community 68 - ".id"
Cohesion: 0.13
Nodes (8): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), MovieListController, MovieList, MovieListPolicy

### Community 69 - "Controller"
Cohesion: 0.15
Nodes (3): EmailVerificationController, SocialAuthController, Controller

### Community 70 - "AuthService #70"
Cohesion: 0.20
Nodes (9): AppServiceProvider, Auth Domain Exceptions, AuthController, AuthService, EmailVerificationController, EmailVerificationService, Auth Known Follow-ups (captcha, GitHub verified email), SendEmailVerification Listener (+1 more)

### Community 71 - "AuthProvider / useAuth (AuthContext"
Cohesion: 0.22
Nodes (10): Auth DTOs (Request/Response data objects), AuthCookies, AuthResponse, Auth Form Requests, TokenPair, AuthProvider / useAuth (AuthContext), CI Workflows (frontend-ci, backend-ci), Known Issue: 3 react-refresh Lint Errors (+2 more)

### Community 72 - "Laravel Boost Guidelines"
Cohesion: 0.22
Nodes (8): .ai/rules project rules, Laravel 12 Streamlined Structure (bootstrap/app.php, casts() method), Laravel Boost Guidelines, Laravel Boost MCP Tools (database-query, database-schema, search-docs, browser-logs), Laravel Cloud Deployment, PHPUnit Testing Rules, Laravel Pint Formatter, Skills Activation (testing-best-practices, deploying-to-cloud)

### Community 74 - "Docs: Frontend Stack (React 19, Vite 8, CSS Mo"
Cohesion: 0.20
Nodes (9): React + TypeScript + Vite template README, Type-aware ESLint configuration, Known Issue: 950 kB Bundle (lazy-load routes), Figma Color Variable Tokens, Figma Design File (WatchWise), Figma MCP 20 calls/month limit, Figma Prototype Flows, Frontend Stack (React 19, Vite 8, CSS Modules) (+1 more)

### Community 75 - "composer"
Cohesion: 0.22
Nodes (8): description, keywords, license, minimum-stability, name, prefer-stable, $schema, type

### Community 76 - "Composer: config"
Cohesion: 0.22
Nodes (9): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, platform, preferred-install, sort-packages (+1 more)

### Community 77 - "Composer: scripts"
Cohesion: 0.22
Nodes (9): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, pre-package-uninstall, setup (+1 more)

### Community 79 - "WatchWise Handoff"
Cohesion: 0.29
Nodes (8): Laravel Boost bootstrap guidelines (AGENTS.md), robots.txt (allow all), Laravel Boost, Laravel Framework (default README), graphify Codebase Knowledge Graph, Web2 Project Brief (Modern Movie Discovery & Social Platform), WatchWise Handoff, WatchWise README

### Community 80 - "Policy: owns"
Cohesion: 0.33
Nodes (3): {closure#1}(), Notification, NotificationPolicy

### Community 83 - "Backend Auth Module (app/Auth)"
Cohesion: 0.25
Nodes (6): Backend Auth Module (app/Auth), AuthModuleServiceProvider, Discussion Rooms (mocked real-time), *Data.ts Mock Data Files, TMDB Catalog (feat/back-tmdb-movies, unmerged), Roadmap

### Community 84 - "TokenFactory"
Cohesion: 0.29
Nodes (5): PasswordResetController, PasswordResetRepository, PasswordResetService, RefreshTokenRepository, TokenFactory

### Community 86 - "Composer: require-dev"
Cohesion: 0.25
Nodes (8): require-dev, fakerphp/faker, laravel/boost, laravel/pail, laravel/pint, mockery/mockery, nunomaduro/collision, phpunit/phpunit

### Community 87 - "dependencies"
Cohesion: 0.25
Nodes (8): dependencies, axios, gsap, @gsap/react, lenis, react, react-dom, react-router

### Community 88 - "PillButton"
Cohesion: 0.25
Nodes (6): ButtonProps, CommonProps, ExternalProps, LinkProps, Never, PillVariant

### Community 89 - "MovieListItem"
Cohesion: 0.22
Nodes (3): {closure#2}(), MovieListItem, MovieListItemPolicy

### Community 91 - "Auth Middleware (auth.jwt, auth.optional"
Cohesion: 0.33
Nodes (7): Auth Middleware (auth.jwt, auth.optional, verified.email, role), AuthorizationServiceProvider, CurrentUser (JwtCurrentUser / GuestUser), CurrentUserFactory, EnsureRole Middleware, Policies (Review, Watchlist, MovieList, MovieNight, Message, Notification, User, ...), Admin Dashboard (AdminLayout)

### Community 92 - "Docs: Laravel 12 Backend"
Cohesion: 0.38
Nodes (5): Database Schema (PR #7), For You Weather + Gemini Mood, GeminiService, Laravel 12 Backend, Weather API (Open-Meteo proxy)

### Community 93 - "Composer: require"
Cohesion: 0.33
Nodes (6): require, firebase/php-jwt, laravel/framework, laravel/socialite, laravel/tinker, php

### Community 94 - "npm: devDependencies"
Cohesion: 0.33
Nodes (6): devDependencies, concurrently, laravel-vite-plugin, tailwindcss, @tailwindcss/vite, vite

### Community 100 - "SocialAuthController #100"
Cohesion: 0.40
Nodes (5): Auth Test Suite, SocialAccountRepository, SocialAuthController, SocialAuthService, Known Issue: test_social_redirect_sets_state_cookie returns 503

### Community 101 - "Composer: psr-4"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 107 - "scripts"
Cohesion: 0.40
Nodes (5): scripts, build, dev, lint, preview

### Community 109 - "Docs: Auth Page Swap (View Transitions"
Cohesion: 0.40
Nodes (3): AuthWaves, useEntranceAnimation (GSAP entrance motion), Lenis Smooth Scrolling

### Community 136 - "Composer: autoload-dev"
Cohesion: 0.67
Nodes (3): autoload-dev, psr-4, Tests\\

### Community 137 - "Composer: extra"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

### Community 141 - "Docs: In-memory Stores (myListsStore, nightsSt"
Cohesion: 0.67
Nodes (3): AppNav, Notifications Page, In-memory Stores (myListsStore, nightsStore, notificationsStore)

## Ambiguous Edges - Review These
- `Roadmap` → `Backend Auth Module (app/Auth)`  [AMBIGUOUS]
  README.md · relation: conceptually_related_to

## Knowledge Gaps
- **48 isolated node(s):** `php`, `concurrently`, `tailwindcss`, `vite`, `@laravel/multiplex` (+43 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 823 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **51 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `User` connect `User` to `AccountFlowsTest.php`, `Tests: AccountFlowsTest`, `UserRepositoryInterface`, `AuthService #8`, `Auth: RefreshToken`, `CurrentUser`, `AuthResponse`, `Tests: GuestUser`, `Message`, `ProfileController.php`, `SocialAccountRepository`, `EmailVerificationServiceInterface`, `Illuminate\Http\JsonResponse`, `ResetPasswordData`, `Auth: TokenFactory`, `RuntimeException`, `UserFactory`, `ProfileTest`, `.loginWithProvider`, `ContentTest`, `MovieNight`, `UserResponse`, `TestCase`, `Tests: MiddlewareTest`, `Policy: owns`?**
  _High betweenness centrality (0.068) - this node is a cross-community bridge._
- **What connects `php`, `concurrently`, `tailwindcss` to the rest of the system?**
  _48 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `ForgotPasswordData` be split into smaller, more focused modules?**
  _Cohesion score 0.14761904761904762 - nodes in this community are weakly interconnected._
- **Why does `react` connect `react` to `MovieDetails`, `Settings`, `PosterCardMini: SignUp`, `Cinemas`, `Rooms.tsx`, `adminData`, `AdminMovies`, `Discover`, `Profile.tsx`, `AdminOverview`, `router.tsx`, `AdminUsers`, `ButtonPrimary: react-router`, `ForYou`, `Home.tsx`, `MovieNights.tsx`, `YearInFilmCard: TrendingCard`, `Watchlist.tsx`, `Notifications.tsx`, `AuthShell: react`, `frontend/package`, `SignIn.tsx`, `ListDialog.tsx`, `Reactbits: Magnet`, `Reactbits: SplitHeadline`, `Landing`, `PillButton`, `Checkbox`?**
  _High betweenness centrality (0.043) - this node is a cross-community bridge._
- **Should `MovieDetails` be split into smaller, more focused modules?**
  _Cohesion score 0.04839685420447671 - nodes in this community are weakly interconnected._
- **Why does `CurrentUser` connect `CurrentUser` to `TestCase`, `ProfileController.php`, `.id`, `User`, `Illuminate\Http\JsonResponse`, `MovieNight`, `Policy: owns`, `Illuminate\Http\Request`, `MovieListItem`, `Tests: GuestUser`, `AuthController.php`, `Message`?**
  _High betweenness centrality (0.033) - this node is a cross-community bridge._

### Low-confidence Hints
_AMBIGUOUS edges — the extractor was unsure. Verify before acting on these._

- **What is the exact relationship between `Roadmap` and `Backend Auth Module (app/Auth)`?**
  _Edge tagged AMBIGUOUS (relation: conceptually_related_to) - confidence is low._