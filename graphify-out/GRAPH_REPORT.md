# Graph Report - Web2-Project  (2026-10-22)

## Corpus Check
- Corpus is ~22,458 words - fits in a single context window. You may not need a graph.

## Summary
- 487 nodes · 667 edges · 40 communities (22 shown, 18 thin omitted)
- Extraction: 98% EXTRACTED · 2% INFERRED · 0% AMBIGUOUS · INFERRED: 13 edges (avg confidence: 0.82)
- Token cost: 81,188 input · 0 output

## Community Hubs (Navigation)
- Frontend Dependencies & Lint
- Composer Package Config
- Landing Page & Assets
- CI, Hooks & Project Docs
- User Model & Laravel Config
- Backend Vite/Tailwind Tooling
- SplitText Headline Animation
- Database Migrations
- Frontend TS App Config
- Sign Up Page & Assets
- Sign In Page & Checkbox
- Magnet Interaction
- Frontend TS Node Config
- Root Repo & Husky Setup
- Buttons, Logo & Placeholder
- Auth Shell & Page Fit
- Auth Form Controls
- App Shell, Router & Lenis
- Laravel Bootstrap & Entry
- Composer Plugins & Platform
- Social Login Buttons
- Mini Poster Card
- Viewport Fit Scaling
- App Service Provider
- Feature Test Setup
- Unit Test Example
- TS Project References
- Base Controller

## God Nodes (most connected - your core abstractions)
1. `compilerOptions` - 18 edges
2. `react` - 17 edges
3. `compilerOptions` - 15 edges
4. `SignUp()` - 13 edges
5. `react-router` - 12 edges
6. `Magnet()` - 12 edges
7. `SignIn()` - 12 edges
8. `AuthShell()` - 10 edges
9. `scripts` - 9 edges
10. `ButtonPrimary()` - 9 edges

## Surprising Connections (you probably didn't know these)
- `Husky post-commit hook (frontend build + php artisan --version)` --semantically_similar_to--> `Laravel Checks job (PHP 8.4, composer install, artisan --version)`  [INFERRED] [semantically similar]
  HANDOFF.md → .github/workflows/backend-ci.yml
- `Husky post-commit hook (frontend build + php artisan --version)` --semantically_similar_to--> `TypeScript Check and Build job (npm ci, tsc --noEmit, npm run build)`  [INFERRED] [semantically similar]
  HANDOFF.md → .github/workflows/frontend-ci.yml
- `React + TypeScript + Vite template README` --conceptually_related_to--> `Frontend Stack (React 19, TypeScript, Vite 8, React Router 7, CSS Modules)`  [INFERRED]
  frontend/README.md → HANDOFF.md
- `Laravel Checks job (PHP 8.4, composer install, artisan --version)` --references--> `Laravel Framework (default README)`  [INFERRED]
  .github/workflows/backend-ci.yml → backend/README.md
- `TypeScript Check and Build job (npm ci, tsc --noEmit, npm run build)` --conceptually_related_to--> `Frontend Stack (React 19, TypeScript, Vite 8, React Router 7, CSS Modules)`  [INFERRED]
  .github/workflows/frontend-ci.yml → HANDOFF.md

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **WatchWise CI / commit quality gates** — _github_workflows_backend_ci_laravel_checks, _github_workflows_frontend_ci_typescript_check_and_build, handoff_husky_post_commit_hook [INFERRED 0.85]
- **WatchWise frontend motion system** — handoff_gsap_lenis_react_bits, handoff_use_entrance_animation, handoff_auth_swap_view_transition, handoff_fit_to_viewport [EXTRACTED 1.00]

## Communities (40 total, 18 thin omitted)

### Community 0 - "Frontend Dependencies & Lint"
Cohesion: 0.05
Nodes (40): dependencies, gsap, @gsap/react, lenis, react, react-dom, react-router, devDependencies (+32 more)

### Community 1 - "Composer Package Config"
Cohesion: 0.05
Nodes (39): autoload, autoload-dev, psr-4, psr-4, description, extra, laravel, keywords (+31 more)

### Community 2 - "Landing Page & Assets"
Cohesion: 0.06
Nodes (12): Chip(), TrendingCard(), TrendingCardProps, TrendingMovie, GENRES, Month, MONTHS, YearInFilmCard() (+4 more)

### Community 3 - "CI, Hooks & Project Docs"
Cohesion: 0.09
Nodes (26): Backend CI Workflow, Laravel Checks job (PHP 8.4, composer install, artisan --version), Frontend CI Workflow, TypeScript Check and Build job (npm ci, tsc --noEmit, npm run build), Laravel Boost bootstrap guidelines (AGENTS.md), Laravel Boost bootstrap guidelines (CLAUDE.md), robots.txt (allow all), Laravel Boost (+18 more)

### Community 4 - "User Model & Laravel Config"
Cohesion: 0.10
Nodes (3): User, UserFactory, DatabaseSeeder

### Community 5 - "Backend Vite/Tailwind Tooling"
Cohesion: 0.08
Nodes (21): devDependencies, concurrently, laravel-vite-plugin, tailwindcss, @tailwindcss/vite, vite, vite, optionalDependencies (+13 more)

### Community 6 - "SplitText Headline Animation"
Cohesion: 0.12
Nodes (16): DEFAULT_FROM, DEFAULT_TO, fontsAreLoaded(), getEffect(), Kick, Look, SplitText(), SplitTextEffect (+8 more)

### Community 7 - "Database Migrations"
Cohesion: 0.16
Nodes (8): {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}(), {closure#1}(), {closure#2}(), {closure#3}()

### Community 8 - "Frontend TS App Config"
Cohesion: 0.10
Nodes (19): compilerOptions, allowArbitraryExtensions, allowImportingTsExtensions, erasableSyntaxOnly, jsx, lib, module, moduleDetection (+11 more)

### Community 9 - "Sign Up Page & Assets"
Cohesion: 0.12
Nodes (6): AUTH_SWAP_STATE, AuthSwapState, useAuthSwap(), Errors, PAPER_MOON, PROVIDERS

### Community 10 - "Sign In Page & Checkbox"
Cohesion: 0.11
Nodes (7): Checkbox(), CheckboxProps, Errors, LAST_LAP, NEON_TIDE, PAPER_MOON, PROVIDERS

### Community 11 - "Magnet Interaction"
Cohesion: 0.17
Nodes (16): arbitrate(), attach(), DEFAULT_SPRING, Entry, field, Magnet(), wake(), MagnetAxis (+8 more)

### Community 12 - "Frontend TS Node Config"
Cohesion: 0.12
Nodes (16): compilerOptions, allowImportingTsExtensions, erasableSyntaxOnly, lib, module, moduleDetection, noEmit, noFallthroughCasesInSwitch (+8 more)

### Community 13 - "Root Repo & Husky Setup"
Cohesion: 0.12
Nodes (16): author, description, devDependencies, husky, keywords, license, main, name (+8 more)

### Community 14 - "Buttons, Logo & Placeholder"
Cohesion: 0.16
Nodes (8): ButtonPrimary(), ButtonProps, CommonProps, LinkProps, Logo(), LogoProps, Placeholder(), PlaceholderProps

### Community 15 - "Auth Shell & Page Fit"
Cohesion: 0.26
Nodes (9): ARTBOARD_WIDTH, AuthShell(), AuthShellProps, FitToViewport(), SplitHeadline(), useEntranceAnimation(), useFitScale(), Landing() (+1 more)

### Community 16 - "Auth Form Controls"
Cohesion: 0.24
Nodes (7): OrDivider(), PosterCardMini(), SocialButton(), TextField(), TextFieldProps, SignIn(), SignUp()

### Community 17 - "App Shell, Router & Lenis"
Cohesion: 0.31
Nodes (5): RootLayout(), router, lenis, react-dom, react-router

### Community 18 - "Laravel Bootstrap & Entry"
Cohesion: 0.33
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 19 - "Composer Plugins & Platform"
Cohesion: 0.22
Nodes (9): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, platform, preferred-install, sort-packages (+1 more)

### Community 20 - "Social Login Buttons"
Cohesion: 0.25
Nodes (3): PROVIDERS, SocialButtonProps, SocialProvider

### Community 22 - "Viewport Fit Scaling"
Cohesion: 0.38
Nodes (4): FitToViewportProps, measure(), useViewportFit(), ViewportFit

## Knowledge Gaps
- **15 isolated node(s):** `concurrently`, `tailwindcss`, `vite`, `@laravel/multiplex`, `@types/node` (+10 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 290 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **18 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `react` connect `Auth Shell & Page Fit` to `Frontend Dependencies & Lint`, `Landing Page & Assets`, `SplitText Headline Animation`, `Sign Up Page & Assets`, `Sign In Page & Checkbox`, `Magnet Interaction`, `Auth Form Controls`, `App Shell, Router & Lenis`, `Mini Poster Card`, `Viewport Fit Scaling`?**
  _High betweenness centrality (0.086) - this node is a cross-community bridge._
- **What connects `concurrently`, `tailwindcss`, `vite` to the rest of the system?**
  _15 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Frontend Dependencies & Lint` be split into smaller, more focused modules?**
  _Cohesion score 0.053426248548199766 - nodes in this community are weakly interconnected._
- **Why does `react-router` connect `App Shell, Router & Lenis` to `Frontend Dependencies & Lint`, `Landing Page & Assets`, `Sign Up Page & Assets`, `Sign In Page & Checkbox`, `Buttons, Logo & Placeholder`?**
  _High betweenness centrality (0.055) - this node is a cross-community bridge._
- **Should `Composer Package Config` be split into smaller, more focused modules?**
  _Cohesion score 0.05 - nodes in this community are weakly interconnected._
- **Why does `@vitejs/plugin-react` connect `Backend Vite/Tailwind Tooling` to `Frontend Dependencies & Lint`?**
  _High betweenness centrality (0.047) - this node is a cross-community bridge._
- **Should `Landing Page & Assets` be split into smaller, more focused modules?**
  _Cohesion score 0.059379217273954114 - nodes in this community are weakly interconnected._

## Run notes
- Images (42 Figma-exported SVG/PNG assets) were detected but not semantically extracted; edges that reference them appear as dangling endpoints.
- Graph health: 69 dangling-endpoint edges, 7 collapsed edges.
- Skipped as sensitive by name: backend/.npmrc, frontend/src/styles/tokens.css (design tokens, not secrets).
- Token cost: docs subagent used 81,188 tokens total (input/output split not reported); code extraction is AST-only (0 tokens).
