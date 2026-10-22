# Graph Report - Web2-Project  (2026-10-22)

## Corpus Check
- 310 files · ~52,455 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 1070 nodes · 1785 edges · 97 communities (51 shown, 46 thin omitted)
- Extraction: 99% EXTRACTED · 1% INFERRED · 0% AMBIGUOUS · INFERRED: 15 edges (avg confidence: 0.8)
- Token cost: 78,320 input · 0 output

## Community Hubs (Navigation)
- Social Buttons & Settings Toggles
- Frontend Dependencies & Lint
- SplitText Headline Animation
- User Model & Relations
- Nearby Cinemas Map
- Weather API (Open-Meteo)
- Discover Page & Movie Tile
- Profile Page
- Movie Details Page
- List Detail Page
- Backend Vite/Tailwind Tooling
- Auth Page Components
- Home Page
- Ratings & Review Cards
- Sign Up & Poster Stack
- Chips & Trending Card
- Watchlist & History Page
- Signed-in Page Shells
- Buttons & Text Fields
- Frontend TS App Config
- Calendar & Follow Models
- Lists Page & Movie Mocks
- Landing Page
- Magnet Interaction
- Frontend TS Node Config
- Root Repo & Husky Setup
- App Navigation & Logo
- Auth Shell & Page Fit
- Pill Button
- Discussion Rooms & Movie Nights
- Core Laravel Migrations
- Notifications & Password Resets
- App Shell, Router & Lenis
- Project Status & Tooling
- Laravel Boost Guidelines
- Composer Package Config
- Composer Plugins & Platform
- Composer Scripts
- Frontend Patterns & New Pages
- CI Workflows
- Composer Dev Dependencies
- Movie Data Mocks
- Poster Collage
- App Service Provider
- Framework Conventions
- Animated Auth Waves
- Design System & Styling
- Movie List Model
- Movie Night Candidates
- Movie Night Votes
- Composer Autoload
- Cache Table Migration
- Social Accounts Migration
- Activity Feed Migration
- Weather & External APIs
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
- Checkbox
- Test Autoload
- Laravel Package Discovery
- Laravel Boost MCP
- TS Project References
- Auth Page Swap Motion
- Planned Product Capabilities
- Fonts & HTML Entry
- Contributing Guidelines
- Roadmap

## God Nodes (most connected - your core abstractions)
1. `react` - 33 edges
2. `react-router` - 27 edges
3. `SplitHeadline()` - 24 edges
4. `useEntranceAnimation()` - 23 edges
5. `User` - 22 edges
6. `compilerOptions` - 18 edges
7. `ButtonPrimary()` - 17 edges
8. `AppNav()` - 16 edges
9. `gradientCss()` - 15 edges
10. `compilerOptions` - 15 edges

## Surprising Connections (you probably didn't know these)
- `TypeScript Check and Build job (npm ci, tsc --noEmit, npm run build)` --conceptually_related_to--> `Frontend Stack (React 19, Vite, CSS Modules, GSAP, Lenis, React Bits)`  [INFERRED]
  .github/workflows/frontend-ci.yml → HANDOFF.md
- `React + TypeScript + Vite template README` --conceptually_related_to--> `Frontend Stack (React 19, Vite, CSS Modules, GSAP, Lenis, React Bits)`  [INFERRED]
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
- **Pages added on profile/settings/cinemas branch** — handoff_profile_page, handoff_settings_page, handoff_nearby_cinemas_page, handoff_mock_data_files [EXTRACTED 1.00]
- **Frontend motion system** — handoff_entrance_motion, handoff_auth_page_swap, handoff_authwaves, handoff_fit_to_viewport, handoff_lenis_scroll_to [INFERRED 0.85]
- **Live weather flow** — handoff_for_you_page, handoff_weather_api, handoff_open_meteo, handoff_external_apis_through_laravel [EXTRACTED 1.00]

## Communities (97 total, 46 thin omitted)

### Community 0 - "Social Buttons & Settings Toggles"
Cohesion: 0.06
Nodes (21): PROVIDERS, SocialButtonProps, SocialProvider, Toggle(), ToggleProps, INTEGRATION_ICONS, maskIcon(), MENU (+13 more)

### Community 1 - "Frontend Dependencies & Lint"
Cohesion: 0.05
Nodes (43): dependencies, axios, gsap, @gsap/react, lenis, react, react-dom, react-router (+35 more)

### Community 2 - "SplitText Headline Animation"
Cohesion: 0.06
Nodes (25): DEFAULT_FROM, DEFAULT_TO, fontsAreLoaded(), getEffect(), Kick, Look, SplitText(), SplitTextEffect (+17 more)

### Community 3 - "User Model & Relations"
Cohesion: 0.06
Nodes (3): User, UserFactory, DatabaseSeeder

### Community 4 - "Nearby Cinemas Map"
Cohesion: 0.07
Nodes (15): Cinemas(), directionsUrl(), Mode, placeAt(), Cinema, CINEMAS, DEFAULT_LOCATION, Feature (+7 more)

### Community 5 - "Weather API (Open-Meteo)"
Cohesion: 0.07
Nodes (8): Controller, WeatherController, {closure#1}(), {closure#2}(), {closure#3}(), ExampleTest, WeatherTest, TestCase

### Community 6 - "Discover Page & Movie Tile"
Cohesion: 0.09
Nodes (15): Discover(), fraction(), pageItems(), DEFAULT_FILTERS, Filters, GENRES, INITIAL_FILTERS, LANGUAGES (+7 more)

### Community 7 - "Profile Page"
Cohesion: 0.09
Nodes (23): ACTIVITY_ICON, ActivityCard(), ActivityHead(), GENRE_COLORS, ListPreview(), PeopleTab, PersonRow(), Profile() (+15 more)

### Community 8 - "Movie Details Page"
Cohesion: 0.07
Nodes (5): getMovieDetails(), MovieDetails(), PINS, SortKey, SORTS

### Community 9 - "List Detail Page"
Cohesion: 0.10
Nodes (16): compact(), ListDetail(), ListDetailPage(), SortKey, SORTS, ALL_MOVIES, entriesFor(), FOLLOWER_FACES (+8 more)

### Community 10 - "Backend Vite/Tailwind Tooling"
Cohesion: 0.08
Nodes (21): devDependencies, concurrently, laravel-vite-plugin, tailwindcss, @tailwindcss/vite, vite, vite, optionalDependencies (+13 more)

### Community 11 - "Auth Page Components"
Cohesion: 0.17
Nodes (16): AuthShell(), AuthWaves(), ButtonPrimary(), Checkbox(), OrDivider(), PosterCardMini(), SocialButton(), SplitHeadline() (+8 more)

### Community 12 - "Home Page"
Cohesion: 0.11
Nodes (9): ACTIVITY, BECAUSE_YOU_LOVED, G, MOVIE_NIGHT, POPULAR, RELEASES, ROOMS, TONIGHTS_PICK (+1 more)

### Community 13 - "Ratings & Review Cards"
Cohesion: 0.11
Nodes (10): RatingStars(), RatingStarsProps, Review, ReviewCard(), Sentiment(), SentimentType, VARIANTS, downloadIcs() (+2 more)

### Community 14 - "Sign Up & Poster Stack"
Cohesion: 0.11
Nodes (7): MiniPoster, PosterCardMiniProps, LAST_LAP, NEON_TIDE, PAPER_MOON, Errors, PROVIDERS

### Community 15 - "Chips & Trending Card"
Cohesion: 0.11
Nodes (8): Chip(), TrendingCard(), TrendingCardProps, TrendingMovie, GENRES, Month, MONTHS, YearInFilmCard()

### Community 16 - "Watchlist & History Page"
Cohesion: 0.13
Nodes (12): MAX_TASTE, SortKey, SORTS, Tab, Availability, FILTERS, HISTORY, HistoryEntry (+4 more)

### Community 17 - "Signed-in Page Shells"
Cohesion: 0.14
Nodes (13): AppNav(), MovieTile(), useEntranceAnimation(), ForYou(), weatherCondition(), Person(), PersonPage(), Collaborator (+5 more)

### Community 18 - "Buttons & Text Fields"
Cohesion: 0.11
Nodes (7): ButtonProps, CommonProps, LinkProps, TextFieldProps, AUTH_SWAP_STATE, AuthSwapState, STEPS

### Community 19 - "Frontend TS App Config"
Cohesion: 0.10
Nodes (19): compilerOptions, allowArbitraryExtensions, allowImportingTsExtensions, erasableSyntaxOnly, jsx, lib, module, moduleDetection (+11 more)

### Community 20 - "Calendar & Follow Models"
Cohesion: 0.16
Nodes (4): CalendarEvent, Follow, Message, MovieNightInvite

### Community 21 - "Lists Page & Movie Mocks"
Cohesion: 0.14
Nodes (8): MOVIES, Tab, FOLLOWING_COUNT, G, MY_LISTS, MyList, PUBLIC_LISTS, PublicList

### Community 22 - "Landing Page"
Cohesion: 0.12
Nodes (4): HEADLINE, NAV_LINKS, STATS, TRENDING

### Community 23 - "Magnet Interaction"
Cohesion: 0.17
Nodes (16): arbitrate(), attach(), DEFAULT_SPRING, Entry, field, Magnet(), wake(), MagnetAxis (+8 more)

### Community 24 - "Frontend TS Node Config"
Cohesion: 0.12
Nodes (16): compilerOptions, allowImportingTsExtensions, erasableSyntaxOnly, lib, module, moduleDetection, noEmit, noFallthroughCasesInSwitch (+8 more)

### Community 25 - "Root Repo & Husky Setup"
Cohesion: 0.12
Nodes (16): author, description, devDependencies, husky, keywords, license, main, name (+8 more)

### Community 26 - "App Navigation & Logo"
Cohesion: 0.16
Nodes (6): AppNavProps, LINKS, Logo(), LogoProps, Placeholder(), PlaceholderProps

### Community 27 - "Auth Shell & Page Fit"
Cohesion: 0.21
Nodes (9): ARTBOARD_WIDTH, AuthShellProps, FitToViewport(), FitToViewportProps, useFitScale(), measure(), useViewportFit(), ViewportFit (+1 more)

### Community 28 - "Pill Button"
Cohesion: 0.15
Nodes (13): ButtonProps, CommonProps, ExternalProps, LinkProps, Never, PillButton(), PillVariant, greeting() (+5 more)

### Community 30 - "Core Laravel Migrations"
Cohesion: 0.23
Nodes (6): {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}(), {closure#3}()

### Community 31 - "Notifications & Password Resets"
Cohesion: 0.21
Nodes (3): Notification, PasswordReset, SocialAccount

### Community 32 - "App Shell, Router & Lenis"
Cohesion: 0.29
Nodes (3): router, lenis, react-router

### Community 33 - "Project Status & Tooling"
Cohesion: 0.22
Nodes (10): Bundle Size Warning (route code-splitting), CI Workflows (frontend-ci, backend-ci), graphify Knowledge Graph, Laravel Auth (Sanctum) - planned, Laravel Database Schema (PR #7), Suggested Next Steps (remaining screens, auth, TMDB, code-split), WatchWise Handoff, WatchWise Movie Discovery & Social Platform (+2 more)

### Community 34 - "Laravel Boost Guidelines"
Cohesion: 0.25
Nodes (7): .ai/rules project rules, Laravel Boost Guidelines, Laravel Boost MCP Tools (database-query, database-schema, search-docs, browser-logs), Laravel Cloud Deployment, PHPUnit Testing Rules, Laravel Pint Formatter, Skills Activation (testing-best-practices, deploying-to-cloud)

### Community 35 - "Composer Package Config"
Cohesion: 0.22
Nodes (8): description, keywords, license, minimum-stability, name, prefer-stable, $schema, type

### Community 36 - "Composer Plugins & Platform"
Cohesion: 0.22
Nodes (9): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, platform, preferred-install, sort-packages (+1 more)

### Community 37 - "Composer Scripts"
Cohesion: 0.22
Nodes (9): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, pre-package-uninstall, setup (+1 more)

### Community 38 - "Frontend Patterns & New Pages"
Cohesion: 0.22
Nodes (6): Figma Component Library, Mock *Data.ts Files, Nearby Cinemas Page, Profile Page, Settings Page, Shared App Components (AppNav, MovieTile, PillButton, Toggle, ...)

### Community 39 - "CI Workflows"
Cohesion: 0.25
Nodes (8): Backend CI Workflow, Laravel Checks job (PHP 8.4, composer install, artisan --version), Frontend CI Workflow, TypeScript Check and Build job (npm ci, tsc --noEmit, npm run build), Laravel Boost bootstrap guidelines (AGENTS.md), robots.txt (allow all), Laravel Boost, Laravel Framework (default README)

### Community 40 - "Composer Dev Dependencies"
Cohesion: 0.25
Nodes (8): require-dev, fakerphp/faker, laravel/boost, laravel/pail, laravel/pint, mockery/mockery, nunomaduro/collision, phpunit/phpunit

### Community 41 - "Movie Data Mocks"
Cohesion: 0.25
Nodes (7): Movie, BY_SLUG, CastMember, Cinema, MovieDetails, NEON_TIDE, SIMILAR

### Community 42 - "Poster Collage"
Cohesion: 0.32
Nodes (6): gradientCss(), PosterCollage(), PosterCollageProps, Gradient, Lists(), PublicListCard()

### Community 44 - "Framework Conventions"
Cohesion: 0.33
Nodes (5): Laravel 12 Streamlined Structure (bootstrap/app.php, casts() method), React + TypeScript + Vite template README, Type-aware ESLint configuration, Frontend Stack (React 19, Vite, CSS Modules, GSAP, Lenis, React Bits), Backend Stack (Laravel 12, PHP 8.2+, Eloquent, SQLite, PHPUnit)

### Community 45 - "Animated Auth Waves"
Cohesion: 0.33
Nodes (5): AuthWavesProps, bandPath(), LAYERS, Point, WaveSide

### Community 46 - "Design System & Styling"
Cohesion: 0.33
Nodes (5): Design Tokens (Color variables, Elevation styles), Figma Design File, Figma MCP Call Limits (20/month), Figma Prototype Flows, WatchWise Owl Logo

### Community 50 - "Composer Autoload"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 55 - "Weather & External APIs"
Cohesion: 0.40
Nodes (4): For You Page (live weather), Open-Meteo, TMDB Integration - planned, Weather API (GET /api/weather, WeatherController)

### Community 61 - "Composer Requirements"
Cohesion: 0.50
Nodes (4): require, laravel/framework, laravel/tinker, php

### Community 80 - "Test Autoload"
Cohesion: 0.67
Nodes (3): autoload-dev, psr-4, Tests\\

### Community 81 - "Laravel Package Discovery"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

### Community 85 - "Planned Product Capabilities"
Cohesion: 0.67
Nodes (3): External Services (TMDB, weather, maps, sentiment, calendar, real-time rooms), Planned Product Capabilities (auth, discovery, reviews, watchlist, recommendations, lists, social, movie nights, admin), WatchWise README

## Knowledge Gaps
- **35 isolated node(s):** `vite`, `@types/node`, `@types/react`, `@types/react-dom`, `typescript` (+30 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 576 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **46 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `react` connect `Auth Shell & Page Fit` to `Social Buttons & Settings Toggles`, `Frontend Dependencies & Lint`, `SplitText Headline Animation`, `Nearby Cinemas Map`, `Discover Page & Movie Tile`, `Profile Page`, `Movie Details Page`, `List Detail Page`, `Auth Page Components`, `Home Page`, `Ratings & Review Cards`, `Sign Up & Poster Stack`, `Chips & Trending Card`, `Watchlist & History Page`, `Signed-in Page Shells`, `Buttons & Text Fields`, `Lists Page & Movie Mocks`, `Landing Page`, `Magnet Interaction`, `App Navigation & Logo`, `Pill Button`, `App Shell, Router & Lenis`, `Animated Auth Waves`, `Checkbox`?**
  _High betweenness centrality (0.090) - this node is a cross-community bridge._
- **What connects `vite`, `@types/node`, `@types/react` to the rest of the system?**
  _35 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Social Buttons & Settings Toggles` be split into smaller, more focused modules?**
  _Cohesion score 0.058279370952821465 - nodes in this community are weakly interconnected._
- **Why does `react-router` connect `App Shell, Router & Lenis` to `Social Buttons & Settings Toggles`, `Frontend Dependencies & Lint`, `SplitText Headline Animation`, `Nearby Cinemas Map`, `Discover Page & Movie Tile`, `Profile Page`, `Movie Details Page`, `List Detail Page`, `Auth Page Components`, `Home Page`, `Sign Up & Poster Stack`, `Chips & Trending Card`, `Watchlist & History Page`, `Signed-in Page Shells`, `Buttons & Text Fields`, `Lists Page & Movie Mocks`, `Landing Page`, `App Navigation & Logo`, `Pill Button`?**
  _High betweenness centrality (0.065) - this node is a cross-community bridge._
- **Should `Frontend Dependencies & Lint` be split into smaller, more focused modules?**
  _Cohesion score 0.049494949494949494 - nodes in this community are weakly interconnected._
- **Should `SplitText Headline Animation` be split into smaller, more focused modules?**
  _Cohesion score 0.06090808416389812 - nodes in this community are weakly interconnected._
- **Should `User Model & Relations` be split into smaller, more focused modules?**
  _Cohesion score 0.06072874493927125 - nodes in this community are weakly interconnected._