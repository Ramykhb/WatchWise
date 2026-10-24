# Graph Report - Web2-Project  (2026-10-24)

## Corpus Check
- 479 files · ~81,083 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 1994 nodes · 4014 edges · 156 communities (88 shown, 68 thin omitted)
- Extraction: 96% EXTRACTED · 4% INFERRED · 0% AMBIGUOUS · INFERRED: 169 edges (avg confidence: 0.85)
- Token cost: 103,711 input · 0 output

## Community Hubs (Navigation)
- Auth: PasswordResetService
- MovieDetails
- Auth: User
- Settings
- Tests: AccountFlowsTest
- Model: User
- PosterCardMini: SignUp
- Auth: RefreshTokenRepositoryInterface
- AuthService #8
- Auth: RefreshToken
- Cinemas
- Rooms
- adminData
- AdminMovies
- Discover
- Profile
- AdminOverview
- Lists
- Policy: CurrentUser
- Auth: Request
- AdminUsers
- Auth: router
- AuthResponse
- ButtonPrimary: react-router
- ForYou
- homeData
- MovieNights
- Tests: GuestUser
- Auth: JsonResponse
- YearInFilmCard: TrendingCard
- Watchlist
- MovieNights: nightsData
- Notifications
- MovieNights: NightDetail
- Auth: {closure#1}
- AuthShell: react
- compilerOptions #36
- Auth: EmailVerificationServiceTest
- MovieDetails: MovieTile
- Model: BelongsTo
- frontend/package
- AuthWaves: useEntranceAnimation
- Lists: ListDialog
- Reactbits: Magnet
- compilerOptions #44
- npm: package
- Auth: ResendVerificationData
- Auth: TokenFactory
- npm: backend/package
- Tests: AuthenticationTest
- AdminLayout
- Person
- Reactbits: SplitHeadline
- MovieNights: nightsStore
- MovieRecommendationController
- AppServiceProvider
- Landing
- Auth: EmailVerificationServiceInterface
- ListDetail
- notificationsStore
- Model: HasMany
- Migration: Blueprint
- devDependencies
- listDetailData
- Policy: id
- Policy: AuthorizationServiceProvider
- Model
- Auth: JwtCurrentUser
- MovieListPolicy
- SocialAuthController #69
- AuthService #70
- AuthProvider / useAuth (AuthContext
- Laravel Boost Guidelines
- Tests: MiddlewareTest
- Docs: Frontend Stack (React 19, Vite 8, CSS Mo
- composer
- Composer: config
- Composer: scripts
- Tests: WeatherTest
- Laravel Framework (default README
- Policy: owns
- ReviewPolicy
- WatchlistPolicy
- WatchWise Handoff
- TokenFactory
- bootstrap/app
- Composer: require-dev
- dependencies
- PillButton
- MovieListItemPolicy
- WeatherController
- Auth Middleware (auth.jwt, auth.optional
- Docs: Laravel 12 Backend
- Composer: require
- npm: devDependencies
- AppNav
- PosterCollage: Gradient
- Model: MovieList
- Model: MovieNightCandidate
- Model: MovieNightVote
- SocialAuthController #100
- Composer: psr-4
- Migration: 0001_01_01_000001_create_cache_table
- Migration: Schema
- Migration
- Tests: GeminiWeatherRecommendation
- scripts
- Checkbox
- Docs: Auth Page Swap (View Transitions
- Auth: LoginRequest
- Model: ActivityFeed
- Model: MovieListItem
- Model: Review
- Model: UserPreference
- Model: Watchlist
- Migration: 2026_10_22_000003_create_social_accounts
- Migration: 2026_10_22_000004_create_reviews_table
- Migration: 2026_10_22_000005_create_watchlists_tabl
- Migration: 2026_10_22_000006_create_user_preference
- Migration: 2026_10_22_000007_create_discussion_room
- Migration: 2026_10_22_000008_create_messages_table
- Migration: 2026_10_22_000011_create_follows_table
- Migration: 2026_10_22_000012_create_activity_feed_t
- Migration: 2026_10_22_000013_create_movie_nights_ta
- Migration: 2026_10_22_000014_create_movie_night_inv
- Migration: 2026_10_22_000015_create_movie_night_can
- Migration: 2026_10_22_000016_create_movie_night_vot
- Migration: 2026_10_22_000017_create_calendar_events
- Migration: 2026_10_22_000018_create_notifications_t
- Migration: 2026_10_22_000019_create_refresh_tokens_
- TextField
- Docs: *Data.ts Mock Data Files
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
1. `CurrentUser` - 83 edges
2. `react` - 47 edges
3. `react-router` - 37 edges
4. `UserRepositoryInterface` - 28 edges
5. `AppNav()` - 26 edges
6. `useEntranceAnimation()` - 25 edges
7. `TokenFactory` - 24 edges
8. `AccountFlowsTest` - 24 edges
9. `gradientCss()` - 22 edges
10. `AuthResponse` - 22 edges

## Surprising Connections (you probably didn't know these)
- `RequireAuth / RequireGuest Route Guards` --semantically_similar_to--> `Auth Middleware (auth.jwt, auth.optional, verified.email, role)`  [INFERRED] [semantically similar]
  HANDOFF.md → backend/Backend.md
- `TypeScript Check and Build job (npm ci, tsc --noEmit, npm run build)` --conceptually_related_to--> `Frontend Stack (React 19, Vite 8, CSS Modules)`  [INFERRED]
  .github/workflows/frontend-ci.yml → HANDOFF.md
- `Roadmap` --conceptually_related_to--> `Backend Auth Module (app/Auth)`  [AMBIGUOUS]
  README.md → backend/Backend.md
- `React + TypeScript + Vite template README` --conceptually_related_to--> `Frontend Stack (React 19, Vite 8, CSS Modules)`  [INFERRED]
  frontend/README.md → HANDOFF.md
- `AuthProvider / useAuth (AuthContext)` --references--> `AuthController`  [INFERRED]
  HANDOFF.md → backend/Backend.md

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Backend agent coding rules (Laravel Boost)** — backend_claude_php_rules, backend_claude_laravel_way, backend_claude_laravel12_structure, backend_claude_pint, backend_claude_phpunit [EXTRACTED 1.00]
- **JWT Auth Request Flow** — backend_backend_auth_middleware, backend_backend_currentuserfactory, backend_backend_currentuser, backend_backend_policies, backend_backend_tokenfactory [EXTRACTED 1.00]
- **Auth Repository Layer** — backend_backend_userrepository, backend_backend_refreshtokenrepository, backend_backend_passwordresetrepository, backend_backend_socialaccountrepository [EXTRACTED 1.00]
- **Frontend Cookie Session Auth Flow** — handoff_authprovider, handoff_route_guards, backend_backend_authcontroller, backend_backend_authcookies [INFERRED 0.85]

## Communities (156 total, 68 thin omitted)

### Community 0 - "Auth: PasswordResetService"
Cohesion: 0.06
Nodes (13): PasswordResetController, ForgotPasswordData, ResetPasswordData, InvalidResetToken, PasswordResetRepositoryInterface, PasswordResetServiceInterface, PasswordResetRepository, ForgotPasswordRequest (+5 more)

### Community 1 - "MovieDetails"
Cohesion: 0.05
Nodes (15): RatingStars(), RatingStarsProps, Review, ReviewCard(), Sentiment(), SentimentType, VARIANTS, getMovieDetails() (+7 more)

### Community 2 - "Auth: User"
Cohesion: 0.09
Nodes (4): UserRepositoryInterface, UserRepository, UserRepositoryTest, UserResponseTest

### Community 3 - "Settings"
Cohesion: 0.06
Nodes (19): PROVIDERS, SocialButtonProps, SocialProvider, INTEGRATION_ICONS, maskIcon(), MENU, PanelHead(), PasswordErrors (+11 more)

### Community 4 - "Tests: AccountFlowsTest"
Cohesion: 0.09
Nodes (4): ResetPasswordMail, VerifyEmailMail, AccountFlowsTest, {closure#3}()

### Community 5 - "Model: User"
Cohesion: 0.06
Nodes (4): User, RefreshTokenFactory, UserFactory, DatabaseSeeder

### Community 6 - "PosterCardMini: SignUp"
Cohesion: 0.08
Nodes (11): MiniPoster, PosterCardMiniProps, AUTH_SWAP_STATE, AuthSwapState, LAST_LAP, NEON_TIDE, PAPER_MOON, Errors (+3 more)

### Community 7 - "Auth: RefreshTokenRepositoryInterface"
Cohesion: 0.13
Nodes (8): SocialLoginData, AccountDisabled, EmailRequired, UnverifiedProviderEmail, RefreshTokenRepositoryInterface, SocialAuthServiceInterface, SocialAuthService, SocialAuthServiceTest

### Community 8 - "AuthService #8"
Cohesion: 0.14
Nodes (7): LoginData, RegisterData, InvalidCredentials, InvalidRefreshToken, AuthServiceInterface, AuthService, AuthServiceTest

### Community 9 - "Auth: RefreshToken"
Cohesion: 0.09
Nodes (4): RefreshTokenRepository, RefreshToken, {closure#1}(), RefreshTokenRepositoryTest

### Community 10 - "Cinemas"
Cohesion: 0.08
Nodes (15): Cinemas(), directionsUrl(), Mode, placeAt(), Cinema, CINEMAS, DEFAULT_LOCATION, Feature (+7 more)

### Community 11 - "Rooms"
Cohesion: 0.08
Nodes (16): ALL_MOVIES, REPLIES, Rooms(), channelName(), clockTime(), EMOJIS, JAD, LINA (+8 more)

### Community 12 - "adminData"
Cohesion: 0.09
Nodes (21): FLAG_REASONS, FlagReason, GUIDELINES, MODERATION_REVIEWS, MODERATION_WEEK, ModerationReview, ModerationStatus, MOVIE_GENRES (+13 more)

### Community 13 - "AdminMovies"
Cohesion: 0.09
Nodes (18): Toggle(), ToggleProps, ADMIN_MOVIES, AdminMovie, MOVIE_SYNC, MovieFields, AdminMovies(), CONTENT_KEYS (+10 more)

### Community 14 - "Discover"
Cohesion: 0.10
Nodes (15): Discover(), fraction(), pageItems(), DEFAULT_FILTERS, Filters, GENRES, INITIAL_FILTERS, LANGUAGES (+7 more)

### Community 15 - "Profile"
Cohesion: 0.11
Nodes (22): ACTIVITY_ICON, ActivityCard(), ActivityHead(), GENRE_COLORS, ListPreview(), PeopleTab, PersonRow(), Profile() (+14 more)

### Community 16 - "AdminOverview"
Cohesion: 0.09
Nodes (14): StatCard(), StatCardProps, ACTIVITY_LABELS, HIGHEST_RATED, MOST_WATCHED, POPULAR_GENRES, RANGES, RankedMovie (+6 more)

### Community 17 - "Lists"
Cohesion: 0.12
Nodes (15): Lists(), PublicListCard(), Tab, FOLLOWING_COUNT, G, MY_LISTS, MyList, PUBLIC_LISTS (+7 more)

### Community 18 - "Policy: CurrentUser"
Cohesion: 0.19
Nodes (3): CurrentUser, MessagePolicy, UserPolicy

### Community 19 - "Auth: Request"
Cohesion: 0.22
Nodes (5): CurrentUserFactory, AuthenticateJwt, AuthenticateOptional, EnsureEmailVerified, EnsureRole

### Community 20 - "AdminUsers"
Cohesion: 0.09
Nodes (11): AdminUserRow, USER_STATUSES, USER_TOTALS, UserRole, UserStatus, AdminUsers(), fmt(), Menu (+3 more)

### Community 21 - "Auth: router"
Cohesion: 0.16
Nodes (18): SocialButton(), AuthContext, AuthContextValue, AuthProvider(), useAuth(), apiErrorMessage(), authApi, AuthPayload (+10 more)

### Community 22 - "AuthResponse"
Cohesion: 0.15
Nodes (3): AuthResponse, UserResponse, TokenPair

### Community 23 - "ButtonPrimary: react-router"
Cohesion: 0.11
Nodes (9): ButtonPrimary(), ButtonProps, CommonProps, LinkProps, LogoProps, STEPS, Placeholder(), PlaceholderProps (+1 more)

### Community 24 - "ForYou"
Cohesion: 0.11
Nodes (11): ForYou(), SHELF_ICONS, weatherCondition(), WeatherMood, WeatherResponse, PROFILE, Shelf, SHELVES (+3 more)

### Community 25 - "homeData"
Cohesion: 0.12
Nodes (10): greeting(), ACTIVITY, BECAUSE_YOU_LOVED, G, MOVIE_NIGHT, POPULAR, RELEASES, ROOMS (+2 more)

### Community 26 - "MovieNights"
Cohesion: 0.11
Nodes (12): ALL_MOVIES, FormErrors, HOW_IT_WORKS, maskIcon(), movieBySlug(), MovieNights(), NightCard(), STATUS_CLASS (+4 more)

### Community 27 - "Tests: GuestUser"
Cohesion: 0.16
Nodes (3): GuestUser, {closure#1}(), PolicyTest

### Community 28 - "Auth: JsonResponse"
Cohesion: 0.19
Nodes (3): AuthController, RegisterRequest, AuthCookies

### Community 29 - "YearInFilmCard: TrendingCard"
Cohesion: 0.11
Nodes (8): Chip(), TrendingCard(), TrendingCardProps, TrendingMovie, GENRES, Month, MONTHS, YearInFilmCard()

### Community 30 - "Watchlist"
Cohesion: 0.13
Nodes (14): byMonth(), HistoryList(), MAX_TASTE, SortKey, SORTS, Tab, Availability, FILTERS (+6 more)

### Community 31 - "MovieNights: nightsData"
Cohesion: 0.11
Nodes (20): MOVIES, Candidate, DETAILS, EXTRA_A, EXTRA_B, NightDetail, Rsvp, SAMI (+12 more)

### Community 32 - "Notifications"
Cohesion: 0.13
Nodes (12): AppNav(), KIND_STYLE, maskIcon(), NotificationItem(), Notifications(), PREFERENCES, Tab, TAB_KINDS (+4 more)

### Community 33 - "MovieNights: NightDetail"
Cohesion: 0.12
Nodes (8): calendarUrl(), DetailRow(), maskIcon(), NightDetailPage(), RSVP_CHIP, RSVP_OPTIONS, getNightDetail(), movieInfo()

### Community 34 - "Auth: {closure#1}"
Cohesion: 0.19
Nodes (5): SocialAccountRepositoryInterface, SocialAccountRepository, {closure#3}(), {closure#1}(), SocialAccountRepositoryTest

### Community 35 - "AuthShell: react"
Cohesion: 0.18
Nodes (14): ARTBOARD_WIDTH, AuthShell(), AuthShellProps, FitToViewport(), FitToViewportProps, Logo(), useAuthSwap(), useEntranceAnimation() (+6 more)

### Community 36 - "compilerOptions #36"
Cohesion: 0.10
Nodes (19): compilerOptions, allowArbitraryExtensions, allowImportingTsExtensions, erasableSyntaxOnly, jsx, lib, module, moduleDetection (+11 more)

### Community 37 - "Auth: EmailVerificationServiceTest"
Cohesion: 0.20
Nodes (4): VerifyEmailData, {closure#2}(), EmailVerificationService, EmailVerificationServiceTest

### Community 38 - "MovieDetails: MovieTile"
Cohesion: 0.12
Nodes (12): Movie, BY_SLUG, CastMember, Cinema, MovieDetails, NEON_TIDE, SIMILAR, Collaborator (+4 more)

### Community 39 - "Model: BelongsTo"
Cohesion: 0.16
Nodes (4): CalendarEvent, Follow, Message, MovieNightInvite

### Community 40 - "frontend/package"
Cohesion: 0.14
Nodes (16): vite, name, private, type, version, axios, eslint, @eslint/js (+8 more)

### Community 41 - "AuthWaves: useEntranceAnimation"
Cohesion: 0.14
Nodes (10): AuthWaves(), AuthWavesProps, bandPath(), LAYERS, Point, WaveSide, EASE_OUT_BACK, gsap (+2 more)

### Community 42 - "Lists: ListDialog"
Cohesion: 0.15
Nodes (7): ALL_MOVIES, Dialog(), initialPicks(), ListDialog(), ListDialogProps, getMyLists(), uniqueSlug()

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
Nodes (5): ADMIN_USER, AdminLayout(), LINKS, MaskIcon(), UPCOMING

### Community 51 - "Person"
Cohesion: 0.20
Nodes (9): MovieTile(), PillButton(), SplitHeadline(), Home(), gradientCss(), Person(), PersonPage(), getPerson() (+1 more)

### Community 52 - "Reactbits: SplitHeadline"
Cohesion: 0.17
Nodes (14): DEFAULT_FROM, DEFAULT_TO, fontsAreLoaded(), getEffect(), Kick, Look, SplitText(), SplitTextEffect (+6 more)

### Community 53 - "MovieNights: nightsStore"
Cohesion: 0.17
Nodes (14): NightDetailRoute(), Invitation, INVITATIONS, Night, UPCOMING, addNight(), answerInvitation(), byDate() (+6 more)

### Community 54 - "MovieRecommendationController"
Cohesion: 0.20
Nodes (3): MovieRecommendationController, WeatherMoodController, GeminiService

### Community 55 - "AppServiceProvider"
Cohesion: 0.17
Nodes (4): AuthModuleServiceProvider, AppServiceProvider, {closure#1}(), AuthorizationServiceProvider

### Community 56 - "Landing"
Cohesion: 0.13
Nodes (4): HEADLINE, NAV_LINKS, STATS, TRENDING

### Community 57 - "Auth: EmailVerificationServiceInterface"
Cohesion: 0.22
Nodes (3): EmailVerificationController, EmailVerificationServiceInterface, SendEmailVerification

### Community 58 - "ListDetail"
Cohesion: 0.16
Nodes (5): compact(), ListDetail(), ListDetailPage(), SortKey, SORTS

### Community 59 - "notificationsStore"
Cohesion: 0.18
Nodes (12): AppNotification, getItems(), INITIAL, JAD, LINA, listeners, NotificationKind, OMAR (+4 more)

### Community 61 - "Migration: Blueprint"
Cohesion: 0.23
Nodes (6): {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}(), {closure#3}()

### Community 62 - "devDependencies"
Cohesion: 0.15
Nodes (13): devDependencies, eslint, @eslint/js, eslint-plugin-react-hooks, eslint-plugin-react-refresh, globals, @types/node, @types/react (+5 more)

### Community 63 - "listDetailData"
Cohesion: 0.19
Nodes (12): ALL_MOVIES, entriesFor(), FOLLOWER_FACES, getListDetail(), ListDetail, ListEntry, ME, moviesToEntries() (+4 more)

### Community 66 - "Model"
Cohesion: 0.21
Nodes (3): Notification, PasswordReset, SocialAccount

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

### Community 79 - "Laravel Framework (default README"
Cohesion: 0.25
Nodes (8): Backend CI Workflow, Laravel Checks job (PHP 8.4, composer install, artisan --version), Frontend CI Workflow, TypeScript Check and Build job (npm ci, tsc --noEmit, npm run build), Laravel Boost bootstrap guidelines (AGENTS.md), robots.txt (allow all), Laravel Boost, Laravel Framework (default README)

### Community 83 - "WatchWise Handoff"
Cohesion: 0.29
Nodes (6): Backend Auth Module (app/Auth), AuthModuleServiceProvider, graphify Codebase Knowledge Graph, Web2 Project Brief (Modern Movie Discovery & Social Platform), WatchWise Handoff, WatchWise README

### Community 84 - "TokenFactory"
Cohesion: 0.29
Nodes (5): PasswordResetController, PasswordResetRepository, PasswordResetService, RefreshTokenRepository, TokenFactory

### Community 85 - "bootstrap/app"
Cohesion: 0.32
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 86 - "Composer: require-dev"
Cohesion: 0.25
Nodes (8): require-dev, fakerphp/faker, laravel/boost, laravel/pail, laravel/pint, mockery/mockery, nunomaduro/collision, phpunit/phpunit

### Community 87 - "dependencies"
Cohesion: 0.25
Nodes (8): dependencies, axios, gsap, @gsap/react, lenis, react, react-dom, react-router

### Community 88 - "PillButton"
Cohesion: 0.25
Nodes (6): ButtonProps, CommonProps, ExternalProps, LinkProps, Never, PillVariant

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

### Community 96 - "PosterCollage: Gradient"
Cohesion: 0.40
Nodes (4): gradientCss(), PosterCollage(), PosterCollageProps, Gradient

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

### Community 134 - "Docs: *Data.ts Mock Data Files"
Cohesion: 0.50
Nodes (4): Discussion Rooms (mocked real-time), *Data.ts Mock Data Files, TMDB Catalog (feat/back-tmdb-movies, unmerged), Roadmap

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
- **48 isolated node(s):** `vite`, `@types/node`, `@types/react`, `@types/react-dom`, `typescript` (+43 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 821 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **68 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `react` connect `AuthShell: react` to `MovieDetails`, `Settings`, `TextField`, `PosterCardMini: SignUp`, `Cinemas`, `Rooms`, `adminData`, `AdminMovies`, `Discover`, `Profile`, `AdminOverview`, `Lists`, `AdminUsers`, `Auth: router`, `ButtonPrimary: react-router`, `ForYou`, `homeData`, `MovieNights`, `YearInFilmCard: TrendingCard`, `Watchlist`, `Notifications`, `MovieNights: NightDetail`, `frontend/package`, `AuthWaves: useEntranceAnimation`, `Lists: ListDialog`, `Reactbits: Magnet`, `Person`, `Reactbits: SplitHeadline`, `MovieNights: nightsStore`, `Landing`, `ListDetail`, `notificationsStore`, `PillButton`, `AppNav`, `Checkbox`?**
  _High betweenness centrality (0.058) - this node is a cross-community bridge._
- **What connects `vite`, `@types/node`, `@types/react` to the rest of the system?**
  _48 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Auth: PasswordResetService` be split into smaller, more focused modules?**
  _Cohesion score 0.05926251097453907 - nodes in this community are weakly interconnected._
- **Why does `RefreshToken` connect `Auth: RefreshToken` to `Model`, `Auth: User`, `Model: User`, `Auth: TokenFactory`?**
  _High betweenness centrality (0.048) - this node is a cross-community bridge._
- **Should `MovieDetails` be split into smaller, more focused modules?**
  _Cohesion score 0.05333333333333334 - nodes in this community are weakly interconnected._
- **Why does `react-router` connect `ButtonPrimary: react-router` to `MovieDetails`, `Settings`, `PosterCardMini: SignUp`, `Cinemas`, `Rooms`, `adminData`, `Discover`, `Profile`, `AdminOverview`, `Lists`, `AdminUsers`, `Auth: router`, `ForYou`, `homeData`, `MovieNights`, `YearInFilmCard: TrendingCard`, `Watchlist`, `Notifications`, `MovieNights: NightDetail`, `MovieDetails: MovieTile`, `frontend/package`, `AuthWaves: useEntranceAnimation`, `Lists: ListDialog`, `AdminLayout`, `Person`, `Landing`, `ListDetail`, `PillButton`, `AppNav`?**
  _High betweenness centrality (0.027) - this node is a cross-community bridge._

### Low-confidence Hints
_AMBIGUOUS edges — the extractor was unsure. Verify before acting on these._

- **What is the exact relationship between `Roadmap` and `Backend Auth Module (app/Auth)`?**
  _Edge tagged AMBIGUOUS (relation: conceptually_related_to) - confidence is low._

## Run notes
- 2026-10-24 incremental update: 124 code files re-extracted (AST), 3 docs (HANDOFF.md, README.md, backend/Backend.md) through one extraction pass (103,711 tokens total), 4 deleted files pruned.
- Figma image assets are not extracted (cost vs. value); their imports appear as bare `frontend_src_assets_*` nodes.
- Community names are generated from each community's most connected node and its area of the codebase.
- Health: 0 dangling/missing endpoints, 0 collapsed edges, 11 self-loops.
- AMBIGUOUS: the README roadmap still lists Laravel authentication as not done, while the auth module is built.
