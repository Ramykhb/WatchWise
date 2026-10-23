# Graph Report - Web2-Project  (2026-10-22)

## Corpus Check
- 353 files · ~63,704 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 1242 nodes · 2162 edges · 109 communities (60 shown, 49 thin omitted)
- Extraction: 99% EXTRACTED · 1% INFERRED · 0% AMBIGUOUS · INFERRED: 23 edges (avg confidence: 0.84)
- Token cost: 81,819 input · 0 output

## Community Hubs (Navigation)
- Frontend Dependencies & Lint
- SplitText Headline Animation
- App Nav & Notifications
- User Model & Relations
- Weather API (Open-Meteo)
- Nearby Cinemas Map
- Discussion Rooms Chat
- Movie Details Page
- Discover Page & Filters
- Profile Page
- Settings Page
- Backend Vite/Tailwind Tooling
- Viewport Fit Scaling
- Auth Page Components
- Animated Auth Waves
- List Detail Page
- Movie Nights Planner
- Home Page
- Watchlist & History Page
- Movie Night Detail Data
- Sign In & Poster Stack
- Shared Header Components
- Movie Night Detail & Voting
- Frontend TS App Config
- Calendar & Follow Models
- Create/Edit List Dialog
- Magnet Interaction
- Frontend TS Node Config
- Root Repo & Husky Setup
- Lists Page & Data
- Person Page
- Reviews & Sentiment
- Movie Tile & Movie Data
- Movie Nights Store
- Discussion Rooms & Movie Nights Models
- Core Laravel Migrations
- Frontend Docs & Motion
- Auth Shell
- Notifications & Password Resets
- Laravel Boost Guidelines
- Trending Card
- Pages & Stores Overview
- Chips & Year-in-Film Card
- App Shell, Router & Lenis
- My Lists Store
- Composer Package Config
- Composer Plugins & Platform
- Composer Scripts
- Calendar Export & Rating Helpers
- CI Workflows
- Composer Dev Dependencies
- Pill Button
- Settings Data
- Social Login Buttons
- App Service Provider
- Poster Collage
- Figma Design & Admin Screens
- Project Tooling & Schema
- Mock Data & External APIs
- Movie List Model
- Movie Night Candidates
- Movie Night Votes
- Composer Autoload
- Cache Table Migration
- Social Accounts Migration
- Activity Feed Migration
- Rating Stars
- Activity Feed Model
- Movie List Item Model
- Review Model
- User Preference Model
- Watchlist Model
- Composer Requirements
- Reviews Migration
- Watchlists Migration
- User Preferences Migration
- Discussion Rooms Migration
- Messages Migration
- Movie Lists Migration
- Movie List Items Migration
- Follows Migration
- Movie Nights Migration
- Movie Night Invites Migration
- Movie Night Candidates Migration
- Movie Night Votes Migration
- Calendar Events Migration
- Notifications Migration
- Unit Test Example
- Toggle Switch
- Test Autoload
- Laravel Package Discovery
- Laravel Boost MCP
- TS Project References
- Planned Product Capabilities
- Fonts & HTML Entry
- Contributing Guidelines
- Roadmap

## God Nodes (most connected - your core abstractions)
1. `react` - 41 edges
2. `react-router` - 32 edges
3. `useEntranceAnimation()` - 26 edges
4. `AppNav()` - 24 edges
5. `User` - 22 edges
6. `SplitHeadline()` - 22 edges
7. `gradientCss()` - 19 edges
8. `compilerOptions` - 18 edges
9. `MOVIES` - 17 edges
10. `ButtonPrimary()` - 15 edges

## Surprising Connections (you probably didn't know these)
- `TypeScript Check and Build job (npm ci, tsc --noEmit, npm run build)` --conceptually_related_to--> `Frontend Stack (React 19, Vite, React Router, CSS Modules)`  [INFERRED]
  .github/workflows/frontend-ci.yml → HANDOFF.md
- `React + TypeScript + Vite template README` --conceptually_related_to--> `Frontend Stack (React 19, Vite, React Router, CSS Modules)`  [INFERRED]
  frontend/README.md → HANDOFF.md
- `Laravel Checks job (PHP 8.4, composer install, artisan --version)` --references--> `Laravel Framework (default README)`  [INFERRED]
  .github/workflows/backend-ci.yml → backend/README.md
- `Laravel 12 Streamlined Structure (bootstrap/app.php, casts() method)` --conceptually_related_to--> `Backend Stack (Laravel 12, PHP 8.2+, Eloquent, SQLite, PHPUnit)`  [INFERRED]
  backend/CLAUDE.md → README.md
- `Separated Frontend/Backend Architecture` --conceptually_related_to--> `Frontend Stack (React 19, Vite, React Router, CSS Modules)`  [INFERRED]
  README.md → HANDOFF.md

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Backend agent coding rules (Laravel Boost)** — backend_claude_php_rules, backend_claude_laravel_way, backend_claude_laravel12_structure, backend_claude_pint, backend_claude_phpunit [EXTRACTED 1.00]
- **Auth page swap motion system** — handoff_auth_page_swap, handoff_authwaves, handoff_use_auth_swap, handoff_use_entrance_animation, handoff_gsap [EXTRACTED 1.00]
- **Shared in-memory stores awaiting backend/real-time** — handoff_mylistsstore, handoff_nightsstore, handoff_notificationsstore, handoff_laravel_reverb [INFERRED 0.85]
- **Live weather flow via Laravel proxy** — handoff_for_you_page, handoff_weather_api, handoff_open_meteo, handoff_external_apis_via_laravel [EXTRACTED 1.00]

## Communities (109 total, 49 thin omitted)

### Community 0 - "Frontend Dependencies & Lint"
Cohesion: 0.05
Nodes (43): dependencies, axios, gsap, @gsap/react, lenis, react, react-dom, react-router (+35 more)

### Community 1 - "SplitText Headline Animation"
Cohesion: 0.06
Nodes (26): DEFAULT_FROM, DEFAULT_TO, fontsAreLoaded(), getEffect(), Kick, Look, SplitText(), SplitTextEffect (+18 more)

### Community 2 - "App Nav & Notifications"
Cohesion: 0.07
Nodes (25): AppNavProps, LINKS, KIND_STYLE, maskIcon(), NotificationItem(), Notifications(), PREFERENCES, Tab (+17 more)

### Community 3 - "User Model & Relations"
Cohesion: 0.06
Nodes (3): User, UserFactory, DatabaseSeeder

### Community 4 - "Weather API (Open-Meteo)"
Cohesion: 0.07
Nodes (8): Controller, WeatherController, {closure#1}(), {closure#2}(), {closure#3}(), ExampleTest, WeatherTest, TestCase

### Community 5 - "Nearby Cinemas Map"
Cohesion: 0.08
Nodes (15): Cinemas(), directionsUrl(), Mode, placeAt(), Cinema, CINEMAS, DEFAULT_LOCATION, Feature (+7 more)

### Community 6 - "Discussion Rooms Chat"
Cohesion: 0.08
Nodes (16): ALL_MOVIES, REPLIES, Rooms(), channelName(), clockTime(), EMOJIS, JAD, LINA (+8 more)

### Community 7 - "Movie Details Page"
Cohesion: 0.07
Nodes (5): getMovieDetails(), MovieDetails(), PINS, SortKey, SORTS

### Community 8 - "Discover Page & Filters"
Cohesion: 0.10
Nodes (15): Discover(), fraction(), pageItems(), DEFAULT_FILTERS, Filters, GENRES, INITIAL_FILTERS, LANGUAGES (+7 more)

### Community 9 - "Profile Page"
Cohesion: 0.11
Nodes (22): ACTIVITY_ICON, ActivityCard(), ActivityHead(), GENRE_COLORS, ListPreview(), PeopleTab, PersonRow(), Profile() (+14 more)

### Community 10 - "Settings Page"
Cohesion: 0.08
Nodes (9): INTEGRATION_ICONS, maskIcon(), MENU, PanelHead(), PasswordErrors, ProfileErrors, PROVIDERS, Section (+1 more)

### Community 11 - "Backend Vite/Tailwind Tooling"
Cohesion: 0.08
Nodes (21): devDependencies, concurrently, laravel-vite-plugin, tailwindcss, @tailwindcss/vite, vite, vite, optionalDependencies (+13 more)

### Community 12 - "Viewport Fit Scaling"
Cohesion: 0.09
Nodes (9): FitToViewport(), FitToViewportProps, measure(), useViewportFit(), ViewportFit, HEADLINE, NAV_LINKS, STATS (+1 more)

### Community 13 - "Auth Page Components"
Cohesion: 0.16
Nodes (13): AuthShell(), AuthWaves(), Checkbox(), OrDivider(), PosterCardMini(), SocialButton(), TextField(), useAuthSwap() (+5 more)

### Community 14 - "Animated Auth Waves"
Cohesion: 0.09
Nodes (11): AuthWavesProps, bandPath(), LAYERS, Point, WaveSide, ButtonProps, CommonProps, LinkProps (+3 more)

### Community 15 - "List Detail Page"
Cohesion: 0.11
Nodes (15): compact(), SortKey, SORTS, ALL_MOVIES, entriesFor(), FOLLOWER_FACES, getListDetail(), ListDetail (+7 more)

### Community 16 - "Movie Nights Planner"
Cohesion: 0.11
Nodes (12): ALL_MOVIES, FormErrors, HOW_IT_WORKS, maskIcon(), movieBySlug(), MovieNights(), NightCard(), STATUS_CLASS (+4 more)

### Community 17 - "Home Page"
Cohesion: 0.13
Nodes (10): greeting(), ACTIVITY, BECAUSE_YOU_LOVED, G, MOVIE_NIGHT, POPULAR, RELEASES, ROOMS (+2 more)

### Community 18 - "Watchlist & History Page"
Cohesion: 0.13
Nodes (12): MAX_TASTE, SortKey, SORTS, Tab, Availability, FILTERS, HISTORY, HistoryEntry (+4 more)

### Community 19 - "Movie Night Detail Data"
Cohesion: 0.11
Nodes (20): Candidate, DETAILS, EXTRA_A, EXTRA_B, NightDetail, Rsvp, SAMI, DATE (+12 more)

### Community 20 - "Sign In & Poster Stack"
Cohesion: 0.12
Nodes (8): MiniPoster, PosterCardMiniProps, SocialProvider, LAST_LAP, NEON_TIDE, PAPER_MOON, Errors, PROVIDERS

### Community 21 - "Shared Header Components"
Cohesion: 0.17
Nodes (16): AppNav(), ButtonPrimary(), Logo(), LogoProps, MovieTile(), SplitHeadline(), useEntranceAnimation(), ForYou() (+8 more)

### Community 22 - "Movie Night Detail & Voting"
Cohesion: 0.13
Nodes (9): calendarUrl(), DetailRow(), maskIcon(), NightDetailPage(), NightDetailRoute(), RSVP_CHIP, RSVP_OPTIONS, getNightDetail() (+1 more)

### Community 23 - "Frontend TS App Config"
Cohesion: 0.10
Nodes (19): compilerOptions, allowArbitraryExtensions, allowImportingTsExtensions, erasableSyntaxOnly, jsx, lib, module, moduleDetection (+11 more)

### Community 24 - "Calendar & Follow Models"
Cohesion: 0.16
Nodes (4): CalendarEvent, Follow, Message, MovieNightInvite

### Community 25 - "Create/Edit List Dialog"
Cohesion: 0.15
Nodes (7): ALL_MOVIES, Dialog(), initialPicks(), ListDialog(), ListDialogProps, getMyLists(), uniqueSlug()

### Community 26 - "Magnet Interaction"
Cohesion: 0.17
Nodes (16): arbitrate(), attach(), DEFAULT_SPRING, Entry, field, Magnet(), wake(), MagnetAxis (+8 more)

### Community 27 - "Frontend TS Node Config"
Cohesion: 0.12
Nodes (16): compilerOptions, allowImportingTsExtensions, erasableSyntaxOnly, lib, module, moduleDetection, noEmit, noFallthroughCasesInSwitch (+8 more)

### Community 28 - "Root Repo & Husky Setup"
Cohesion: 0.12
Nodes (16): author, description, devDependencies, husky, keywords, license, main, name (+8 more)

### Community 29 - "Lists Page & Data"
Cohesion: 0.15
Nodes (7): Tab, FOLLOWING_COUNT, G, MY_LISTS, MyList, PUBLIC_LISTS, PublicList

### Community 30 - "Person Page"
Cohesion: 0.15
Nodes (6): Collaborator, Credit, getPerson(), LENA, OTHERS, Person

### Community 31 - "Reviews & Sentiment"
Cohesion: 0.18
Nodes (5): Review, ReviewCard(), Sentiment(), SentimentType, VARIANTS

### Community 32 - "Movie Tile & Movie Data"
Cohesion: 0.15
Nodes (8): Movie, MOVIES, BY_SLUG, CastMember, Cinema, MovieDetails, NEON_TIDE, SIMILAR

### Community 33 - "Movie Nights Store"
Cohesion: 0.21
Nodes (12): Invitation, INVITATIONS, UPCOMING, addNight(), answerInvitation(), byDate(), emit(), getState() (+4 more)

### Community 35 - "Core Laravel Migrations"
Cohesion: 0.23
Nodes (6): {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}(), {closure#3}()

### Community 36 - "Frontend Docs & Motion"
Cohesion: 0.19
Nodes (12): React + TypeScript + Vite template README, Type-aware ESLint configuration, AuthWaves (GSAP-tweened SVG waves), Main bundle size warning (route code-splitting), Design Tokens (Color variables, Elevation styles), FitToViewport (desktop single-screen scaling), Frontend Stack (React 19, Vite, React Router, CSS Modules), GSAP (+4 more)

### Community 37 - "Auth Shell"
Cohesion: 0.18
Nodes (6): ARTBOARD_WIDTH, AuthShellProps, CheckboxProps, TextFieldProps, useFitScale(), react

### Community 38 - "Notifications & Password Resets"
Cohesion: 0.21
Nodes (3): Notification, PasswordReset, SocialAccount

### Community 39 - "Laravel Boost Guidelines"
Cohesion: 0.18
Nodes (9): .ai/rules project rules, Laravel 12 Streamlined Structure (bootstrap/app.php, casts() method), Laravel Boost Guidelines, Laravel Boost MCP Tools (database-query, database-schema, search-docs, browser-logs), Laravel Cloud Deployment, PHPUnit Testing Rules, Laravel Pint Formatter, Skills Activation (testing-best-practices, deploying-to-cloud) (+1 more)

### Community 41 - "Pages & Stores Overview"
Cohesion: 0.21
Nodes (11): AppNav (signed-in header), Nearby Cinemas page, Laravel Reverb (real-time channel), Lists / List Detail / ListDialog, Movie Nights / Movie Night Detail, myListsStore (shared in-memory store), nightsStore (shared in-memory store), Notifications page (+3 more)

### Community 42 - "Chips & Year-in-Film Card"
Cohesion: 0.24
Nodes (6): Chip(), TrendingCard(), GENRES, Month, MONTHS, YearInFilmCard()

### Community 43 - "App Shell, Router & Lenis"
Cohesion: 0.29
Nodes (3): router, lenis, react-router

### Community 44 - "My Lists Store"
Cohesion: 0.29
Nodes (8): Lists(), PublicListCard(), deleteMyList(), emit(), listeners, saveMyList(), subscribe(), useMyLists()

### Community 45 - "Composer Package Config"
Cohesion: 0.22
Nodes (8): description, keywords, license, minimum-stability, name, prefer-stable, $schema, type

### Community 46 - "Composer Plugins & Platform"
Cohesion: 0.22
Nodes (9): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, platform, preferred-install, sort-packages (+1 more)

### Community 47 - "Composer Scripts"
Cohesion: 0.22
Nodes (9): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, pre-package-uninstall, setup (+1 more)

### Community 48 - "Calendar Export & Rating Helpers"
Cohesion: 0.33
Nodes (9): PillButton(), RatingStars(), gradientCss(), downloadIcs(), googleCalendarUrl(), MovieDetailsPage(), byMonth(), HistoryList() (+1 more)

### Community 49 - "CI Workflows"
Cohesion: 0.25
Nodes (8): Backend CI Workflow, Laravel Checks job (PHP 8.4, composer install, artisan --version), Frontend CI Workflow, TypeScript Check and Build job (npm ci, tsc --noEmit, npm run build), Laravel Boost bootstrap guidelines (AGENTS.md), robots.txt (allow all), Laravel Boost, Laravel Framework (default README)

### Community 50 - "Composer Dev Dependencies"
Cohesion: 0.25
Nodes (8): require-dev, fakerphp/faker, laravel/boost, laravel/pail, laravel/pint, mockery/mockery, nunomaduro/collision, phpunit/phpunit

### Community 51 - "Pill Button"
Cohesion: 0.25
Nodes (6): ButtonProps, CommonProps, ExternalProps, LinkProps, Never, PillVariant

### Community 52 - "Settings Data"
Cohesion: 0.25
Nodes (7): ACCOUNT, CONNECTED, IntegrationKey, INTEGRATIONS, PRIVACY, PrivacyKey, VISIBILITY

### Community 55 - "Poster Collage"
Cohesion: 0.40
Nodes (4): gradientCss(), PosterCollage(), PosterCollageProps, Gradient

### Community 56 - "Figma Design & Admin Screens"
Cohesion: 0.33
Nodes (6): Admin Screens (Overview, Users, Review Moderation, Movie Data), Figma Components Frame, Figma Design File, Figma MCP call limits (Starter plan), Figma Prototype Flows, Owl Logo (Light/Dark variants)

### Community 57 - "Project Tooling & Schema"
Cohesion: 0.33
Nodes (6): CI workflows (frontend-ci, backend-ci), Database Schema (PR #7), graphify knowledge graph, Laravel 12 Backend, WatchWise (Modern Movie Discovery & Social Platform), Validation (CI-equivalent checks)

### Community 58 - "Mock Data & External APIs"
Cohesion: 0.33
Nodes (5): For You page (live weather), Mock *Data.ts files, Open-Meteo, TMDB integration, GET /api/weather (WeatherController)

### Community 62 - "Composer Autoload"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 73 - "Composer Requirements"
Cohesion: 0.50
Nodes (4): require, laravel/framework, laravel/tinker, php

### Community 92 - "Test Autoload"
Cohesion: 0.67
Nodes (3): autoload-dev, psr-4, Tests\\

### Community 93 - "Laravel Package Discovery"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

### Community 96 - "Planned Product Capabilities"
Cohesion: 0.67
Nodes (3): External Services (TMDB, weather, maps, sentiment, calendar, real-time rooms), Planned Product Capabilities (auth, discovery, reviews, watchlist, recommendations, lists, social, movie nights, admin), WatchWise README

## Knowledge Gaps
- **39 isolated node(s):** `vite`, `@types/node`, `@types/react`, `@types/react-dom`, `typescript` (+34 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 650 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **49 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `react` connect `Auth Shell` to `Frontend Dependencies & Lint`, `SplitText Headline Animation`, `App Nav & Notifications`, `Nearby Cinemas Map`, `Discussion Rooms Chat`, `Movie Details Page`, `Discover Page & Filters`, `Profile Page`, `Settings Page`, `Viewport Fit Scaling`, `Auth Page Components`, `Animated Auth Waves`, `List Detail Page`, `Movie Nights Planner`, `Home Page`, `Watchlist & History Page`, `Sign In & Poster Stack`, `Movie Night Detail & Voting`, `Create/Edit List Dialog`, `Magnet Interaction`, `Lists Page & Data`, `Person Page`, `Reviews & Sentiment`, `Movie Nights Store`, `Chips & Year-in-Film Card`, `App Shell, Router & Lenis`, `My Lists Store`, `Pill Button`?**
  _High betweenness centrality (0.107) - this node is a cross-community bridge._
- **What connects `vite`, `@types/node`, `@types/react` to the rest of the system?**
  _39 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Frontend Dependencies & Lint` be split into smaller, more focused modules?**
  _Cohesion score 0.049494949494949494 - nodes in this community are weakly interconnected._
- **Why does `react-router` connect `App Shell, Router & Lenis` to `Frontend Dependencies & Lint`, `SplitText Headline Animation`, `App Nav & Notifications`, `Nearby Cinemas Map`, `Discussion Rooms Chat`, `Movie Details Page`, `Discover Page & Filters`, `Profile Page`, `Settings Page`, `Viewport Fit Scaling`, `Auth Page Components`, `Animated Auth Waves`, `List Detail Page`, `Movie Nights Planner`, `Home Page`, `Watchlist & History Page`, `Sign In & Poster Stack`, `Shared Header Components`, `Movie Night Detail & Voting`, `Create/Edit List Dialog`, `Lists Page & Data`, `Person Page`, `Movie Tile & Movie Data`, `Trending Card`, `Pill Button`?**
  _High betweenness centrality (0.052) - this node is a cross-community bridge._
- **Should `SplitText Headline Animation` be split into smaller, more focused modules?**
  _Cohesion score 0.06090808416389812 - nodes in this community are weakly interconnected._
- **Why does `@vitejs/plugin-react` connect `Backend Vite/Tailwind Tooling` to `Frontend Dependencies & Lint`?**
  _High betweenness centrality (0.024) - this node is a cross-community bridge._
- **Should `App Nav & Notifications` be split into smaller, more focused modules?**
  _Cohesion score 0.07435897435897436 - nodes in this community are weakly interconnected._