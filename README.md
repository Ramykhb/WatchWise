<div align="center">
  <img src="frontend/src/assets/landing/logo-mark.svg" alt="WatchWise logo" width="96" />

  <h1>WatchWise</h1>
  <p><strong>Discover better. Watch together.</strong></p>
  <p>A movie and TV discovery platform with personalized recommendations, reviews, social profiles, collaborative movie nights, live discussion rooms, and an AI assistant.</p>

  <p>
    <a href="#features">Features</a> ·
    <a href="#technology">Technology</a> ·
    <a href="#getting-started">Getting started</a> ·
    <a href="#configuration">Configuration</a> ·
    <a href="#api-documentation">API documentation</a>
  </p>
</div>

<p align="center">
  <img src="https://img.shields.io/badge/Frontend-React%2019%20%7C%20TypeScript%20%7C%20Vite-61DAFB?style=for-the-badge&logo=react&logoColor=white" alt="React 19, TypeScript and Vite" />
  <img src="https://img.shields.io/badge/Backend-Laravel%2012%20%7C%20PHP%208.2%2B-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 12 and PHP 8.2+" />
</p>

## Overview

WatchWise is a Web Programming II university project built around discovering what to watch and sharing the experience with others. A React single-page application communicates with a Laravel REST API, with TMDB supplying movie and TV metadata, Groq powering AI features, and Laravel Reverb supporting live conversations.

This README describes the implemented application, its local setup, and the remaining integration boundaries. External features require the corresponding credentials listed below.

## Features

### Authentication and account management

- Email and password registration, sign-in, sign-out, and automatic session refresh.
- JWT access tokens and rotating refresh tokens delivered through HttpOnly cookies.
- Email verification, verification resends, and password recovery/reset flows.
- Google, GitHub, and Discord OAuth sign-in and connected-account management.
- Editable display name, username, email, location, biography, and profile photo.
- Avatar upload/removal, password changes, and account deletion.
- Profile visibility, activity privacy, and saved integration preferences.
- Protected member routes and role-based access for members, moderators, and administrators.

### Movie and TV discovery

- A personalized home page with weekly trending movies and TV shows and a weather-based movie pick.
- TMDB-backed catalog browsing, title search, genre browsing, filters, sorting, and pagination.
- Discovery views for movies, TV shows, people, and public lists.
- Movie and TV detail pages with posters, descriptions, genres, ratings, credits, and available trailers.
- Actor and director pages with biographies, filmography, and collaborators.
- Nearby cinema search using browser geolocation, Google Places results, and an interactive Mapbox map.

### Recommendations and AI assistant

- Weather-aware movie recommendations and AI-generated descriptions of the current viewing mood.
- Recommendations drawn from watched history and favorite actors/directors.
- Picks based on the viewing activity of people the member follows.
- A taste profile showing watched movie/TV totals, favorite genres, five-star rating counts, and frequently watched people.
- A floating conversational AI assistant with conversation context, streamed responses over Reverb, and failed-message retries.

### Watchlists and custom lists

- Add movies and TV shows to a personal watchlist or remove them later.
- Mark titles as watched and browse watch history and genre summaries.
- Sort the watch queue and rate watched titles.
- Create, edit, and delete custom lists with titles, descriptions, and public/private visibility.
- Search for titles to add to lists, manage membership, and browse list detail pages with poster collages.
- Discover public lists and lists from followed members.

### Ratings and reviews

- Personal star ratings for movies and TV shows.
- Create, edit, and delete reviews, with spoiler marking.
- Browse and sort reviews, load additional reviews, and mark reviews as helpful.
- Report reviews for moderation.
- Average ratings, rating distributions, and positive/neutral/negative sentiment summaries.
- Groq-based review sentiment classification with a star-rating fallback, plus automated moderation labels and a staff review queue.

### Profiles and community

- Member search, follow/unfollow actions, and follower/following lists.
- Profiles with activity, reviews, lists, watched titles, and favorite genres, subject to privacy settings.
- Share links to profiles, lists, and watchlists.
- A notification-center interface with category/unread filters, read/dismiss controls, follow-back actions, and notification preferences. See [current boundaries](#current-boundaries) for its API status.

### Collaborative movie nights

- Plan a movie night with a title, date/time, in-person location or online meeting link, invited friends, and film candidates.
- Upcoming, invitation, and past-night views.
- Accept or decline invitations, review participants and RSVP states, and vote on shortlisted movies.
- Host controls for updating the event, closing/reopening voting, selecting a winning film, and canceling the night.
- Add an event to Google Calendar through a prefilled calendar link.

### Live discussion rooms

- Create film discussion rooms, join rooms, and invite followed members.
- Accept room invitations by joining or decline them.
- Live messages through authenticated Reverb channels, with synchronization and connection feedback.
- Unread counts and read tracking.
- Scheduled cleanup of messages older than 24 hours once no member still needs to read them.

### Administration and moderation

- An overview dashboard with date-range statistics, charts, activity information, and CSV export.
- Searchable, paginated user management with role/status filters and member insights.
- Account suspension/reinstatement, administrator role management, and user deletion with permission checks.
- Review moderation with queue/status, sentiment, reason, and search filters; approval, rejection, and deletion actions.
- A local movie-catalog management screen for administrators.
- Moderators can view statistics/users, suspend or reinstate ordinary members, and moderate reviews. Movie management and user deletion are administrator-only.

### Interface and accessibility

- Responsive layouts, shared design tokens, CSS Modules, and reusable interface components.
- GSAP entrance effects and Lenis smooth scrolling.
- Animated skeletons and loading indicators for pages and asynchronous sections, with reduced-motion support.
- Accessible loading/status announcements, labeled forms, validation feedback, and retry states.

## Technology

| Layer | Stack |
| --- | --- |
| Frontend | React 19, TypeScript 6, Vite 8, React Router 7, Axios |
| Styling and motion | CSS Modules, CSS custom properties, GSAP, Lenis, Lucide icons |
| Backend | Laravel 12, PHP 8.2+, Eloquent ORM, form requests, policies, service/repository interfaces |
| Authentication | `firebase/php-jwt`, refresh-token persistence, Laravel Socialite |
| Database | SQLite by default for local development |
| Real time | Laravel Reverb, Laravel Echo, Pusher-compatible browser client |
| External data | TMDB, Open-Meteo, Google Places, Mapbox |
| AI | Groq; an optional Gemini client is also included in the backend |
| Validation | TypeScript, ESLint, PHPUnit, Laravel Pint, GitHub Actions |

## Architecture

```text
WatchWise/
├── frontend/
│   ├── src/
│   │   ├── assets/          # Branding, icons, and illustrations
│   │   ├── components/      # Shared UI, loaders, route guards, and AI assistant
│   │   ├── contexts/        # Authentication/session state
│   │   ├── hooks/           # Reusable interaction and animation hooks
│   │   ├── layouts/         # Application shell
│   │   ├── lib/             # API client, data types, caches, and shared utilities
│   │   ├── pages/           # Member and administrator screens
│   │   ├── styles/          # Design tokens and motion styles
│   │   └── router.tsx       # Application routes
│   └── .env.example
├── backend/
│   ├── app/
│   │   ├── Auth/            # Authentication and account flows
│   │   ├── Admin/           # Staff tools and statistics
│   │   ├── Models/          # Eloquent models and relationships
│   │   └── …/               # Movie, Person, Review, Room, User, and other modules
│   ├── config/              # Services, authentication, storage, and Reverb
│   ├── database/            # Migrations, factories, and seeders
│   ├── routes/              # API endpoints and scheduled commands
│   ├── tests/               # Feature and unit tests
│   └── .env.example
├── .github/workflows/       # Frontend build and backend boot checks
├── .husky/                  # Commit checks
├── HANDOFF.md              # Historical implementation handoff
└── README.md
```

Frontend requests use the backend `/api` base URL. Backend modules separate controllers, validation, DTOs/resources, business services, persistence, and authorization. Server-side service credentials belong in the backend environment; browser integrations such as Mapbox use public client configuration.

## Getting started

### Prerequisites

- Node.js 24 and npm, matching the frontend CI environment.
- PHP 8.2 or newer, Composer, and the PHP extensions required by Laravel and SQLite (`pdo_sqlite`/`sqlite3`). Backend CI uses PHP 8.4.
- Credentials for the external features you want to use; see [configuration](#configuration).

### 1. Clone and install

```bash
git clone https://github.com/Ramykhb/WatchWise.git
cd WatchWise
npm ci

cd frontend
npm ci
cp .env.example .env

cd ../backend
composer install
cp .env.example .env
php artisan key:generate
```

The root `npm ci` installs the repository's Husky commit hooks; frontend dependencies are installed separately.

### 2. Configure the backend

Edit `backend/.env` before starting the app:

- Set `JWT_SECRET` to a random value of at least 32 characters. Generate a value locally with `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'` and copy it into the environment file.
- Keep `APP_URL=http://localhost:8000` and `FRONTEND_URL=http://localhost:5173` for the default local setup.
- Replace the service placeholders needed for your demo. For local email inspection without SMTP, set `MAIL_MAILER=log`; messages are written to `backend/storage/logs/laravel.log`.
- Set `REVERB_APP_ID`, `REVERB_APP_KEY`, and `REVERB_APP_SECRET` for chat and room sockets. A local app ID can be `watchwise-local`; generate independent random values for the key and secret. Keep the example's local Reverb host, port, and scheme settings.

Then, from `backend/`:

```bash
touch database/database.sqlite
php artisan migrate
php artisan storage:link
```

The storage link exposes uploaded profile photos. Use the same hostname consistently for the frontend and backend so cookie-based authentication works as expected.

### 3. Start the application

Run each process in its own terminal, starting from the repository root.

**Backend:**

```bash
cd backend
composer run dev
```

The API runs at `http://localhost:8000`. In the local environment, the `serve` command also starts Reverb automatically, normally on port `18080`. `composer run dev` disables automatic server reload and uses the worker setting in `.env.example` so slow external requests do not block every page. If automatic socket startup fails, run `php artisan reverb:start` in a separate backend terminal.

**Frontend:**

```bash
cd frontend
npm run dev
```

Open `http://localhost:5173` (or the URL printed by Vite). If the port changes, update the configured frontend URL and OAuth settings accordingly.

**Scheduler:**

```bash
cd backend
php artisan schedule:work
```

The scheduler runs room-message cleanup every minute. On a deployed server, use a scheduler/cron process and manage Reverb as a persistent service.

### 4. Staff access

Default database seeding does not create sample users, titles, or community activity. Register your own account and use TMDB search to add real titles.

To explore staff tools, register an account and assign its role through Tinker in your local database:

```bash
php artisan tinker
```

```php
App\Models\User::where('email', 'you@example.com')->firstOrFail()->forceFill(['role' => 'admin'])->save();
```

Use `moderator` instead of `admin` to test the restricted moderation role. The application reads roles from the database for authorization.

## Configuration

| Location / variables | Purpose |
| --- | --- |
| `frontend/.env`: `VITE_BACKEND_URL` | Backend origin; defaults to `http://localhost:8000`. |
| `backend/.env`: `APP_URL`, `FRONTEND_URL` | Backend URLs, frontend redirects, mail links, and allowed frontend origin configuration. |
| `JWT_SECRET`, `JWT_ACCESS_TTL`, `JWT_REFRESH_TTL_DAYS` | JWT signing and token lifetimes; example defaults are 15 minutes and 30 days. |
| `DB_CONNECTION`, `DB_DATABASE` | Local SQLite database configuration. |
| `TMDB_API_KEY`, `TMDB_URL`, `TMDB_IMAGE_URL` | Movie/TV metadata, search, genres, people, and artwork. |
| `GROQ_API_KEY`, `GROQ_MODEL`, `GROQ_API_URL` | AI assistant, weather mood/recommendations, sentiment, and moderation. |
| `GROQ_API_KEY_2` | Separate Groq key used for watched-history recommendations. |
| `OPEN_METEO_*` | Weather API URL, default coordinates, and timezone; defaults use Beirut and `Asia/Beirut`. |
| `GOOGLE_PLACES_API_KEY` | Nearby cinema lookup. |
| Mapbox client configuration | The public access token is currently configured in `frontend/src/pages/Cinemas/Cinemas.tsx`; `VITE_MAPBOX_STYLE_URL` optionally overrides the map style. |
| `GOOGLE_*`, `GITHUB_*`, `DISCORD_*` | OAuth client IDs, client secrets, and redirect URIs. |
| `MAIL_*` | SMTP delivery for verification and password resets, or the local `log` mailer. |
| `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME` | Reverb application credentials and internal socket connection. |
| `REVERB_PUBLIC_HOST`, `REVERB_PUBLIC_PORT`, `REVERB_PUBLIC_SCHEME`, `REVERB_ALLOWED_ORIGINS` | Browser-facing socket address and allowed hostnames when different from local defaults. |
| `GEMINI_API_KEY`, `GEMINI_MODEL`, `GEMINI_URL` | Optional backend Gemini client configuration. |

OAuth callback URLs follow `http://localhost:8000/api/auth/social/{provider}/callback`, where the provider is `google`, `github`, or `discord`. Register the matching URL in each provider's developer console.

Use [backend/.env.example](backend/.env.example) and [frontend/.env.example](frontend/.env.example) as the configuration reference. Keep private credentials out of version control. Restart Vite after changing frontend environment variables; run `php artisan config:clear` after changing cached backend configuration.

## API documentation

With the backend running:

- Interactive documentation: `http://localhost:8000/api/documentation`
- OpenAPI JSON: `http://localhost:8000/api/documentation/openapi.json`
- Health endpoint: `http://localhost:8000/up`

| API group | Main capabilities |
| --- | --- |
| `/api/auth/*` | Registration, login/logout, refresh, current user, email verification, password reset, and OAuth. |
| `/api/movies/*`, `/api/genres`, `/api/people/*` | Catalog, discovery, search, trending titles, details, credits, and collaborators. |
| `/api/me/*`, `/api/users/*`, `/api/home` | Account/profile management, privacy/preferences, people search, follows, and home data. |
| `/api/watchlist`, `/api/ratings/*`, `/api/lists/*` | Saved titles, watched status, ratings, and custom lists. |
| `/api/reviews/*` | Reviews, helpful votes, reporting, and review summaries. |
| `/api/weather`, `/api/weather-mood`, recommendation endpoints, `/api/taste-profile` | Weather, AI recommendations, social recommendations, and taste insights. |
| `/api/movie-nights/*` | Events, invitations, RSVPs, voting, and host updates. |
| `/api/rooms/*`, `/api/chat/*` | Room membership/messages and AI chat with socket authentication. |
| `/api/admin/*` | Staff statistics, users, review moderation, and movie management. |

Most member and staff endpoints require authenticated cookies. External-service routes also apply rate limits. Route definitions are in [api.php](backend/routes/api.php), [auth_flows.php](backend/routes/auth_flows.php), [social.php](backend/routes/social.php), and [admin.php](backend/routes/admin.php).

## Development and validation

```bash
# Frontend, from frontend/
npm run dev       # Development server
npm run build     # TypeScript project build and production assets
npm run lint      # ESLint
npm run preview   # Serve the production build locally

# Backend, from backend/
composer run dev  # Local API and automatic Reverb startup
composer test     # Clear cached config and run feature/unit tests
vendor/bin/pint --test
php artisan route:list --path=api
```

Backend tests cover authentication, authorization, profiles/privacy, lists, watchlists, ratings/reviews, moderation, movie nights, rooms, catalog/people, recommendations, weather, chat, and OpenAPI coverage. PHPUnit is configured to use an in-memory SQLite database and isolated test settings.

GitHub Actions runs the [frontend TypeScript/build checks](.github/workflows/frontend-ci.yml) and a [backend dependency-install/boot check](.github/workflows/backend-ci.yml). The backend workflow does not currently run the full PHPUnit suite. Existing frontend lint findings are separate from the production build check.

## Current boundaries

- Terms, privacy policy, community, releases, full credits, and full video-gallery routes still use placeholder screens. Core movie details include credits and available trailers.
- The notification screen, shared frontend store, preferences, and model exist, but `/api/notifications` read/update/dismiss routes are not currently registered in the backend.
- Google Calendar support opens a prefilled event link; it does not synchronize calendars in the background. Saved integration/digest preferences do not by themselves implement delivery jobs.
- External catalog, AI, email, map, OAuth, and socket functionality depends on valid provider configuration.

## Contributing

1. Create a focused branch from `dev`.
2. Reuse shared frontend components/design tokens and the backend's module conventions.
3. Run the checks relevant to the change before opening a pull request.
4. Include screenshots or a short demonstration for visible interface changes.

[HANDOFF.md](HANDOFF.md) contains historical implementation context and may describe earlier stages of the project.

## Educational use

Developed as part of the Web Programming II course. Third-party data, artwork, services, and libraries remain subject to their respective providers' terms and licenses.
