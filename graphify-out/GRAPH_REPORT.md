# Graph Report - Web2-Project  (2026-10-22)

## Corpus Check
- 351 files · ~62,152 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 1206 nodes · 2073 edges · 107 communities (58 shown, 49 thin omitted)
- Extraction: 99% EXTRACTED · 1% INFERRED · 0% AMBIGUOUS · INFERRED: 23 edges (avg confidence: 0.82)
- Token cost: 78,145 input · 0 output

## Community Hubs (Navigation)
- Social Buttons & Settings Toggles
- Frontend Dependencies & Lint
- CI, Docs & Project Overview
- User Model & Relations
- Weather API (Open-Meteo)
- Nearby Cinemas Map
- Discussion Rooms Chat
- Profile Page
- Discover Page & Filters
- Movie Night Detail & Voting
- Movie Details Page
- Auth Shell & Waves
- Backend Vite/Tailwind Tooling
- Home Page
- Movie Nights Planner
- Watchlist & History Page
- For You Page & Live Weather
- Chips & Trending Card
- Sign In & Poster Stack
- SplitText Headline Animation
- Frontend TS App Config
- Form Controls & Sign Up
- Signed-in Page Shells
- Calendar & Follow Models
- Primary Button & Forgot Password
- Landing Page
- Magnet Interaction
- Frontend TS Node Config
- Root Repo & Husky Setup
- Create/Edit List Dialog
- Movie Nights Store & Data
- List Detail Page
- Lists Page & Data
- Discussion Rooms & Movie Nights Models
- Core Laravel Migrations
- Text Field & App Shell
- List Detail Data
- Notifications & Password Resets
- Reviews & Sentiment
- Movie Tile & Person Data
- My Lists Store
- Movie Nights Mock Guests
- Movie Data Mocks
- Composer Package Config
- Composer Plugins & Platform
- Composer Scripts
- Composer Dev Dependencies
- Animated Auth Waves
- Pill Button
- Viewport Fit Scaling
- App Service Provider
- App Navigation Header
- Poster Collage
- Movie Details Helpers
- Movie List Model
- Movie Night Candidates
- Movie Night Votes
- Composer Autoload
- Cache Table Migration
- Social Accounts Migration
- Activity Feed Migration
- Rating Stars
- Mock Data & External APIs
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
- Frontend Motion System
- Test Autoload
- Laravel Package Discovery
- Laravel Boost MCP
- TS Project References
- In-Memory Stores & Real-time Plan
- Planned Product Capabilities
- Fonts & HTML Entry
- Bundle Size & Code Splitting
- Contributing Guidelines
- Roadmap

## God Nodes (most connected - your core abstractions)
1. `react` - 39 edges
2. `react-router` - 31 edges
3. `useEntranceAnimation()` - 25 edges
4. `User` - 22 edges
5. `SplitHeadline()` - 22 edges
6. `gradientCss()` - 19 edges
7. `compilerOptions` - 18 edges
8. `MOVIES` - 17 edges
9. `ButtonPrimary()` - 15 edges
10. `compilerOptions` - 15 edges

## Surprising Connections (you probably didn't know these)
- `TypeScript Check and Build job (npm ci, tsc --noEmit, npm run build)` --conceptually_related_to--> `Frontend Stack (React 19, Vite, CSS Modules, GSAP, Lenis)`  [INFERRED]
  .github/workflows/frontend-ci.yml → HANDOFF.md
- `React + TypeScript + Vite template README` --conceptually_related_to--> `Frontend Stack (React 19, Vite, CSS Modules, GSAP, Lenis)`  [INFERRED]
  frontend/README.md → HANDOFF.md
- `Laravel Checks job (PHP 8.4, composer install, artisan --version)` --references--> `Laravel Framework (default README)`  [INFERRED]
  .github/workflows/backend-ci.yml → backend/README.md
- `Laravel 12 Streamlined Structure (bootstrap/app.php, casts() method)` --conceptually_related_to--> `Backend Stack (Laravel 12, PHP 8.2+, Eloquent, SQLite, PHPUnit)`  [INFERRED]
  backend/CLAUDE.md → README.md
- `Frontend CI Workflow` --semantically_similar_to--> `Backend CI Workflow`  [INFERRED] [semantically similar]
  .github/workflows/frontend-ci.yml → .github/workflows/backend-ci.yml

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Backend agent coding rules (Laravel Boost)** — backend_claude_php_rules, backend_claude_laravel_way, backend_claude_laravel12_structure, backend_claude_pint, backend_claude_phpunit [EXTRACTED 1.00]
- **UI Motion System (entrances, auth swap, waves, fit-to-viewport)** — handoff_entrance_motion, handoff_auth_page_swap, handoff_auth_waves, handoff_fit_to_viewport [INFERRED 0.85]
- **Replacing Mocks with Backend APIs** — handoff_mock_data_files, handoff_shared_in_memory_stores, handoff_mocked_realtime_rooms, handoff_tmdb_integration, handoff_laravel_reverb, handoff_laravel_auth [INFERRED 0.85]

## Communities (107 total, 49 thin omitted)

### Community 0 - "Social Buttons & Settings Toggles"
Cohesion: 0.06
Nodes (21): PROVIDERS, SocialButtonProps, SocialProvider, Toggle(), ToggleProps, INTEGRATION_ICONS, maskIcon(), MENU (+13 more)

### Community 1 - "Frontend Dependencies & Lint"
Cohesion: 0.05
Nodes (43): dependencies, axios, gsap, @gsap/react, lenis, react, react-dom, react-router (+35 more)

### Community 2 - "CI, Docs & Project Overview"
Cohesion: 0.05
Nodes (33): Backend CI Workflow, Laravel Checks job (PHP 8.4, composer install, artisan --version), Frontend CI Workflow, TypeScript Check and Build job (npm ci, tsc --noEmit, npm run build), Laravel Boost bootstrap guidelines (AGENTS.md), .ai/rules project rules, Laravel 12 Streamlined Structure (bootstrap/app.php, casts() method), Laravel Boost Guidelines (+25 more)

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

### Community 7 - "Profile Page"
Cohesion: 0.10
Nodes (22): ACTIVITY_ICON, ActivityCard(), ActivityHead(), GENRE_COLORS, ListPreview(), PeopleTab, PersonRow(), Profile() (+14 more)

### Community 8 - "Discover Page & Filters"
Cohesion: 0.10
Nodes (15): Discover(), fraction(), pageItems(), DEFAULT_FILTERS, Filters, GENRES, INITIAL_FILTERS, LANGUAGES (+7 more)

### Community 9 - "Movie Night Detail & Voting"
Cohesion: 0.10
Nodes (17): calendarUrl(), DetailRow(), maskIcon(), NightDetailPage(), RSVP_CHIP, RSVP_OPTIONS, Candidate, DETAILS (+9 more)

### Community 10 - "Movie Details Page"
Cohesion: 0.07
Nodes (3): PINS, SortKey, SORTS

### Community 11 - "Auth Shell & Waves"
Cohesion: 0.15
Nodes (19): ARTBOARD_WIDTH, AuthShell(), AuthShellProps, AuthWaves(), ButtonPrimary(), FitToViewport(), Logo(), LogoProps (+11 more)

### Community 12 - "Backend Vite/Tailwind Tooling"
Cohesion: 0.08
Nodes (21): devDependencies, concurrently, laravel-vite-plugin, tailwindcss, @tailwindcss/vite, vite, vite, optionalDependencies (+13 more)

### Community 13 - "Home Page"
Cohesion: 0.11
Nodes (10): greeting(), ACTIVITY, BECAUSE_YOU_LOVED, G, MOVIE_NIGHT, POPULAR, RELEASES, ROOMS (+2 more)

### Community 14 - "Movie Nights Planner"
Cohesion: 0.11
Nodes (12): ALL_MOVIES, FormErrors, HOW_IT_WORKS, maskIcon(), movieBySlug(), MovieNights(), NightCard(), STATUS_CLASS (+4 more)

### Community 15 - "Watchlist & History Page"
Cohesion: 0.12
Nodes (12): MAX_TASTE, SortKey, SORTS, Tab, Availability, FILTERS, HISTORY, HistoryEntry (+4 more)

### Community 16 - "For You Page & Live Weather"
Cohesion: 0.11
Nodes (9): SHELF_ICONS, weatherCondition(), WeatherResponse, PROFILE, Shelf, SHELVES, TASTE, TasteChip (+1 more)

### Community 17 - "Chips & Trending Card"
Cohesion: 0.11
Nodes (8): Chip(), TrendingCard(), TrendingCardProps, TrendingMovie, GENRES, Month, MONTHS, YearInFilmCard()

### Community 18 - "Sign In & Poster Stack"
Cohesion: 0.12
Nodes (8): MiniPoster, PosterCardMini(), PosterCardMiniProps, LAST_LAP, NEON_TIDE, PAPER_MOON, Errors, PROVIDERS

### Community 19 - "SplitText Headline Animation"
Cohesion: 0.13
Nodes (17): DEFAULT_FROM, DEFAULT_TO, fontsAreLoaded(), getEffect(), Kick, Look, SplitText(), SplitTextEffect (+9 more)

### Community 20 - "Frontend TS App Config"
Cohesion: 0.10
Nodes (19): compilerOptions, allowArbitraryExtensions, allowImportingTsExtensions, erasableSyntaxOnly, jsx, lib, module, moduleDetection (+11 more)

### Community 21 - "Form Controls & Sign Up"
Cohesion: 0.12
Nodes (5): Checkbox(), CheckboxProps, OrDivider(), Errors, PROVIDERS

### Community 22 - "Signed-in Page Shells"
Cohesion: 0.19
Nodes (12): AppNav(), MovieTile(), useEntranceAnimation(), ForYou(), Home(), gradientCss(), Person(), PersonPage() (+4 more)

### Community 23 - "Calendar & Follow Models"
Cohesion: 0.16
Nodes (4): CalendarEvent, Follow, Message, MovieNightInvite

### Community 24 - "Primary Button & Forgot Password"
Cohesion: 0.13
Nodes (6): ButtonProps, CommonProps, LinkProps, AUTH_SWAP_STATE, AuthSwapState, STEPS

### Community 25 - "Landing Page"
Cohesion: 0.12
Nodes (4): HEADLINE, NAV_LINKS, STATS, TRENDING

### Community 26 - "Magnet Interaction"
Cohesion: 0.17
Nodes (16): arbitrate(), attach(), DEFAULT_SPRING, Entry, field, Magnet(), wake(), MagnetAxis (+8 more)

### Community 27 - "Frontend TS Node Config"
Cohesion: 0.12
Nodes (16): compilerOptions, allowImportingTsExtensions, erasableSyntaxOnly, lib, module, moduleDetection, noEmit, noFallthroughCasesInSwitch (+8 more)

### Community 28 - "Root Repo & Husky Setup"
Cohesion: 0.12
Nodes (16): author, description, devDependencies, husky, keywords, license, main, name (+8 more)

### Community 29 - "Create/Edit List Dialog"
Cohesion: 0.15
Nodes (6): ALL_MOVIES, Dialog(), initialPicks(), ListDialog(), ListDialogProps, uniqueSlug()

### Community 30 - "Movie Nights Store & Data"
Cohesion: 0.17
Nodes (14): NightDetailRoute(), Invitation, INVITATIONS, Night, UPCOMING, addNight(), answerInvitation(), byDate() (+6 more)

### Community 31 - "List Detail Page"
Cohesion: 0.16
Nodes (6): PillButton(), compact(), ListDetail(), ListDetailPage(), SortKey, SORTS

### Community 32 - "Lists Page & Data"
Cohesion: 0.16
Nodes (6): Tab, FOLLOWING_COUNT, G, MY_LISTS, PUBLIC_LISTS, PublicList

### Community 34 - "Core Laravel Migrations"
Cohesion: 0.23
Nodes (6): {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}(), {closure#3}()

### Community 35 - "Text Field & App Shell"
Cohesion: 0.22
Nodes (5): TextFieldProps, router, lenis, react, react-router

### Community 36 - "List Detail Data"
Cohesion: 0.19
Nodes (12): ALL_MOVIES, entriesFor(), FOLLOWER_FACES, getListDetail(), ListDetail, ListEntry, ME, moviesToEntries() (+4 more)

### Community 37 - "Notifications & Password Resets"
Cohesion: 0.21
Nodes (3): Notification, PasswordReset, SocialAccount

### Community 38 - "Reviews & Sentiment"
Cohesion: 0.18
Nodes (3): Review, SentimentType, VARIANTS

### Community 39 - "Movie Tile & Person Data"
Cohesion: 0.17
Nodes (6): MOVIES, Collaborator, Credit, LENA, OTHERS, Person

### Community 40 - "My Lists Store"
Cohesion: 0.24
Nodes (10): Lists(), PublicListCard(), MyList, deleteMyList(), emit(), getMyLists(), listeners, saveMyList() (+2 more)

### Community 41 - "Movie Nights Mock Guests"
Cohesion: 0.18
Nodes (10): DATE, JAD, LINA, NightStatus, OMAR, PAST, RANA, SARA (+2 more)

### Community 42 - "Movie Data Mocks"
Cohesion: 0.20
Nodes (9): Movie, BY_SLUG, CastMember, Cinema, getMovieDetails(), MovieDetails, NEON_TIDE, SIMILAR (+1 more)

### Community 43 - "Composer Package Config"
Cohesion: 0.22
Nodes (8): description, keywords, license, minimum-stability, name, prefer-stable, $schema, type

### Community 44 - "Composer Plugins & Platform"
Cohesion: 0.22
Nodes (9): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, platform, preferred-install, sort-packages (+1 more)

### Community 45 - "Composer Scripts"
Cohesion: 0.22
Nodes (9): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, pre-package-uninstall, setup (+1 more)

### Community 46 - "Composer Dev Dependencies"
Cohesion: 0.25
Nodes (8): require-dev, fakerphp/faker, laravel/boost, laravel/pail, laravel/pint, mockery/mockery, nunomaduro/collision, phpunit/phpunit

### Community 47 - "Animated Auth Waves"
Cohesion: 0.25
Nodes (5): AuthWavesProps, bandPath(), LAYERS, Point, WaveSide

### Community 48 - "Pill Button"
Cohesion: 0.25
Nodes (6): ButtonProps, CommonProps, ExternalProps, LinkProps, Never, PillVariant

### Community 49 - "Viewport Fit Scaling"
Cohesion: 0.38
Nodes (4): FitToViewportProps, measure(), useViewportFit(), ViewportFit

### Community 52 - "Poster Collage"
Cohesion: 0.40
Nodes (4): gradientCss(), PosterCollage(), PosterCollageProps, Gradient

### Community 53 - "Movie Details Helpers"
Cohesion: 0.47
Nodes (6): RatingStars(), ReviewCard(), Sentiment(), downloadIcs(), googleCalendarUrl(), MovieDetailsPage()

### Community 57 - "Composer Autoload"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 63 - "Mock Data & External APIs"
Cohesion: 0.40
Nodes (4): For You Live Weather, Per-page *Data.ts Mock Data, TMDB Integration (Planned), GET /api/weather (WeatherController)

### Community 69 - "Composer Requirements"
Cohesion: 0.50
Nodes (4): require, laravel/framework, laravel/tinker, php

### Community 88 - "Test Autoload"
Cohesion: 0.67
Nodes (3): autoload-dev, psr-4, Tests\\

### Community 89 - "Laravel Package Discovery"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

### Community 92 - "In-Memory Stores & Real-time Plan"
Cohesion: 0.67
Nodes (3): Laravel Reverb Real-time Channel (Planned), Mocked Real-time Discussion Rooms, Shared In-Memory Stores (myListsStore, nightsStore)

### Community 93 - "Planned Product Capabilities"
Cohesion: 0.67
Nodes (3): External Services (TMDB, weather, maps, sentiment, calendar, real-time rooms), Planned Product Capabilities (auth, discovery, reviews, watchlist, recommendations, lists, social, movie nights, admin), WatchWise README

## Knowledge Gaps
- **39 isolated node(s):** `vite`, `@types/node`, `@types/react`, `@types/react-dom`, `typescript` (+34 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 648 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **49 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `react` connect `Text Field & App Shell` to `Social Buttons & Settings Toggles`, `Frontend Dependencies & Lint`, `Nearby Cinemas Map`, `Discussion Rooms Chat`, `Profile Page`, `Discover Page & Filters`, `Movie Night Detail & Voting`, `Movie Details Page`, `Auth Shell & Waves`, `Home Page`, `Movie Nights Planner`, `Watchlist & History Page`, `For You Page & Live Weather`, `Chips & Trending Card`, `Sign In & Poster Stack`, `SplitText Headline Animation`, `Form Controls & Sign Up`, `Signed-in Page Shells`, `Primary Button & Forgot Password`, `Landing Page`, `Magnet Interaction`, `Create/Edit List Dialog`, `Movie Nights Store & Data`, `List Detail Page`, `Lists Page & Data`, `Reviews & Sentiment`, `My Lists Store`, `Animated Auth Waves`, `Pill Button`, `Viewport Fit Scaling`, `App Navigation Header`?**
  _High betweenness centrality (0.110) - this node is a cross-community bridge._
- **What connects `vite`, `@types/node`, `@types/react` to the rest of the system?**
  _39 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Social Buttons & Settings Toggles` be split into smaller, more focused modules?**
  _Cohesion score 0.058279370952821465 - nodes in this community are weakly interconnected._
- **Why does `react-router` connect `Text Field & App Shell` to `Social Buttons & Settings Toggles`, `Frontend Dependencies & Lint`, `Nearby Cinemas Map`, `Discussion Rooms Chat`, `Profile Page`, `Discover Page & Filters`, `Movie Night Detail & Voting`, `Movie Details Page`, `Auth Shell & Waves`, `Home Page`, `Movie Nights Planner`, `Watchlist & History Page`, `For You Page & Live Weather`, `Chips & Trending Card`, `Sign In & Poster Stack`, `Form Controls & Sign Up`, `Signed-in Page Shells`, `Primary Button & Forgot Password`, `Landing Page`, `Create/Edit List Dialog`, `List Detail Page`, `Lists Page & Data`, `Movie Tile & Person Data`, `Pill Button`, `App Navigation Header`?**
  _High betweenness centrality (0.055) - this node is a cross-community bridge._
- **Should `Frontend Dependencies & Lint` be split into smaller, more focused modules?**
  _Cohesion score 0.049494949494949494 - nodes in this community are weakly interconnected._
- **Why does `@vitejs/plugin-react` connect `Backend Vite/Tailwind Tooling` to `Frontend Dependencies & Lint`?**
  _High betweenness centrality (0.018) - this node is a cross-community bridge._
- **Should `CI, Docs & Project Overview` be split into smaller, more focused modules?**
  _Cohesion score 0.05121951219512195 - nodes in this community are weakly interconnected._