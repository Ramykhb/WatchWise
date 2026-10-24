# WatchWise — Handoff

_Last updated: 2026-10-24 · branch `feat/user-info` (user data from the database: profiles, follows, lists, watchlist, notifications, rooms, movie nights)_

WatchWise is the "Modern Movie Discovery & Social Platform" from the Web2 (WEB2PROG302-EC10) project brief.
So far the work covers **(1) the full product design in Figma**, **(2) every Figma screen in React (23, including the four Admin screens) plus a Notifications page**, **(3) a Laravel API with the database schema, JWT authentication (email/password, verification, password reset, Google/GitHub/Discord sign-in), live weather and Gemini weather-mood recommendations, plus the user data API (profiles, follows, lists, watchlist, notifications, rooms, movie nights)**, wired into the frontend so signed-in pages show the user's own data, and **(4) a graphify knowledge graph of the codebase**.
See also the root [README.md](README.md) for the public project overview and setup.

---

## 1. Quick links

| What | Where |
|---|---|
| GitHub repo | https://github.com/Ramykhb/WatchWise (PRs target `dev`) |
| Figma design file | https://www.figma.com/design/7Et0yexqzDoEn2MigUyfpE (everything is on **Page 1**) |
| Codebase graph | `graphify-out/graph.html` (open in a browser) · `graphify-out/GRAPH_REPORT.md` |
| Project overview & setup | `README.md` (root), `frontend/README.md`, `backend/README.md`, `backend/CLAUDE.md` |
| Backend auth guide | `backend/Backend.md` (architecture, routes, config, rate limits, security notes, follow-ups) |
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
| App | ✅ Home `25:369` · ✅ Discover `27:503` · ✅ Movie Details `28:794` · ✅ Person `27:848` · ✅ For You `30:1340` · ✅ Watchlist & History `30:1046` · ✅ Lists `32:1443` · ✅ List Detail `32:1681` · ✅ Create/Edit List modal `32:1944` · ✅ Profile `33:1538` · ✅ Settings `33:1786` · ✅ Nearby Cinemas `45:1698` · ✅ Discussion Rooms `45:1882` · ✅ Movie Nights `46:1778` · ✅ Movie Night Detail `46:2063` |
| Admin | ✅ Overview `47:1905` · ✅ Users `47:2173` · ✅ Review Moderation `49:2046` · ✅ Movie Data `49:2356` |
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
| `/signin`, `/signup` | Sign In / Sign Up | **Live**: `POST /api/auth/login` / `register` through `lib/auth.ts`; Google/GitHub/Discord buttons redirect to `/api/auth/social/{provider}`; validation, Show/Hide password; swap animation (below) |
| `/auth/callback` | Auth Callback | Landing page after social sign-in: reads `?status=`, loads `/api/auth/me`, then goes to `/home-page` |
| `/forgot-password` | Forgot Password | **Live**: `POST /api/auth/forgot-password`; step chips (Request → Check email), preview cards |
| `/home-page` | Home | **Live** (`GET /api/home`): greeting with the user's name, "From people you follow" activity, busiest rooms, your next movie night; the Watchlist button adds Tonight's pick. Picks/carousels/releases are still mock movies |
| `/discover` | Discover | Search & filters (`discoverData.ts`) |
| `/movies/:slug` | Movie Details | Reviews, ratings, sentiment, cinemas map (`movieData.ts`) |
| `/people/:slug` | Person | Actor/director page (`personData.ts`) |
| `/for-you` | For You | **Live weather + Gemini mood**: `/api/weather` (browser geolocation, Beirut fallback), then `POST /api/weather-mood` turns the weather into a viewing mood; recommendation rows still use mock movies |
| `/lists`, `/lists/:slug` | Lists / List Detail | **Live** (`/api/lists`): My lists, other members' Public lists, and Following (public lists of people you follow). URLs are `/lists/{id}-{title-slug}`. Only the owner sees reorder/remove/add on List Detail; changes are saved with `PATCH /api/lists/{id}`. `myListsStore` keeps the user's lists in sync across pages. Likes / list followers were dropped (no table for them) |
| `/lists/new`, `/lists/:slug/edit` | Create/Edit List dialog | `ListDialog`, opens over the Lists page; `POST` / `PATCH /api/lists` with the films in order |
| `/lists/watchlist` | Watchlist & History | **Live** (`/api/watchlist`): queue, mark as watched, remove, history with your review ratings, "What you watch" bars from watched genres. Availability filters wait for TMDB |
| `/profile`, `/profile/:handle` | Profile | **Live** (`GET /api/users/{username}`): `/profile` = the signed-in user (**Edit profile**, Log out); others show Follow/Unfollow (`POST/DELETE /api/users/{username}/follow`, which notifies them). Activity, reviews, lists, watched, top 4 (highest rated), favourite genres, followers/following. The owner's privacy switches and profile visibility (public / followers / private) are applied by the backend |
| `/settings` | Settings | **Live** (`/api/me`): profile (name, @username, email, location, bio), photo upload/remove, password change (signs out other devices), visibility + privacy toggles, connected accounts (disconnect; connect = provider sign-in), integrations, delete account (asks for the password) |
| `/discover/cinemas` | Nearby Cinemas | Illustrated Figma map with pins, popup, zoom; cinemas / movie-locations modes, filters, showtimes, Google Maps directions, "Use my location" (`cinemasData.ts`). Under Discover so that nav link is active; `/cinemas` redirects here and Discover links to it from the "In cinemas near me" filter |
| `/movie-nights` | Movie Nights | **Live** (`/api/movie-nights`): pending invitations (accept/decline), upcoming/past nights, **Plan a movie night** (invite people you follow by @username, starting shortlist) — guests get an invite notification |
| `/movie-nights/:id` | Movie Night | **Live**: RSVP (going / maybe / can't go → notifies the host), one vote each (suggesting a film votes for it), host-only Invite more and Close/Reopen voting, Google Calendar link, share |
| `/rooms`, `/rooms/:roomId` | Discussion Rooms | **Live** (`/api/rooms`): rooms you posted in first, create room, messages (polled every 5 s — no real-time channel yet), `@username` mentions notify that member, members list with links to profiles. "Agree" reactions are local only |
| `/notifications` | Notifications | **Live** (`/api/notifications`): grouped feed (Today / Yesterday / This week / Earlier), tabs, mark read/unread, dismiss, mark all read, Follow back, "Notify me about" switches saved in preferences. The header bell polls every minute (`notificationsStore`) |
| `/admin`, `/admin/users`, `/admin/reviews`, `/admin/movies` | Admin dashboard | `AdminLayout` (sidebar + nested routes): Overview (stats, activity chart, genres, top movies), Users (table, filters, bulk actions), Review Moderation (sentiment, approve/remove/warn), Movie Data (TMDB sync status, edit drawer). Mock data in `adminData.ts`; **no admin role check in the frontend yet** |
| everything else | Placeholder | "Coming soon" / 404 so every link works (`releases`, `terms`, `privacy`, `movies/:slug/credits`, …) |

Everything that belongs to a user now comes from the database. **Still mock:** movie/person/discover/cinemas data and the Home/For You movie rows (waiting for TMDB) and the Admin pages.

**Films are TMDB ids.** Until TMDB is merged, `lib/movies.ts` maps ids **1001–1008** to the eight demo movies (the seeder uses the same ids); any other id shows a neutral "Film #id" poster. Swap `movieFor()` for real TMDB data later.

**Data layer:** `lib/api.ts` = axios for `/api` (cookies; a 401 refreshes the token once and retries) + `useApi(path)` hook (`data`, `error`, `loading`, `setData`, `reload`). API shapes are typed in `lib/types.ts`; each page's `*Data.ts` now holds the adapter from API data to what its components draw. Avatars use initials + a colour picked from the user id (`lib/format.ts`), or the uploaded photo.

**Route guards (`components/RouteGuards/RouteGuards.tsx`, used in `router.tsx`):** `RequireGuest` wraps Landing and the auth pages (a signed-in user is sent to `/home-page`); `RequireAuth` wraps everything else (a guest is sent to `/signin`, remembering where they were going). Both wait for `AuthContext` to finish loading the session. All paths are lowercase.

### Behaviour worth knowing
- **Fit to viewport (desktop ≥ 1025px):** `FitToViewport` scales single-screen pages (Landing, auth) to fit the window with no scrolling, using compact `[data-fit]` spacing. Long app pages (Home, Discover, …) scroll normally with Lenis.
- **Entrance motion:** `useEntranceAnimation` (GSAP) animates `[data-enter]` / `data-enter-delay` / `data-enter-rise` (`data-enter="pop"` scales in). Headlines use React Bits `SplitText` via `SplitHeadline`. `Magnet` on key buttons. Everything respects `prefers-reduced-motion`.
- **Auth page swap (Sign in ⇄ Sign up ⇄ Forgot password):** React Router `viewTransition` links with `AUTH_SWAP_STATE`; on a swap the entrance is skipped (`useAuthSwap`) and named layers morph (`styles/motion.css`):
  - Each poster card (`card-last-lap`, `card-paper-moon`, `card-neon-tide`), the year card, sticker and forgot-password cards have their **own names** — unnamed content lives in the root snapshot, which paints *below* every named layer, so anything that must stay on top or move needs a name. Names must be unique per page or the browser skips the transition.
  - The coral/violet waves are **`AuthWaves`**: inline SVG bands whose path GSAP tweens from the previous page's side to the new one; the `auth-waves` layer shows the live new page instead of cross-fading two snapshots.
- **Shared app pieces:** `AppNav` (signed-in header, active link via `NavLink`, search → `/discover?q=`, bell → `/notifications` with unread badge), `MovieTile` (set `--poster-w` for non-196px tiles), `PillButton` (`to` / `href` / button, `disabled`), `Toggle` (48×28 switch, `role="switch"`), `RatingStars`, `ReviewCard`, `Sentiment`, `PosterCollage`.
- **Highlighting a nav link from a sub-page:** nest the route under the section (`/lists/watchlist`, `/discover/cinemas`) and `NavLink` marks it active; add a `Navigate` redirect for the short URL.
- **Scrolling to a section:** Lenis owns the page scroll, so `element.scrollIntoView()` gets overridden. Use `useLenis()` and `lenis.scrollTo(element, { offset })` (see `Settings.tsx`). Inner scroll areas need `data-lenis-prevent`.
- **Auth session:** `AuthProvider` (`contexts/AuthContext.tsx`) loads `GET /api/auth/me` on start and falls back to `POST /api/auth/refresh`. Tokens live in **HTTP-only cookies** (axios `withCredentials`), so the frontend never stores a token. `useAuth()` (`contexts/useAuth.ts`) exposes `user` (incl. `username`, `avatar`, `bio`, `location`), `loading`, `setUser` and `logout`; logout also clears the notifications store. API errors become messages through `apiErrorMessage()` in `lib/auth.ts`.
- **Saving pattern:** buttons change at once and the request runs in the background; a failed request puts the old state back (follows, watchlist, notifications, list order).

### File map (`frontend/src`)
```
main.tsx, router.tsx (routes), index.css (.sr-only utility)
layouts/RootLayout.tsx            Lenis + GSAP ticker, <Outlet/>
contexts/AuthContext.tsx          AuthProvider (session from cookies); contexts/useAuth.ts = context + useAuth
lib/auth.ts                       axios instance for /api/auth, AuthUser type, apiErrorMessage
lib/api.ts                        axios instance for /api (token refresh on 401) + useApi hook
lib/types.ts                      API response types · lib/movies.ts TMDB id → demo movie · lib/format.ts avatars, dates
lib/gsap.ts                       registers GSAP plugins, EASE_OUT_BACK
styles/tokens.css                 colors, shadows, fonts (mirrors Figma variables)
styles/motion.css                 view-transition names + auth swap rules
hooks/  useEntranceAnimation · useAuthSwap · useViewportFit · useFitScale
components/
  AuthShell, AuthWaves, FitToViewport          auth layout + motion
  AppNav, MovieTile, PillButton, Toggle, ButtonPrimary, Chip, Logo, RouteGuards, ...
  RatingStars, ReviewCard, Sentiment, PosterCollage, PosterCardMini, YearInFilmCard, TrendingCard
  TextField, Checkbox, OrDivider, SocialButton, SplitHeadline
  reactbits/SplitText, reactbits/Magnet       (React Bits sources)
pages/  Landing · SignIn · SignUp · ForgotPassword · Home · Discover · MovieDetails · Person
        ForYou · Lists (+ ListDialog) · ListDetail · Watchlist · Profile · Settings · Cinemas
        MovieNights (+ NightDetail) · Rooms · Notifications · AuthCallback · Placeholder · authPosters.ts
        Admin/ (AdminLayout + Overview · Users · Reviews · Movies, adminData.ts)
assets/<page>/                    SVG/PNG assets exported from Figma, one folder per page
```
Env: `frontend/.env` with `VITE_BACKEND_URL=http://localhost:8000` (see `.env.example`).

---

## 4. Backend (`backend/`, Laravel 12)

- **Schema (PR #7):** migrations + models for users, social accounts, reviews, watchlists, user preferences, discussion rooms & messages, movie lists & items, follows, activity feed, movie nights (invites, candidates, votes), calendar events, notifications, password resets.
- **Auth module (PR #23 + Discord follow-up), `app/Auth/`:** layered (Controllers → Services → Repositories, with DTOs, Form Requests, Exceptions and Middleware). Full guide: **`backend/Backend.md`**.
  - Endpoints (all under `/api/auth`): `register`, `login`, `refresh`, `logout`, `me` (JWT), `verify-email/{id}/{hash}` (signed link), `resend-verification`, `forgot-password`, `reset-password`, `social/{google|github|discord}` + `/callback`.
  - **JWT** (`firebase/php-jwt`) in HTTP-only cookies: access token 15 min, refresh token 30 days (rotated, stored hashed). Every route is rate-limited (`throttle:auth-*`).
  - Social: Google and GitHub through **Socialite**; **Discord through a manual OAuth flow** in `SocialAuthController`. The redirect returns **503** when a provider's credentials are missing or still `CHANGE_ME`. After the callback the user lands on the frontend's `/auth/callback`.
  - Middleware: `auth.jwt`, optional auth, `EnsureEmailVerified`, `EnsureRole` (role is on `users.role`). **Policies** for messages, lists, list items, movie nights, notifications, reviews, users, preferences and watchlists.
- **Weather (PR #15):** `GET /api/weather?latitude=&longitude=` proxies Open-Meteo, falls back to Beirut, returns 502 JSON if the service is down (`OPEN_METEO_*`).
- **Gemini (merged from `gemini-integeration-test`):** `GeminiService` + `POST /api/weather-mood` (weather → viewing mood) and `GET /api/movie-recommendations`. Config `services.gemini` → `GEMINI_API_KEY`, `GEMINI_MODEL` (**not yet listed in `.env.example`**).
- **User data API (`feat/user-info`), `routes/social.php`, all behind `auth.jwt`:**
  - `GET /api/home` — friends' activity, busiest rooms, your next movie night.
  - `GET|PATCH|DELETE /api/me`, `PATCH /api/me/preferences` (genres + `privacy` / `integrations` / `notifications` switches), `PUT /api/me/password` (revokes every refresh token), `POST|DELETE /api/me/avatar` (public disk → run `php artisan storage:link`), `DELETE /api/me/social/{provider}`.
  - `GET /api/users?q=` (search), `GET /api/users/{username}` (profile; `restricted: true` when private / followers-only and you can't see it), `.../followers`, `.../following`, `POST|DELETE .../follow`.
  - `apiResource lists` (`?scope=mine|public|following`; `tmdb_ids` replaces the films in order), `apiResource watchlist`.
  - `GET /api/notifications`, `PATCH /api/notifications/{id}` (`read`), `POST /api/notifications/read-all`, `DELETE /api/notifications/{id}`.
  - `GET|POST /api/rooms`, `GET /api/rooms/{room}/messages?after=`, `POST /api/rooms/{room}/messages`.
  - `GET|POST /api/movie-nights`, `GET|PATCH /api/movie-nights/{night}`, `POST .../invites`, `PUT .../rsvp`, `PUT .../vote`.
  - Controllers in `app/Http/Controllers` (Profile, Me, MovieList, Watchlist, Notification, Room, MovieNight, Home), JSON shapes in `app/Http/Resources`, existing **policies** do the authorization (`UserPolicy` gained `follow` and followers-only visibility).
  - **Links between users:** `App\Services\Notifier` writes notifications for follows, movie night invites / RSVPs / votes and `@mentions` in rooms (`data` holds the sentence + link + `actor_id`). `App\Services\ActivityFeedService` records list / watchlist / review activity and builds the Profile and Home feeds.
  - **New migrations (2026-10-24):** `users.username` (unique, generated from the name on create and backfilled) + `users.location`, `profile_visibility` now a string (`public` / `followers` / `private`); `user_preferences.settings` (JSON); `movie_list_items.position` + `note`; `discussion_rooms.created_by` + `spoilers`; `movie_nights.note`; invite status string (adds `maybe`).
  - **Demo data:** `php artisan db:seed` runs `DemoSocialSeeder` — six members who follow each other (maya, linasaad, omark, jadn, ranah, samt `@watchwise.test`; password in the seeder's doc comment) with lists, watchlists, reviews, rooms, movie nights and notifications.
  - Authentication now runs **before** route model binding (`bootstrap/app.php`), so guests get 401, not 404.
- **Tests:** `php artisan test` — **100 tests** (auth + 16 new in `tests/Feature/Social`: profiles, privacy, follows, lists, watchlist, notifications, rooms/mentions, movie nights).
- **Not yet:** TMDB catalog (remote `feat/back-tmdb-movies`, unmerged), review writing/moderation endpoints, list likes, real-time rooms (Reverb), admin APIs.
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

**Latest update (2026-10-24, `feat/user-info`: user data API and wired pages; code re-extracted with `graphify update .`):** current node / edge / community counts are in the Summary of `graphify-out/GRAPH_REPORT.md` (kept out of this file so updating the graph doesn't change the doc it reads).
- Code is extracted structurally (AST, no tokens). Docs (HANDOFF, READMEs, CLAUDE.md, CI workflows) go through an LLM pass; token cost per run is logged in `cost.json`.
- **Most connected nodes:** `User` (model), `CurrentUser` (auth), `react`, `AppNav()`, `react-router`, `SplitHeadline()`, `gradientCss()`, `useEntranceAnimation()`, `MovieNight`.
- `graphify update .` only re-extracts code. The doc nodes for this HANDOFF are from the previous LLM pass; run `/graphify . --update` to refresh them, and `graphify label` to rename communities (45 were renamed after their hub node automatically). Backups it writes (`graphify-out/20*/`) are git-ignored.
- **Flagged:** one AMBIGUOUS edge: the README roadmap still lists Laravel authentication as not done.
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
- **Social login providers are Google, GitHub and Discord.**
- **Home lives at `/home-page`** and requires a signed-in session.
- **External APIs go through Laravel** (e.g. weather), never straight from the browser, so keys stay server-side.
- Figma's looping timeline previews were implemented as **play-once entrances**.

## 7. Working agreements
- **The user commits, pushes and opens PRs themselves.** Make and verify changes, then leave them uncommitted and say they're ready. Don't create or delete branches.
- When building several pages, finish and verify **one page at a time** and stop so the user can commit it before the next.
- Feature branches come off `dev`; PRs go into `dev`.
- After a pull, run `npm install` in `frontend/` — new dependencies (e.g. axios in PR #15) otherwise break the build with "Cannot find module".

## 8. Current state & known issues
**Checked on 2026-10-24** (`feat/user-info`):
- ✅ Frontend `tsc`, `npm run lint` (the 3 Fast Refresh errors are fixed: guards moved to `components/RouteGuards`, `useAuth` to `contexts/useAuth.ts`) and `npm run build` pass.
- ✅ Backend: **100 / 100 tests pass** locally. `AccountFlowsTest::test_social_redirect_sets_state_cookie` passes only when real Google credentials are in `.env`; on a clean checkout it gets **503** — set fake ones in the test (`config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret'])`). **Backend CI only runs `composer install` + `php artisan --version`**, so add `php artisan test` (and frontend lint) to CI.
- ✅ Checked in the browser as the demo user `maya`: Home, Profile (own + others, follow → notification), Lists, Watchlist, Notifications, Rooms (send + @mention → notification), Movie Nights and Settings load and save real data.
- ⚠️ `php artisan serve` handles one request at a time, so pages that fire several requests fill in slowly in dev; fine in production (php-fpm).
- ⚠️ After pulling, run **`composer install`** in `backend/` (new `firebase/php-jwt` + `laravel/socialite`); with a stale `vendor/`, 20 tests fail with `Class "Firebase\JWT\JWT" not found`. Same for `npm install` in `frontend/`.
- ⚠️ `/terms` and `/privacy` sit inside `RequireAuth`, but the Sign Up form links to them, so a guest clicking them is sent to Sign in. Move them (and the 404) out of the guarded group.
- ⚠️ `/admin/*` only requires a signed-in user; there is no admin role check in the frontend (the backend has `EnsureRole`, but no admin APIs yet).
- The husky **pre-commit** hook runs the frontend build and `php artisan --version`.
- Main JS bundle is **950 kB (280 kB gzip)** → Vite size warning. Fix: lazy-load routes (`React.lazy` / route `lazy`), worth doing with 25+ routes.
- In stacked mobile layouts, a panel with `flex: 1` and a fixed height collapses or grows (seen on Cinemas and Rooms); set `flex: none` in the media query.
- Movie Details' in-page jump buttons use `scrollIntoView`, which Lenis may override (the bug fixed in Settings); not checked yet.
- Animations were checked frame by frame in a throttled preview pane; **live motion should still be checked in a real browser**.
- Line-ending warnings (LF → CRLF) appear on commit; harmless, a `.gitattributes` would silence them.
- The GitHub CLI (`gh`) is not installed on the dev machine; PRs are opened from compare links.

## 9. Connections & tooling
- **GitHub:** `git@github.com:Ramykhb/WatchWise.git`.
- **CI:** `.github/workflows/frontend-ci.yml` (npm ci → `tsc --noEmit` → `npm run build`) and `backend-ci.yml` run on pushes/PRs.
- **Figma MCP:** both Figma accounts are **Starter plan / View seats = 20 MCP calls per month each**. Pull a frame with one `get_design_context` call (it includes the screenshot); skip `get_motion_context` unless the motion matters.
- **Fonts:** Google Fonts (Outfit 700/900, DM Sans 400/500/700) loaded in `frontend/index.html`.
- **Dev server in Claude Code:** `.claude/launch.json` defines `frontend` (`npm --prefix frontend run dev`, port 5173). It's only a convenience for the Claude desktop preview; the project builds and runs without it, so it can be removed (`git rm -r .claude` + ignore it).
- **graphify:** CLI via `uv tool` plus a Claude Code skill in `~/.claude/skills/graphify`.

## 10. Suggested next steps
1. Fix the review findings in §8: the 3 lint errors, the failing social-redirect test and guest access to `/terms` / `/privacy`; add tests and lint to CI.
2. Gate `/admin` on `user.role === 'admin'` in the frontend and add admin APIs behind `EnsureRole`.
3. Add `GEMINI_API_KEY` / `GEMINI_MODEL` to `backend/.env.example`; finish the auth follow-ups in `backend/Backend.md` (captcha, GitHub verified-email check, real SMTP/OAuth testing).
4. Merge the TMDB work (`feat/back-tmdb-movies`), replace `movieFor()` in `lib/movies.ts` with real movie data and TMDB search in the pickers, and swap the remaining movie mocks (Discover, Movie Details, Person, For You rows).
   Add a real-time channel (e.g. Laravel Reverb) for room messages and notifications instead of polling; add review writing, list likes and admin APIs.
5. Code-split routes to clear the bundle warning.
6. Re-run graphify (`/graphify . --update`) after each feature so the graph stays current.

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
composer install        # re-run after every pull that touches composer.lock
cp .env.example .env && php artisan key:generate
# then set JWT_SECRET, FRONTEND_URL, the OAuth client ids/secrets and GEMINI_API_KEY in .env
php artisan migrate
php artisan db:seed       # demo members + their lists, rooms, nights, notifications
php artisan storage:link  # serves uploaded profile photos
php artisan serve   # http://localhost:8000
php artisan test
```
