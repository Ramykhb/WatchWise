# Graph Report - Web2-Project  (2026-10-22)

## Corpus Check
- Corpus is ~45,094 words - fits in a single context window. You may not need a graph.

## Summary
- 961 nodes · 1605 edges · 89 communities (44 shown, 45 thin omitted)
- Extraction: 99% EXTRACTED · 1% INFERRED · 0% AMBIGUOUS · INFERRED: 20 edges (avg confidence: 0.81)
- Token cost: 89,540 input · 0 output

## Community Hubs (Navigation)
- Lists, List Detail & Poster Collage
- CI, Docs & Project Overview
- Frontend Dependencies & Lint
- User Model & Relations
- Landing, Chips & Trending Card
- Weather API (Open-Meteo)
- Movie Details Page
- Discover Page & Filters
- Home Page
- Backend Vite/Tailwind Tooling
- For You Page & Live Weather
- Sign In & Poster Stack
- SplitText Headline Animation
- Watchlist & History Page
- Auth Shell & Form Controls
- Frontend TS App Config
- Calendar & Follow Models
- Primary Button & Forgot Password
- App Shell, Router & Lenis
- Magnet Interaction
- Frontend TS Node Config
- Root Repo & Husky Setup
- Sign Up & Year-in-Film Card
- Reviews & Sentiment
- Auth Component Calls
- Discussion Rooms & Movie Nights
- Core Laravel Migrations
- Person Page & App Shell Calls
- Notifications & Password Resets
- Movie Tile & Person Data
- Composer Package Config
- Composer Plugins & Platform
- Composer Scripts
- Calendar Export & Rating Helpers
- Composer Dev Dependencies
- Social Login Buttons
- Pill Button
- Movie Data Mocks
- App Service Provider
- App Navigation Header
- Animated Auth Waves
- Movie List Model
- Movie Night Candidates
- Movie Night Votes
- Composer Autoload
- Cache Table Migration
- Social Accounts Migration
- Activity Feed Migration
- Rating Stars
- Auth Page Swap Motion
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
- Test Autoload
- Laravel Package Discovery
- Laravel Boost MCP
- TS Project References
- Quality Gates (CI & Hooks)
- Fonts & HTML Entry
- Team Working Agreements

## God Nodes (most connected - your core abstractions)
1. `react` - 30 edges
2. `SplitHeadline()` - 26 edges
3. `react-router` - 24 edges
4. `User` - 22 edges
5. `useEntranceAnimation()` - 21 edges
6. `ButtonPrimary()` - 19 edges
7. `AppNav()` - 18 edges
8. `compilerOptions` - 18 edges
9. `compilerOptions` - 15 edges
10. `Magnet()` - 14 edges

## Surprising Connections (you probably didn't know these)
- `TypeScript Check and Build job (npm ci, tsc --noEmit, npm run build)` --conceptually_related_to--> `Frontend Stack (React 19, Vite 8, React Router 7, CSS Modules, GSAP, Lenis, React Bits, axios)`  [INFERRED]
  .github/workflows/frontend-ci.yml → HANDOFF.md
- `React + TypeScript + Vite template README` --conceptually_related_to--> `Frontend Stack (React 19, Vite 8, React Router 7, CSS Modules, GSAP, Lenis, React Bits, axios)`  [INFERRED]
  frontend/README.md → HANDOFF.md
- `Laravel Checks job (PHP 8.4, composer install, artisan --version)` --references--> `Laravel Framework (default README)`  [INFERRED]
  .github/workflows/backend-ci.yml → backend/README.md
- `PHPUnit Testing Rules` --conceptually_related_to--> `GET /api/weather (WeatherController, Open-Meteo proxy, PR #15)`  [INFERRED]
  backend/CLAUDE.md → HANDOFF.md
- `Do Things the Laravel Way (artisan make:, API Resources, named routes)` --conceptually_related_to--> `External APIs go through Laravel`  [INFERRED]
  backend/CLAUDE.md → HANDOFF.md

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Auth page swap motion system** — handoff_auth_page_swap, handoff_useauthswap, handoff_authwaves, handoff_motion_css, handoff_useentranceanimation [EXTRACTED 1.00]
- **Location-aware weather integration flow** — handoff_for_you_weather, handoff_weather_api, handoff_open_meteo, handoff_external_apis_via_laravel [EXTRACTED 1.00]
- **Backend agent coding rules (Laravel Boost)** — backend_claude_php_rules, backend_claude_laravel_way, backend_claude_laravel12_structure, backend_claude_pint, backend_claude_phpunit [EXTRACTED 1.00]

## Communities (89 total, 45 thin omitted)

### Community 0 - "Lists, List Detail & Poster Collage"
Cohesion: 0.06
Nodes (30): gradientCss(), PosterCollage(), PosterCollageProps, useEntranceAnimation(), Gradient, compact(), ListDetail(), ListDetailPage() (+22 more)

### Community 1 - "CI, Docs & Project Overview"
Cohesion: 0.05
Nodes (41): Backend CI Workflow, Laravel Checks job (PHP 8.4, composer install, artisan --version), Frontend CI Workflow, TypeScript Check and Build job (npm ci, tsc --noEmit, npm run build), Laravel Boost bootstrap guidelines (AGENTS.md), .ai/rules project rules, Laravel 12 Streamlined Structure (bootstrap/app.php, casts() method), Laravel Boost Guidelines (+33 more)

### Community 2 - "Frontend Dependencies & Lint"
Cohesion: 0.05
Nodes (43): dependencies, axios, gsap, @gsap/react, lenis, react, react-dom, react-router (+35 more)

### Community 3 - "User Model & Relations"
Cohesion: 0.06
Nodes (3): User, UserFactory, DatabaseSeeder

### Community 4 - "Landing, Chips & Trending Card"
Cohesion: 0.06
Nodes (11): Chip(), TrendingCard(), TrendingCardProps, TrendingMovie, GENRES, Month, MONTHS, HEADLINE (+3 more)

### Community 5 - "Weather API (Open-Meteo)"
Cohesion: 0.07
Nodes (8): Controller, WeatherController, {closure#1}(), {closure#2}(), {closure#3}(), ExampleTest, WeatherTest, TestCase

### Community 6 - "Movie Details Page"
Cohesion: 0.07
Nodes (6): getMovieDetails(), MovieDetails(), PINS, SortKey, SORTS, Placeholder()

### Community 7 - "Discover Page & Filters"
Cohesion: 0.10
Nodes (15): Discover(), fraction(), pageItems(), DEFAULT_FILTERS, Filters, GENRES, INITIAL_FILTERS, LANGUAGES (+7 more)

### Community 8 - "Home Page"
Cohesion: 0.10
Nodes (11): greeting(), Home(), ACTIVITY, BECAUSE_YOU_LOVED, G, MOVIE_NIGHT, POPULAR, RELEASES (+3 more)

### Community 9 - "Backend Vite/Tailwind Tooling"
Cohesion: 0.08
Nodes (21): devDependencies, concurrently, laravel-vite-plugin, tailwindcss, @tailwindcss/vite, vite, vite, optionalDependencies (+13 more)

### Community 10 - "For You Page & Live Weather"
Cohesion: 0.11
Nodes (10): ForYou(), SHELF_ICONS, weatherCondition(), WeatherResponse, PROFILE, Shelf, SHELVES, TASTE (+2 more)

### Community 11 - "Sign In & Poster Stack"
Cohesion: 0.11
Nodes (7): MiniPoster, PosterCardMiniProps, LAST_LAP, NEON_TIDE, PAPER_MOON, Errors, PROVIDERS

### Community 12 - "SplitText Headline Animation"
Cohesion: 0.12
Nodes (17): DEFAULT_FROM, DEFAULT_TO, fontsAreLoaded(), getEffect(), Kick, Look, SplitText(), SplitTextEffect (+9 more)

### Community 13 - "Watchlist & History Page"
Cohesion: 0.13
Nodes (12): MAX_TASTE, SortKey, SORTS, Tab, Availability, FILTERS, HISTORY, HistoryEntry (+4 more)

### Community 14 - "Auth Shell & Form Controls"
Cohesion: 0.13
Nodes (11): ARTBOARD_WIDTH, AuthShellProps, CheckboxProps, FitToViewport(), FitToViewportProps, TextFieldProps, useFitScale(), measure() (+3 more)

### Community 15 - "Frontend TS App Config"
Cohesion: 0.10
Nodes (19): compilerOptions, allowArbitraryExtensions, allowImportingTsExtensions, erasableSyntaxOnly, jsx, lib, module, moduleDetection (+11 more)

### Community 16 - "Calendar & Follow Models"
Cohesion: 0.16
Nodes (4): CalendarEvent, Follow, Message, MovieNightInvite

### Community 17 - "Primary Button & Forgot Password"
Cohesion: 0.13
Nodes (6): ButtonProps, CommonProps, LinkProps, AUTH_SWAP_STATE, AuthSwapState, STEPS

### Community 18 - "App Shell, Router & Lenis"
Cohesion: 0.17
Nodes (6): LogoProps, RootLayout(), PlaceholderProps, router, lenis, react-router

### Community 19 - "Magnet Interaction"
Cohesion: 0.17
Nodes (16): arbitrate(), attach(), DEFAULT_SPRING, Entry, field, Magnet(), wake(), MagnetAxis (+8 more)

### Community 20 - "Frontend TS Node Config"
Cohesion: 0.12
Nodes (16): compilerOptions, allowImportingTsExtensions, erasableSyntaxOnly, lib, module, moduleDetection, noEmit, noFallthroughCasesInSwitch (+8 more)

### Community 21 - "Root Repo & Husky Setup"
Cohesion: 0.12
Nodes (16): author, description, devDependencies, husky, keywords, license, main, name (+8 more)

### Community 22 - "Sign Up & Year-in-Film Card"
Cohesion: 0.14
Nodes (4): OrDivider(), YearInFilmCard(), Errors, PROVIDERS

### Community 23 - "Reviews & Sentiment"
Cohesion: 0.18
Nodes (5): Review, ReviewCard(), Sentiment(), SentimentType, VARIANTS

### Community 24 - "Auth Component Calls"
Cohesion: 0.33
Nodes (14): AuthShell(), AuthWaves(), ButtonPrimary(), Checkbox(), Logo(), PosterCardMini(), SocialButton(), SplitHeadline() (+6 more)

### Community 26 - "Core Laravel Migrations"
Cohesion: 0.23
Nodes (6): {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}(), {closure#3}()

### Community 27 - "Person Page & App Shell Calls"
Cohesion: 0.21
Nodes (5): AppNav(), MovieTile(), Person(), PersonPage(), getPerson()

### Community 28 - "Notifications & Password Resets"
Cohesion: 0.21
Nodes (3): Notification, PasswordReset, SocialAccount

### Community 29 - "Movie Tile & Person Data"
Cohesion: 0.18
Nodes (6): Movie, Collaborator, Credit, LENA, OTHERS, Person

### Community 30 - "Composer Package Config"
Cohesion: 0.22
Nodes (8): description, keywords, license, minimum-stability, name, prefer-stable, $schema, type

### Community 31 - "Composer Plugins & Platform"
Cohesion: 0.22
Nodes (9): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, platform, preferred-install, sort-packages (+1 more)

### Community 32 - "Composer Scripts"
Cohesion: 0.22
Nodes (9): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, pre-package-uninstall, setup (+1 more)

### Community 33 - "Calendar Export & Rating Helpers"
Cohesion: 0.33
Nodes (9): PillButton(), RatingStars(), gradientCss(), downloadIcs(), googleCalendarUrl(), MovieDetailsPage(), byMonth(), HistoryList() (+1 more)

### Community 34 - "Composer Dev Dependencies"
Cohesion: 0.25
Nodes (8): require-dev, fakerphp/faker, laravel/boost, laravel/pail, laravel/pint, mockery/mockery, nunomaduro/collision, phpunit/phpunit

### Community 35 - "Social Login Buttons"
Cohesion: 0.25
Nodes (3): PROVIDERS, SocialButtonProps, SocialProvider

### Community 36 - "Pill Button"
Cohesion: 0.25
Nodes (6): ButtonProps, CommonProps, ExternalProps, LinkProps, Never, PillVariant

### Community 37 - "Movie Data Mocks"
Cohesion: 0.25
Nodes (7): MOVIES, BY_SLUG, CastMember, Cinema, MovieDetails, NEON_TIDE, SIMILAR

### Community 40 - "Animated Auth Waves"
Cohesion: 0.33
Nodes (5): AuthWavesProps, bandPath(), LAYERS, Point, WaveSide

### Community 44 - "Composer Autoload"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 50 - "Auth Page Swap Motion"
Cohesion: 0.40
Nodes (3): AuthWaves, styles/motion.css, useAuthSwap

### Community 56 - "Composer Requirements"
Cohesion: 0.50
Nodes (4): require, laravel/framework, laravel/tinker, php

### Community 74 - "Test Autoload"
Cohesion: 0.67
Nodes (3): autoload-dev, psr-4, Tests\\

### Community 75 - "Laravel Package Discovery"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

### Community 78 - "Quality Gates (CI & Hooks)"
Cohesion: 0.67
Nodes (3): CI workflows (frontend-ci.yml, backend-ci.yml), Husky pre-commit hook, Validation (CI-equivalent checks)

## Knowledge Gaps
- **34 isolated node(s):** `robots.txt (allow all)`, `Google Fonts (DM Sans, Outfit)`, `frontend index.html entry (root div, /src/main.tsx)`, `Type-aware ESLint configuration`, `Laravel Boost bootstrap guidelines (AGENTS.md)` (+29 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 519 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **45 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `react` connect `Auth Shell & Form Controls` to `Lists, List Detail & Poster Collage`, `Frontend Dependencies & Lint`, `Landing, Chips & Trending Card`, `Movie Details Page`, `Discover Page & Filters`, `Home Page`, `For You Page & Live Weather`, `Sign In & Poster Stack`, `SplitText Headline Animation`, `Watchlist & History Page`, `Primary Button & Forgot Password`, `App Shell, Router & Lenis`, `Magnet Interaction`, `Sign Up & Year-in-Film Card`, `Reviews & Sentiment`, `Person Page & App Shell Calls`, `Pill Button`, `App Navigation Header`, `Animated Auth Waves`?**
  _High betweenness centrality (0.086) - this node is a cross-community bridge._
- **What connects `robots.txt (allow all)`, `Google Fonts (DM Sans, Outfit)`, `frontend index.html entry (root div, /src/main.tsx)` to the rest of the system?**
  _34 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Lists, List Detail & Poster Collage` be split into smaller, more focused modules?**
  _Cohesion score 0.0602322206095791 - nodes in this community are weakly interconnected._
- **Why does `react-router` connect `App Shell, Router & Lenis` to `Lists, List Detail & Poster Collage`, `Frontend Dependencies & Lint`, `Pill Button`, `Landing, Chips & Trending Card`, `Movie Details Page`, `App Navigation Header`, `Discover Page & Filters`, `Home Page`, `For You Page & Live Weather`, `Sign In & Poster Stack`, `Watchlist & History Page`, `Primary Button & Forgot Password`, `Sign Up & Year-in-Film Card`, `Person Page & App Shell Calls`, `Movie Tile & Person Data`?**
  _High betweenness centrality (0.054) - this node is a cross-community bridge._
- **Should `CI, Docs & Project Overview` be split into smaller, more focused modules?**
  _Cohesion score 0.045068027210884355 - nodes in this community are weakly interconnected._
- **Why does `@vitejs/plugin-react` connect `Backend Vite/Tailwind Tooling` to `Frontend Dependencies & Lint`?**
  _High betweenness centrality (0.026) - this node is a cross-community bridge._
- **Should `Frontend Dependencies & Lint` be split into smaller, more focused modules?**
  _Cohesion score 0.049494949494949494 - nodes in this community are weakly interconnected._