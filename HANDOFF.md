# WatchWise — Handoff

_Last updated: 2026-10-22 · branch `fix/update-graph-and-HANDOFF` (based on `dev` after PR #15)_

WatchWise is the "Modern Movie Discovery & Social Platform" from the Web2 (WEB2PROG302-EC10) project brief.
So far the work covers **(1) the full product design in Figma**, **(2) twelve frontend screens in React**, **(3) the Laravel database schema and a first API endpoint (weather)** and **(4) a graphify knowledge graph of the codebase**.
See also the root [README.md](README.md) for the public project overview and setup.

---

## 1. Quick links

| What | Where |
|---|---|
| GitHub repo | https://github.com/Ramykhb/WatchWise (PRs target `dev`) |
| Figma design file | https://www.figma.com/design/7Et0yexqzDoEn2MigUyfpE (everything is on **Page 1**) |
| Codebase graph | `graphify-out/graph.html` (open in a browser) · `graphify-out/GRAPH_REPORT.md` |
| Project overview & setup | `README.md` (root), `frontend/README.md`, `backend/README.md`, `backend/CLAUDE.md` |
| Project brief | `Web2_CCE_Project.docx` (kept outside the repo, in the author's Downloads folder) |

---

## 2. Figma design (done)

### Brand & system
- **Name/logo:** WatchWise. Owl mark (owl = wise, wide eyes = watching, right pupil is a play button). `Logo` component has **Light** (cream/white backgrounds) and **Dark** (violet backgrounds) variants; `Logo/Mark` is the owl on its own.
- **Style:** cream ground, deep-violet "ground" panels, coral/yellow/mint accents, wavy organic shapes, floating cards with soft drop shadows. Type: **Outfit** (Black/Bold) for display, **DM Sans** for body.
- **Tokens:** local variable collection `Color` (e.g. `ground/cream #FFF3E3`, `ground/violet #2B1A63`, `accent/coral #FF5A4E`, `accent/yellow #FFC93C`, `ink/900 #16122B`, `border/subtle #DBD5D1`, tints). Effect styles `Elevation/Float` and `Elevation/Soft`.
- **Components** (frame `Components`, node `2:15`): Button/Primary, Pill Button (Solid/Outline/Soft/Accent), Button/Social, Input, Divider/Or, Chip, Avatar, Rating/Stars, Sentiment, Movie Tile, Poster Card/Mini, Movie Card/Trending, Review Card, Stat Card, Section Header, Nav/App, Admin/Sidebar, Logo, Icon/* brand icons. Hover variants exist for the buttons and movie tiles.

### Screens (23 frames + hub) — every requirement in the brief has a screen
✅ = built in React (see §3).

| Area | Frame (node id) |
|---|---|
| Visitor | ✅ Landing `2:68` |
| Auth | ✅ Sign Up `5:120` · ✅ Sign In `3:93` · ✅ Forgot Password `25:286` |
| App | ✅ Home `25:369` · ✅ Discover `27:503` · ✅ Movie Details `28:794` · ✅ Person `27:848` · ✅ For You `30:1340` · ✅ Watchlist & History `30:1046` · ✅ Lists `32:1443` · ✅ List Detail `32:1681` · Create/Edit List modal `32:1944` · Profile `33:1538` · Settings `33:1786` · Nearby Cinemas `45:1698` · Discussion Rooms `45:1882` · Movie Nights `46:1778` · Movie Night Detail `46:2063` |
| Admin | Overview `47:1905` · Users `47:2173` · Review Moderation `49:2046` · Movie Data `49:2356` |
| Extras | WatchWise logo sheet `20:192` · **Flow Hub — Start here** `53:2763` |

### Prototype
- ~240 click links, Smart Animate for header/sidebar and Sign in ⇄ Sign up, Push-left drill-downs, Move-in/out for the Create List modal, hover states, entrance keyframes on Landing, auth pages, Home and Movie Details.
- **Flows** (Present → pick one): `0 · Review tour (start here)`, `1 · Visitor`, `2 · Auth`, `3 · Signed-in app`, `4 · Admin dashboard`. The pinned "Tour n/23 · Next →" bar is a review aid only, not app UI.

---

## 3. Frontend (`frontend/`, in progress)

**Stack:** React 19 + TypeScript + Vite 8, React Router 7, plain CSS Modules with design tokens, **GSAP + @gsap/react**, **Lenis**, **React Bits** (copied components), **axios** (backend calls). No Tailwind.

### Built screens
| Route | Page | Notes |
|---|---|---|
| `/` | Landing | Hero, magnetic Get Started, Trending card, sticker, toast, wavy backgrounds |
| `/signin`, `/signup` | Sign In / Sign Up | Social buttons, validation, Show/Hide password; swap animation (below) |
| `/forgot-password` | Forgot Password | Email validation, step chips (Request → Check email), preview cards |
| `/Home-Page` | Home | Signed-in home, mock data. **Reached by URL** until auth exists |
| `/discover` | Discover | Search & filters (`discoverData.ts`) |
| `/movies/:slug` | Movie Details | Reviews, ratings, sentiment, cinemas map (`movieData.ts`) |
| `/people/:slug` | Person | Actor/director page (`personData.ts`) |
| `/for-you` | For You | Recommendations + **live weather** from the backend (`/api/weather`, browser geolocation, Beirut fallback) |
| `/lists`, `/lists/:slug` | Lists / List Detail | Public/private lists (`listsData.ts`, `listDetailData.ts`) |
| `/lists/watchlist` | Watchlist & History | Under Lists so the Lists nav link is active; `/watchlist` redirects here |
| everything else | Placeholder | "Coming soon" / 404 so every link works (`lists/new`, `profile`, `rooms`, `movie-nights`, `cinemas`, …) |

Every page except For You's weather uses **mock data** in a `*Data.ts` file next to the page — replace those with API calls.

### Behaviour worth knowing
- **Fit to viewport (desktop ≥ 1025px):** `FitToViewport` scales single-screen pages (Landing, auth) to fit the window with no scrolling, using compact `[data-fit]` spacing. Long app pages (Home, Discover, …) scroll normally with Lenis.
- **Entrance motion:** `useEntranceAnimation` (GSAP) animates `[data-enter]` / `data-enter-delay` / `data-enter-rise` (`data-enter="pop"` scales in). Headlines use React Bits `SplitText` via `SplitHeadline`. `Magnet` on key buttons. Everything respects `prefers-reduced-motion`.
- **Auth page swap (Sign in ⇄ Sign up ⇄ Forgot password):** React Router `viewTransition` links with `AUTH_SWAP_STATE`; on a swap the entrance is skipped (`useAuthSwap`) and named layers morph (`styles/motion.css`):
  - Each poster card (`card-last-lap`, `card-paper-moon`, `card-neon-tide`), the year card, sticker and forgot-password cards have their **own names** — unnamed content lives in the root snapshot, which paints *below* every named layer, so anything that must stay on top or move needs a name. Names must be unique per page or the browser skips the transition.
  - The coral/violet waves are **`AuthWaves`**: inline SVG bands whose path GSAP tweens from the previous page's side to the new one; the `auth-waves` layer shows the live new page instead of cross-fading two snapshots.
- **Shared app pieces:** `AppNav` (signed-in header, active link via `NavLink`, search → `/discover?q=`), `MovieTile` (set `--poster-w` for non-196px tiles), `PillButton` (`to` / `href` / button), `RatingStars`, `ReviewCard`, `Sentiment`, `PosterCollage`.
- **Forms:** client-side validation only; submit handlers have `TODO`s for `POST /api/login`, `/api/register`, `/api/forgot-password` and Socialite redirects.

### File map (`frontend/src`)
```
main.tsx, router.tsx, index.css (.sr-only utility)
layouts/RootLayout.tsx            Lenis + GSAP ticker, <Outlet/>
lib/gsap.ts                       registers GSAP plugins, EASE_OUT_BACK
styles/tokens.css                 colors, shadows, fonts (mirrors Figma variables)
styles/motion.css                 view-transition names + auth swap rules
hooks/  useEntranceAnimation · useAuthSwap · useViewportFit · useFitScale
components/
  AuthShell, AuthWaves, FitToViewport          auth layout + motion
  AppNav, MovieTile, PillButton, ButtonPrimary, Chip, Logo, ...
  RatingStars, ReviewCard, Sentiment, PosterCollage, PosterCardMini, YearInFilmCard, TrendingCard
  TextField, Checkbox, OrDivider, SocialButton, SplitHeadline
  reactbits/SplitText, reactbits/Magnet       (React Bits sources)
pages/  Landing · SignIn · SignUp · ForgotPassword · Home · Discover · MovieDetails · Person
        ForYou · Lists · ListDetail · Watchlist · Placeholder · authPosters.ts
assets/<page>/                    SVG/PNG assets exported from Figma, one folder per page
```
Env: `frontend/.env` with `VITE_BACKEND_URL=http://localhost:8000` (see `.env.example`).

---

## 4. Backend (`backend/`, Laravel 12)

- **Schema (PR #7):** migrations + models for users, social accounts, reviews, watchlists, user preferences, discussion rooms & messages, movie lists & items, follows, activity feed, movie nights (invites, candidates, votes), calendar events, notifications, password resets.
- **API (PR #15):** `GET /api/weather?latitude=&longitude=` (`WeatherController`) proxies Open-Meteo; falls back to Beirut when no coordinates; 502 JSON if the service is down. Config in `config/services.php` → `OPEN_METEO_*` in `.env`. Tests: `tests/Feature/WeatherTest.php`.
- **Not yet:** auth endpoints, TMDB catalog, reviews/lists/watchlist APIs, Socialite, real-time rooms.
- `backend/CLAUDE.md` holds the Laravel Boost guidelines; `backend/.claude/skills/` holds agent skills (excluded from the graph).

---

## 5. Codebase knowledge graph (graphify)

[graphify](https://github.com/safishamsi/graphify) maps the repo into a queryable knowledge graph. Output lives in `graphify-out/`:

| File | What it is |
|---|---|
| `graph.html` | interactive graph — open in any browser, no server |
| `GRAPH_REPORT.md` | audit report: god nodes, communities, surprising links, suggested questions |
| `graph.json` | raw graph data (GraphRAG-ready) |
| `manifest.json`, `.graphify_labels.json`, `cost.json` | state for incremental updates, community names, token cost log |

**Latest update (2026-10-22, on `dev` after PR #15):** 131 code files + docs → **961 nodes, 1605 edges, 89 communities** (44 shown in the report), health check clean.
- Code is extracted structurally (AST, no tokens). Docs (HANDOFF, READMEs, CLAUDE.md, CI workflows) go through an LLM pass; this update re-read HANDOFF, README and `backend/CLAUDE.md` (~90k tokens, logged in `cost.json`).
- **Most connected nodes:** `react`, `SplitHeadline()`, `react-router`, `User` (Laravel model), `useEntranceAnimation()`, `ButtonPrimary()`, `AppNav()`, `Magnet()`.
- **Excluded:** `.graphifyignore` keeps `backend/.claude/` and `.claude/` (agent tooling) out. Figma image assets are not extracted (cost vs. value): their imports appear as bare `frontend_src_assets_*` nodes and the images are listed as "new" on every update. `backend/.npmrc` and `styles/tokens.css` are skipped as "sensitive" by name only.
- When a doc is rewritten, graphify refuses to save because its old concepts disappear; that is expected — save with `to_json(..., force=True)` after checking the new graph.
- Machine-specific files (`graphify-out/cache/`, `.graphify_python`, `.graphify_root`) are git-ignored.

**Use it**
```bash
graphify query "how does the auth page swap work?"   # answer from the graph
graphify explain "AuthWaves()"                         # a node and its neighbours
graphify path "router" "ForYou()"                      # shortest path between two nodes
graphify update .                                      # re-extract changed code (no LLM)
```
In Claude Code, `/graphify . --update` rebuilds incrementally (docs need the LLM pass).

## 6. Decisions made along the way
- **Plain CSS Modules + tokens** instead of Tailwind (matches the existing Vite setup).
- **Single-screen desktop pages fit the window** rather than scroll; long app pages scroll.
- **GSAP, Lenis and React Bits are the default** for UI motion. React Bits components are copied into `components/reactbits`, not installed from npm.
- **Facebook login added** to the auth pages because the brief lists "gmail, github, fb".
- **Home lives at `/Home-Page`** and is opened by URL until login is wired up.
- **External APIs go through Laravel** (e.g. weather), never straight from the browser, so keys stay server-side.
- Figma's looping timeline previews were implemented as **play-once entrances**.

## 7. Working agreements
- **The user commits, pushes and opens PRs themselves.** Make and verify changes, then leave them uncommitted and say they're ready. Don't create or delete branches.
- Feature branches come off `dev`; PRs go into `dev`.
- After a pull, run `npm install` in `frontend/` — new dependencies (e.g. axios in PR #15) otherwise break the build with "Cannot find module".

## 8. Current state & known issues
- `npm run build` / `npm run lint` pass (after `npm install`). The husky **pre-commit** hook runs the frontend build and `php artisan --version`.
- Main JS bundle is **730 kB (226 kB gzip)** → Vite size warning. Fix: lazy-load routes (`React.lazy` / route `lazy`) — now worth doing with 12 pages.
- Animations were checked frame by frame in a throttled preview pane; **live motion should still be checked in a real browser**.
- Line-ending warnings (LF → CRLF) appear on commit; harmless, a `.gitattributes` would silence them.
- The GitHub CLI (`gh`) is not installed on the dev machine; PRs are opened from compare links.

## 9. Connections & tooling
- **GitHub:** `git@github.com:Ramykhb/WatchWise.git`.
- **CI:** `.github/workflows/frontend-ci.yml` (npm ci → `tsc --noEmit` → `npm run build`) and `backend-ci.yml` run on pushes/PRs.
- **Figma MCP:** both Figma accounts are **Starter plan / View seats = 20 MCP calls per month each**. Pull a frame with one `get_design_context` call (it includes the screenshot); skip `get_motion_context` unless the motion matters.
- **Fonts:** Google Fonts (Outfit 700/900, DM Sans 400/500/700) loaded in `frontend/index.html`.
- **Dev server in Claude Code:** `.claude/launch.json` defines `frontend` (`npm --prefix frontend run dev`, port 5173).
- **graphify:** CLI via `uv tool` plus a Claude Code skill in `~/.claude/skills/graphify`.

## 10. Suggested next steps
1. Build the remaining app screens from Figma: Create/Edit List modal → Profile → Settings → Movie Nights + Detail → Discussion Rooms → Nearby Cinemas (node ids in §2), then the four Admin screens.
2. Laravel auth (register/login/logout, Sanctum), then connect Sign in / Sign up / Forgot Password and replace `/Home-Page` with a real post-login redirect.
3. TMDB integration through the backend; swap the `*Data.ts` mocks for API calls page by page.
4. Code-split routes to clear the bundle warning.
5. Re-run graphify (`/graphify . --update`) after each feature so the graph stays current.

## 11. Run it
```bash
# frontend
cd frontend
npm install
npm run dev      # http://localhost:5173
npm run build    # type-check + production build
npm run lint

# backend (second terminal)
cd backend
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan serve   # http://localhost:8000
php artisan test
```
