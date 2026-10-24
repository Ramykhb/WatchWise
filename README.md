<div align="center">
  <img src="frontend/src/assets/landing/logo-mark.svg" alt="WatchWise logo" width="96" />

  <h1>WatchWise</h1>

  <p><strong>Discover better. Watch together.</strong></p>

  <p>
    A modern movie discovery and social platform for finding films, sharing opinions,
    planning movie nights, and building a community around what you watch.
  </p>

  <p>
    <a href="#features">Features</a> ·
    <a href="#getting-started">Getting started</a> ·
    <a href="#architecture">Architecture</a> ·
    <a href="#roadmap">Roadmap</a>
  </p>
</div>

<p align="center">
  <img src="https://img.shields.io/badge/Frontend-React%2019%20%7C%20TypeScript%20%7C%20Vite-61DAFB?style=for-the-badge&logo=react&logoColor=white" alt="React 19, TypeScript and Vite" />
  <img src="https://img.shields.io/badge/Backend-Laravel%2012%20%7C%20PHP%208.2%2B-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 12 and PHP 8.2+" />
  <img src="https://img.shields.io/badge/Status-In%20active%20development-FFC93C?style=for-the-badge" alt="In active development" />
</p>

## Overview

WatchWise is the Web Programming II project for the **Modern Movie Discovery & Social Platform** brief. It combines a polished movie discovery experience with the social tools people need to turn recommendations into shared experiences.

The platform is designed around:

- Discovering popular, upcoming, and personally relevant movies
- Exploring rich movie, cast, and director details
- Rating and reviewing films
- Managing watchlists, watch history, and custom public or private lists
- Following users and keeping up with their activity
- Organizing collaborative movie nights with invitations and voting
- Extending recommendations with external data such as weather, location, sentiment, and calendar services

> **Project status:** The frontend foundation and core visual flows are in progress. The Laravel backend contains the initial domain model foundation, while authentication, API endpoints, external service integrations, and real-time functionality are being delivered incrementally.

## Features

### Available in the current frontend foundation

- Branded landing page and responsive application shell
- Sign up, sign in, and forgot-password user flows
- Social sign-in entry points for Google, GitHub, and Discord
- Discover, movie details, person, home, and placeholder route structure
- Reusable movie, review, rating, sentiment, navigation, and form components
- Responsive styling based on shared design tokens and CSS Modules
- GSAP entrance animations and interaction effects
- Lenis smooth scrolling with reduced-motion support
- Desktop viewport fitting for laptop displays

### Product capabilities

| Area | Planned capability |
| --- | --- |
| Authentication | Registration, login/logout, password management, profiles, authorization, and social login |
| Movie discovery | Search, filtering, sorting, popular and upcoming movies, posters, genres, cast, directors, ratings, and runtime |
| Reviews | Create, edit, delete, browse, and moderate reviews with average user ratings |
| Watchlist | Add or remove films, mark films as watched, and maintain watch history |
| Recommendations | Personalized suggestions based on ratings, history, genres, people, and movie similarity |
| Custom lists | Create public or private lists with titles, descriptions, and movie membership |
| Social graph | Follow or unfollow users, browse public profiles, and view activity feeds |
| Movie nights | Invite friends, shortlist candidates, vote, RSVP, and publish the winning film and event details |
| External services | TMDB, weather, geolocation/maps, sentiment analysis, calendar, and real-time discussion rooms |
| Administration | User management, review moderation, movie data management, analytics, and activity monitoring |

## Screens and user journeys

The product design covers the complete review flow:

- Visitor and authentication: landing, sign up, sign in, and password recovery
- Discovery: home, discover/search, movie details, and cast/director profiles
- Personalization: recommendations, watchlist, history, and custom lists
- Community: profiles, follows, reviews, activity, and discussion rooms
- Planning: nearby cinemas and collaborative movie nights
- Administration: overview, users, review moderation, and movie data

The current frontend implements the first screens and provides placeholder routes for the remaining journeys so the navigation structure is already testable.

## Architecture

WatchWise uses a separated frontend/backend structure:

```text
WatchWise/
├── frontend/                 # React application and Vite tooling
│   ├── src/
│   │   ├── components/       # Reusable UI components
│   │   ├── pages/            # Route-level screens
│   │   ├── layouts/          # Shared application layouts
│   │   ├── assets/           # Brand, movie, and interface assets
│   │   └── styles/           # Design tokens and motion styles
│   └── package.json
├── backend/                  # Laravel application and domain layer
│   ├── app/Models/           # Users, reviews, lists, movie nights, and social models
│   ├── database/             # Migrations, seeders, and SQLite development database
│   └── composer.json
├── .github/workflows/        # Frontend and backend CI checks
└── README.md
```

### Frontend

- React 19
- TypeScript
- Vite 8
- React Router 7
- CSS Modules and shared CSS design tokens
- GSAP and `@gsap/react`
- Lenis

### Backend

- Laravel 12
- PHP 8.2+
- Eloquent ORM
- SQLite for local development
- PHPUnit and Laravel's application test tooling

The application is structured to support an MVC backend and independently deployable service integrations. External APIs should be accessed through backend service classes rather than directly from UI components, keeping credentials private and the client contract stable.

## Getting started

### Prerequisites

- Node.js 24 or newer
- npm
- PHP 8.2 or newer
- Composer

### 1. Clone the repository

```bash
git clone https://github.com/Ramykhb/WatchWise.git
cd WatchWise
```

### 2. Start the frontend

```bash
cd frontend
npm ci
npm run dev
```

The Vite development server will print the local URL, normally `http://localhost:5173`.

Available frontend commands:

```bash
npm run dev       # Start the Vite development server
npm run build     # Type-check and create a production build
npm run lint      # Run ESLint
npm run preview   # Preview the production build locally
```

### 3. Set up the Laravel backend

In a second terminal:

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

The backend will normally be available at `http://localhost:8000`.

For a one-command Laravel setup after dependencies are available, the backend also provides:

```bash
composer run setup
```

Do not commit `.env` files or third-party API keys. Add service credentials to the backend environment when those integrations are enabled.

## Validation

Run the same checks used by the repository's CI workflows:

```bash
# Frontend
cd frontend
npm ci
npx tsc --noEmit
npm run build

# Backend
cd ../backend
composer install --no-interaction --prefer-dist
php artisan --version
php artisan test
```

## Roadmap

Development follows incremental deliveries:

- [x] Product direction, visual identity, and reusable frontend design system
- [x] Landing and authentication screen foundations
- [x] Frontend routing and responsive application shell
- [ ] Laravel authentication and frontend/backend API contract
- [ ] TMDB movie catalog and search integration
- [ ] Watchlists, ratings, reviews, and moderation
- [ ] Personalized recommendations and weather-aware discovery
- [ ] Profiles, follows, public lists, and activity feeds
- [ ] Movie-night invitations, voting, and calendar integration
- [ ] Nearby cinemas, sentiment analysis, and real-time discussion rooms
- [ ] Administrator analytics and production deployment

## Project documentation

- [Frontend documentation](frontend/README.md)
- [Backend documentation](backend/README.md)
- [Implementation handoff](HANDOFF.md)
- [Frontend CI workflow](.github/workflows/frontend-ci.yml)
- [Backend CI workflow](.github/workflows/backend-ci.yml)

## Contributing

1. Create a focused feature branch from `dev`.
2. Keep frontend and backend changes separated by responsibility.
3. Reuse existing components, design tokens, and Laravel conventions.
4. Run the relevant lint, build, and test commands before opening a pull request.
5. Include screenshots or a short demonstration for visible UI changes.

## License

This project is developed for educational purposes as part of the Web Programming II course. Licensing and production deployment terms will be finalized before release.

