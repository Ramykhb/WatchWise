# WatchWise — Handoff

_Last updated: 2026-10-22 · current branch `feat/add-graphify` (based on `dev` after PR #4)_

WatchWise is the "Modern Movie Discovery & Social Platform" from the Web2 (WEB2PROG302-EC10) project brief.
So far the work covers **(1) the full product design in Figma**, **(2) the first three frontend screens in React** and **(3) a graphify knowledge graph of the codebase**.
The Laravel backend in `backend/` has not been touched by this work.

---

## 1. Quick links

| What | Where |
|---|---|
| GitHub repo | https://github.com/Ramykhb/WatchWise |
| Figma design file | https://www.figma.com/design/7Et0yexqzDoEn2MigUyfpE (everything is on **Page 1**) |
| Open PR for this branch | https://github.com/Ramykhb/WatchWise/compare/dev...feat/add-graphify |
| Codebase graph | `graphify-out/graph.html` (open in a browser) · `graphify-out/GRAPH_REPORT.md` |
| Project brief | `Web2_CCE_Project.docx` (kept outside the repo, in the author's Downloads folder) |

---

## 2. Figma design (done)

### Brand & system
- **Name/logo:** WatchWise. Owl mark (owl = wise, wide eyes = watching, right pupil is a play button). `Logo` component has **Light** (cream/white backgrounds) and **Dark** (violet backgrounds) variants; `Logo/Mark` is the owl on its own.
- **Style:** cream ground, deep-violet "ground" panels, coral/yellow/mint accents, wavy organic shapes, floating cards with soft drop shadows. Type: **Outfit** (Black/Bold) for display, **DM Sans** for body.
- **Tokens:** local variable collection `Color` (e.g. `ground/cream #FFF3E3`, `ground/violet #2B1A63`, `accent/coral #FF5A4E`, `accent/yellow #FFC93C`, `ink/900 #16122B`, `border/subtle #DBD5D1`, tints). Effect styles `Elevation/Float` and `Elevation/Soft`.
- **Components** (frame `Components`, node `2:15`): Button/Primary, Pill Button (Solid/Outline/Soft/Accent), Button/Social (Google/GitHub/Facebook icon swap), Input, Divider/Or, Chip, Avatar, Rating/Stars, Sentiment (Positive/Neutral/Negative), Movie Tile, Poster Card/Mini, Movie Card/Trending, Review Card, Stat Card, Section Header, Nav/App, Admin/Sidebar, Logo, Icon/* brand icons. Hover variants exist for the buttons and movie tiles.

### Screens (23 frames + hub) — every requirement in the brief has a screen

| Area | Frame (node id) |
|---|---|
| Visitor | Landing `2:68` |
| Auth | Sign Up `5:120` · Sign In `3:93` · Forgot Password `25:286` |
| App | Home `25:369` · Discover / Search & Filter `27:503` · Movie Details `28:794` · Person (actor/director) `27:848` · For You (recommendations + weather) `30:1340` · Watchlist & History `30:1046` · Lists `32:1443` · List Detail `32:1681` · Create/Edit List modal `32:1944` · Profile (follow, followers, activity) `33:1538` · Settings (password, privacy, connected accounts, integrations) `33:1786` · Nearby Cinemas (map) `45:1698` · Discussion Rooms (live chat) `45:1882` · Movie Nights `46:1778` · Movie Night Detail (voting, RSVP) `46:2063` |
| Admin | Overview `47:1905` · Users `47:2173` · Review Moderation `49:2046` · Movie Data `49:2356` |
| Extras | WatchWise logo sheet `20:192` · **Flow Hub — Start here** `53:2763` |

### Prototype
- ~240 click links: app header and admin sidebar on every page, posters → Movie Details, lists → List Detail, etc.
- Transitions: Smart Animate for header/sidebar and Sign in ⇄ Sign up, Push-left for drill-downs, Move-in/out for the Create List modal. Hover states on buttons/posters. Entrance keyframes on Landing, auth pages, Home and Movie Details.
- **Flows** (Present → pick one): `0 · Review tour (start here)`, `1 · Visitor (Landing)`, `2 · Auth`, `3 · Signed-in app`, `4 · Admin dashboard`. Every page has a pinned "Tour n/23 · Next →" bar for the guided review.

---

## 3. Frontend code (in progress)

**Stack:** React 19 + TypeScript + Vite 8 (`frontend/`), React Router 7, plain CSS Modules with design tokens, **GSAP + @gsap/react**, **Lenis**, **React Bits** (copied components). No Tailwind.

### Built screens (match the Figma frames)
| Route | Page | Notes |
|---|---|---|
| `/` | Landing | Hero, Get Started (magnetic), Trending Now card, sticker, toast, wavy backgrounds |
| `/signin` | Sign In | Google/GitHub/Facebook buttons, email + password (Show/Hide), Remember me, validation |
| `/signup` | Sign Up | Name/email/password rules, Terms checkbox, year-in-film card, validation |
| `/forgot-password`, `/terms`, `/privacy`, `/discover`, `/lists`, `/community`, `*` | Placeholder | "Coming soon" / 404 screen so every link works |

### Behaviour worth knowing
- **Fit to viewport (desktop ≥ 1025px):** `FitToViewport` scales the whole page so it fits the window with no scrolling (built for laptops with 125–150% Windows scaling). In this mode pages use a compact vertical spacing (`[data-fit]` CSS) and side margins grow with width. Below 1025px pages use their normal responsive, scrolling layout.
- **Animations:**
  - Entrance: `useEntranceAnimation` (GSAP) animates any element with `data-enter` / `data-enter-delay` / `data-enter-rise` (`data-enter="pop"` for scale-in). Timings come from the Figma prototype; CSS tilts are preserved.
  - Headlines: React Bits `SplitText` via `SplitHeadline` (letters rise in, lift on hover).
  - `Magnet` (React Bits) on Get Started and the header Sign in/Sign up pills.
  - Sign in ⇄ Sign up: React Router `viewTransition` links + `view-transition-name`s in `styles/motion.css`; on a swap the entrance and letter animation are skipped (`useAuthSwap`).
  - Lenis smooth scroll in `RootLayout`, driven by GSAP's ticker and synced to ScrollTrigger.
  - Everything respects `prefers-reduced-motion`.
- **Forms:** client-side validation only. Submitting and social login are **TODO** — see `handleSubmit` / `handleSocial` in `SignIn.tsx` and `SignUp.tsx` (expected: `POST /api/login`, `POST /api/register`, Laravel Socialite redirects).

### File map (`frontend/src`)
```
main.tsx, router.tsx, index.css
layouts/RootLayout.tsx            Lenis + GSAP ticker, <Outlet/>
lib/gsap.ts                       registers GSAP plugins, EASE_OUT_BACK
styles/tokens.css                 colors, shadows, fonts (mirrors Figma variables)
styles/motion.css                 view-transition names for the auth swap
hooks/  useEntranceAnimation · useAuthSwap · useViewportFit · useFitScale
components/
  AuthShell                       shared split layout for Sign in/up (fit + entrance)
  FitToViewport                   desktop scale-to-fit wrapper
  Logo, ButtonPrimary, Chip, TrendingCard, SocialButton, TextField,
  Checkbox, OrDivider, PosterCardMini, YearInFilmCard, SplitHeadline
  reactbits/SplitText, reactbits/Magnet   (React Bits sources, commit e12c221)
pages/  Landing · SignIn · SignUp · Placeholder
assets/landing, assets/auth       SVG/PNG assets exported from Figma
```

### Branch / PR history
| PR / branch | Content | Status |
|---|---|---|
| #1 `feat/added-gitignore` | root .gitignore | merged |
| #2 `feat/front-landing-page` | Landing page + React Router | merged into `dev` |
| #3 `feat/front-auth-pages` | Sign in/Sign up + animations, fit-to-viewport, zoom & margins | merged into `dev` |
| #4 `feat/front-end-libraries` | GSAP, Lenis, React Bits wired into all three pages; HANDOFF.md | merged into `dev` |
| `feat/add-graphify` | graphify knowledge graph (`graphify-out/`), HANDOFF.md update | **current branch, PR not opened** |

---

## 4. Codebase knowledge graph (graphify)

[graphify](https://github.com/safishamsi/graphify) maps the repo into a queryable knowledge graph. Output lives in `graphify-out/`:

| File | What it is |
|---|---|
| `graph.html` | interactive graph — open in any browser, no server |
| `GRAPH_REPORT.md` | audit report: god nodes, communities, surprising links, suggested questions |
| `graph.json` | raw graph data (GraphRAG-ready) |
| `manifest.json`, `.graphify_labels.json`, `cost.json` | state for incremental `--update`, community names, token cost log |

**Latest build (2026-10-22, on `dev` after PR #4):** 118 files detected (67 code, 9 docs, 42 images) → **487 nodes, 667 edges, 40 communities**.
- Code was extracted structurally (AST, no tokens); the 9 docs (HANDOFF, READMEs, CI workflows, backend AGENTS/CLAUDE.md, index.html) went through one extraction pass (~81k tokens).
- **Most connected nodes:** the two TS `compilerOptions`, `react`, `SignUp()`, `react-router`, `Magnet()`, `SignIn()`, `AuthShell()`, `ButtonPrimary()`.
- **Notable links:** the husky post-commit hook duplicates the checks of both CI jobs; hyperedges group "CI / commit quality gates" and the "frontend motion system" (GSAP+Lenis+React Bits, entrance hook, auth view-transition swap, fit-to-viewport).
- **Known gaps:** the 42 Figma image assets were not extracted (cost vs. value), so their imports show up as 69 dangling edges in the health check; 19 Laravel config files yield no symbols. `backend/.npmrc` and `styles/tokens.css` were skipped as "sensitive" by name only.
- Machine-specific files (`graphify-out/cache/`, `.graphify_python`, `.graphify_root`) are git-ignored.

**Use it**
```bash
graphify query "how does the auth page swap work?"   # answer from the graph
graphify explain "AuthShell()"                         # a node and its neighbours
graphify path "router" "SignUp()"                      # shortest path between two nodes
graphify update .                                      # re-extract changed code (no LLM)
```
In Claude Code, `/graphify . --update` rebuilds incrementally (docs/images need the LLM pass).

## 5. Decisions made along the way
- **Plain CSS Modules + tokens** instead of Tailwind (matches the existing Vite setup).
- **Desktop pages fit the window** rather than scroll; compact spacing when fitted; phones/tablets keep a normal scrolling layout.
- **GSAP, Lenis and React Bits are the default** for any new UI motion/interaction (user request). React Bits components are copied into `components/reactbits`, not installed from npm.
- **Facebook login added** to the auth pages because the brief lists "gmail, github, fb".
- Figma's looping timeline preview was implemented as a **play-once entrance** in code.
- The design screenshots/tour bar in Figma are review aids only — the "Tour" pill is not part of the app UI.

## 6. Current state & known issues
- `npm run build` / `npm run lint` / `tsc` pass; the repo's husky post-commit hook (frontend build + `php artisan --version`) passes.
- Main JS bundle is **517 kB (169 kB gzip)** → Vite size warning. Option: lazy-load the auth routes.
- The GSAP entrance was verified frame by frame; **live motion and Lenis scrolling still need a check in a real browser** (the automated preview pane was hidden).
- Line-ending warnings (LF → CRLF) appear on commit; harmless, but a `.gitattributes` would silence them.
- During this work npm briefly failed with `UNABLE_TO_VERIFY_LEAF_SIGNATURE` (HTTPS interception on the network); it was fixed on the machine, not in the repo.

## 7. Connections & tooling
- **GitHub:** `git@github.com:Ramykhb/WatchWise.git`. The GitHub CLI (`gh`) is not installed on the dev machine, so PRs are opened from the compare links (`winget install GitHub.cli` would enable it).
- **CI:** `.github/workflows/frontend-ci.yml` (npm ci → `tsc --noEmit` → `npm run build`) and `backend-ci.yml` run on pushes/PRs.
- **Figma MCP:** design work used the Figma MCP from Claude Code. Both Figma accounts used are **Starter plan / View seats = 20 MCP calls per month each**; batch work and take screenshots inside `use_figma` to save calls.
- **Fonts:** Google Fonts (Outfit, DM Sans) loaded in `frontend/index.html`.
- **graphify:** installed locally as a CLI (`graphify`, via `uv tool`) plus a Claude Code skill in `~/.claude/skills/graphify`.

## 8. Suggested next steps
1. Open the PR for `feat/add-graphify` into `dev`.
2. Check animations + Lenis in a real browser (`cd frontend && npm run dev`).
3. Connect Sign in / Sign up to the Laravel API (login, register, Socialite for Google/GitHub/Facebook) and add auth state.
4. Build the next screens from Figma in this order: Forgot Password → Home → Discover → Movie Details → the rest (node ids in §2). Reuse `AuthShell`, `FitToViewport`, `useEntranceAnimation`, `SplitHeadline` and React Bits.
5. Code-split routes to clear the bundle warning.
6. Re-run graphify (`graphify update .` or `/graphify . --update`) after each feature so the graph stays current.

## 9. Run it
```bash
cd frontend
npm install
npm run dev      # http://localhost:5173
npm run build    # type-check + production build
npm run lint
```
