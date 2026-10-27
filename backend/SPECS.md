# WatchWise Feature Specification

Every feature the Web2 project brief (PROG302-EC10, "Modern Movie Discovery & Social Platform") requires, grouped by backend module the way `app/` is laid out. Each feature is checked against the code on `dev` at commit `56454c6` (2026-10-27).

New modules must follow the rules in `backend/CLAUDE.md` › "WatchWise Module Architecture". Each module section lists the brief objectives it covers.

## Status legend

| Tag | Meaning |
|---|---|
| **[Done]** | Implemented correctly, follows the module rules. Nothing to do. |
| **[Refactor]** | Works, but the code breaks the module rules. Behaviour stays the same; the structure changes. |
| **[Modify]** | Exists but is incomplete or behaves differently from what the brief asks. |
| **[Rebuild]** | Existed, was deleted with `app/Http` and `app/Services` (PR #34), and is required by the brief. Rebuild it as a module. |
| **[Missing]** | Required by the brief and never implemented in the backend. |
| **[Optional]** | Not required by the brief. Decide whether to keep or build it. |

Every feature also has a **Frontend** line: *real API*, *mock data* (page exists with hard-coded content), *calls removed endpoint*, or *none*.

## Summary

| Module | Brief objective | State |
|---|---|---|
| Activity | 9 (recent activity) | **[Rebuild]** new module |
| Admin | 11 (dashboard) | **[Missing]** new module |
| Auth | 1 (authentication) | Done, with 3 refactors and 1 decision |
| Calendar | 7 (calendar integration) | Done in the frontend. A backend module is **[Optional]**. |
| Chat (now `Groq`) | not in brief | Done. Rename is a **[Refactor]**. |
| Cinemas | 7 (maps / geolocation) | Works end to end, but **[Refactor]**: the whole feature is in one controller |
| Docs | 5 (documentation) | **[Done]** |
| Gemini | 7 (AI) | Unused. **[Refactor]**: use it or delete it. |
| Genre | 2, 3 | **[Done]** |
| Movie | 2, 3, 11 (local data) | Done, with 1 refactor and 2 missing pieces |
| MovieList | 8 | **[Done]** (+ activity hooks) |
| MovieNight | 10 | **[Done]**, with 1 small fix |
| MovieRecommendation | 6, 7 (weather) | **[Modify]**: weather only, not personalized |
| Notification | not in brief | **[Optional]**: deleted. The table now also holds room read receipts. |
| Person | 2, 3 (actors / directors) | **[Done]** |
| Rating | 4 (rate movies) | **[Done]**. Merge into Review when it's built (**[Refactor]**). |
| Review | 4, 7 (sentiment) | **[Missing]** apart from star ratings (biggest gap) |
| Room | 7 (real-time rooms) | **[Done]**, with 3 refactors and missing moderation |
| Support | shared | **[Done]** |
| User | 1 (profile), 9 (follow) | Mostly done. Profile reviews and activity need **[Rebuild]**. |
| Watchlist | 5 | Done, with 1 modification (history) |
| Weather | 7 (weather) | **[Done]** |

Suggested order of work: Review (absorbing Rating), Activity, Admin (with the local movie catalogue), personalized recommendations, the Cinemas refactor, then the remaining refactors.

### What changed since the last check (`8b2637b`)

- **Room module (`app/Room/`)**: built with Reverb. Rooms can be public or private, with invites, a live rooms list, real-time messages and read receipts. This moves Room from **[Rebuild]** to **[Done]**.
- **Person module (`app/Person/`)**: TMDB person details and frequent collaborators. Moves from **[Missing]** to **[Done]**.
- **Rating module (`app/Rating/`)**: members save a 1–5 star rating per film. The "Rate movies" part of objective 4 is now **[Done]**.
- **Cinemas (`app/Cinemas/`)**: `GET /api/nearby-cinemas` through Geoapify, with a Mapbox map in the frontend. It works, but needs a **[Refactor]**.
- **MovieList**: added single-film add and remove (`POST /api/lists/{list}/items`, `DELETE /api/lists/{list}/items/{tmdbId}`) and `?contains=` for the movie page's "Add to list".
- **Migrations**: `reviews.content` and `reviews.sentiment` are now nullable, with a unique index on (`user_id`, `tmdb_id`). New tables `room_members` and `room_invites`, and a new column `discussion_rooms.is_private`.

---

## Activity (new module: `app/Activity/`)

Brief objective 9: "Display recent activity from followed users, such as new reviews, ratings and public lists" and "Allow users to control which activities appear on their public profile".

The `activity_feed` table and the `ActivityFeed` model still exist. The code that wrote and read them (`app/Services/ActivityFeedService.php`) was deleted in PR #34.

1. **Record activity [Rebuild]**
   - `ActivityServiceInterface::record(User $user, string $type, ...)` stores one row for each event. The `type` enum already exists: `review`, `rating`, `watchlist_add`, `list_created`, `list_movie_added`.
   - The Rating/Review, Watchlist and MovieList services call it through the interface.
   - Activity on private lists is stored with `is_public = false`.
   - Frontend: none (it's a side effect).
2. **Feed of followed users [Rebuild]**
   - `ActivityServiceInterface::feedFor(CurrentUser $viewer, int $limit)` returns the newest events from the people the viewer follows.
   - It must respect each owner's profile visibility and section switches through `App\User\Services\ProfilePrivacy` (a `review` event needs the `reviews` section, a `rating` event needs `ratings`, and so on).
   - Restore it as `activity` in `GET /api/home` through `HomeService`.
   - Frontend: real API. `Home.tsx` already reads `home.activity` and defaults it to `[]`.
3. **Profile activity [Rebuild]**
   - `ActivityServiceInterface::forProfile(User $owner, CurrentUser $viewer, int $limit)` uses the same privacy rules.
   - Restore it as `activity` in `GET /api/users/{username}` through `ProfileService`.
   - Frontend: real API. `profileData.ts` already reads `data.activity ?? []`.
4. **Owner controls what appears [Done in User, applies here]**
   - The section switches (`ratings`, `reviews`, `lists`, `watchlist`, `history`, `following`) already exist in Settings › Privacy (`PATCH /api/me/preferences`).
   - The Activity module must filter on them. Don't add a second set of switches.
5. **Layout:** `InterfaceServices/ActivityServiceInterface`, `Services/ActivityService`, `InterfaceRepository/ActivityRepositoryInterface`, `Repository/ActivityRepository`, `DTOs/Responses/ActivityItemResponse`, and `Providers/ActivityModuleServiceProvider`. Add `Controllers/` only if a standalone feed endpoint is wanted.
6. **Tests:** feed shows followed users only; hidden sections are excluded; private lists don't leak; the owner and admins see everything.

---

## Admin (new module: `app/Admin/`)

Brief objective 11: Administrator Dashboard. The frontend has `pages/Admin/*` (Overview, Users, Reviews, Movies), but all of it is mock data from `adminData.ts`. The backend has the `users.role` column, the `role` middleware alias (`EnsureRole`) and `CurrentUser::isAdmin()`, but no admin routes.

All routes go under `/api/admin` with `auth.jwt` and `role:admin`, plus a policy check per action.

1. **Number of registered users [Missing]**: `GET /api/admin/stats` returns `users_total` and new users per period. Frontend: mock data.
2. **Number of reviews [Missing]**
   - `reviews_total`, `reviews_pending` (unapproved) and reviews per period, in the same stats response.
   - Count only rows with text. Rating-only rows are star ratings, not reviews, the same rule `ProfileRepository::reviewsCount()` uses.
   - Frontend: mock data.
3. **Most watched movies [Missing]**: top `tmdb_id`s by count of watched entries (see Watchlist 4), with titles from the local movie catalogue (Movie 7). Frontend: mock data.
4. **Most highly rated movies [Missing]**
   - Top `tmdb_id`s by average approved rating, star ratings included, with a minimum rating count so one 5-star rating doesn't win.
   - Frontend: mock data.
5. **Most popular genres [Missing]**: genre share across watched and rated movies. This needs genre IDs per movie, which come from the local movie catalogue (Movie 7). Frontend: mock data.
6. **User activity [Missing]**
   - A time series (per day or week) of sign-ups, ratings and reviews, list creations, watch events and room messages.
   - Source it from `users.created_at`, `reviews.created_at` and `activity_feed`.
   - Frontend: mock data (`ACTIVITY_LABELS`).
7. **User management [Missing]**
   - `GET /api/admin/users?q=&role=&status=` (paginated).
   - `PATCH /api/admin/users/{user}` changes `role` and `is_active`. Deactivating revokes the user's refresh tokens.
   - `DELETE /api/admin/users/{user}`.
   - An admin cannot demote, deactivate or delete themselves.
   - Frontend: mock data (`AdminUsers.tsx`).
8. **Review moderation [Missing]**
   - `GET /api/admin/reviews?status=pending|approved` lists reviews.
   - `PATCH /api/admin/reviews/{review}` approves or rejects.
   - `DELETE /api/admin/reviews/{review}`.
   - The moderation logic itself lives in the Review module (`ReviewServiceInterface::approve()`); Admin calls it through the interface.
   - Frontend: mock data (`AdminReviews.tsx`).
9. **Local movie data management [Missing]**
   - `GET /api/admin/movies` and `PATCH /api/admin/movies/{tmdbId}` (hide, feature, edit the local title or overview).
   - The data and logic live in the Movie module's local catalogue (Movie 7); Admin calls `MovieCatalogServiceInterface`.
   - Frontend: mock data (`AdminMovies.tsx`).
10. **[Optional] Room moderation**: let admins delete a room or a message, through `RoomServiceInterface` (see Room 9).
11. **Frontend guard [Missing]**: the `/admin` routes in `frontend/src/router.tsx` are only behind sign-in, not behind the admin role. Add a role guard. The backend `role:admin` check is still the real protection.
12. **Layout:**
    - `Controllers/AdminController` (one controller for the dashboard, users, reviews and movies).
    - `FormRequests/` (`AdminUserIndexRequest`, `UpdateUserRequest`, `ModerateReviewRequest`, `UpdateLocalMovieRequest`).
    - `DTOs/Responses/` (`AdminStatsResponse`, `RankedMovieResponse`, ...).
    - `InterfaceServices/AdminServiceInterface`, `Services/AdminService`.
    - `InterfaceRepository/AdminStatsRepositoryInterface`, `Repository/AdminStatsRepository` (aggregate queries only).
    - `Exceptions/CannotModerateSelf`, `Policies/` (if needed), `Providers/AdminModuleServiceProvider`.
    - Add the paths to `openapi/paths/admin.json`.
13. **Tests:** non-admins get 403 on every admin route; stats count correctly; self-demotion is blocked; deactivation blocks login and refresh.

---

## Auth (`app/Auth/`)

Brief objective 1: User Authentication. Unchanged since the last check.

1. **Registration [Done]**: `POST /api/auth/register`, rate-limited, sends the verification email. Frontend: real API.
2. **Login / logout [Done]**: `POST /api/auth/login` and `POST /api/auth/logout`. JWT access token plus a rotating refresh token in HttpOnly cookies. Frontend: real API.
3. **Session refresh [Done]**: `POST /api/auth/refresh` rotates the refresh token. Frontend: real API (axios interceptor).
4. **Current user [Done]**: `GET /api/auth/me`. Frontend: real API.
5. **Password management [Done]**
   - Forgot and reset password: `POST /api/auth/forgot-password` and `POST /api/auth/reset-password`.
   - Change password lives in the User module (`PUT /api/me/password`) and is also done.
   - Frontend: real API.
6. **Email verification [Done], with a decision needed**
   - Signed link `GET /api/auth/verify-email/{id}/{hash}` and `POST /api/auth/resend-verification` both work.
   - **[Modify]** The `verified.email` middleware (`EnsureEmailVerified`) is registered but used on no route, so unverified users can do everything.
   - Decide which actions need a verified email (suggested: writing reviews, posting room messages, creating movie nights) and apply the middleware there.
7. **Login via social media [Done]**
   - Google, GitHub and Discord through `GET /api/auth/social/{provider}` and its `callback`, with a state cookie.
   - The brief says "gmail, github, fb etc", so three providers is enough. Facebook is **[Optional]**.
   - Frontend: real API.
8. **Authentication and authorization [Done]**
   - `auth.jwt`, `auth.optional` and `role` middleware.
   - The `CurrentUser` abstraction, with the Gate bound to it in `AuthorizationServiceProvider`.
   - Policies for every owned model.
   - `role:admin` isn't used yet because the Admin module doesn't exist.
9. **[Refactor] `SocialAuthController` holds business and HTTP logic**
   - The OAuth credential check (`abort_if(... CHANGE_ME ...)`) is in the controller.
   - So is the whole Discord token exchange and user fetch (`discordUser()` with two `Http::` calls).
   - Move both into the service layer, for example `SocialAuthServiceInterface::redirectUrl()` and an `Auth/Support/DiscordOAuthClient` used by the service. The controller should only translate the result into a redirect.
10. **[Refactor] Persistence in a service**
    - `SocialAuthService.php:101` calls `$user->forceFill(['avatar' => null])->save()` directly.
    - Move this into `UserRepositoryInterface` (for example `clearAvatar(User $user)`).
11. **[Refactor] Controllers catch domain exceptions to build responses**
    - `AuthController::login()` catches `InvalidCredentials`, and `PasswordResetController::reset()` catches `InvalidResetToken`, each to build JSON by hand.
    - Give both exceptions a `render()` that returns their 401 or 422 JSON, and remove the try/catch.
    - `AuthController::refresh()` may keep its catch, because it must also clear the auth cookies. Add a PHPDoc saying so.
    - The `EmailRequired`, `UnverifiedProviderEmail` and `AccountDisabled` catches in the social callback map to redirect reasons, which is acceptable for a redirect flow.
12. **[Modify] Known failing test**
    - `AccountFlowsTest::test_social_redirect_sets_state_cookie` fails whenever `.env` has `GOOGLE_CLIENT_ID=CHANGE_ME`.
    - Set `config(['services.google.client_id' => ..., 'services.google.client_secret' => ...])` inside the test, as `CLAUDE.md` › Tests requires.
13. **[Optional]** Captcha after repeated failed logins (existing TODO in `AuthController::login()`).

---

## Calendar (brief objective 7: "Calendar Integration")

1. **Add a movie release or a movie night to the user's calendar [Done in the frontend]**
   - `MovieDetails.tsx` and `NightDetail.tsx` build a Google Calendar "add event" link (`calendar.google.com/calendar/render`).
   - This meets the brief as written.
2. **[Optional] Backend calendar module (`app/Calendar/`)**
   - If the evaluators expect the integration to go through the backend (the micro-service rubric), add `GET /api/movie-nights/{night}/calendar.ics` and `GET /api/movies/{id}/calendar.ics`.
   - These would return iCalendar files that work with Google, Outlook and Apple.
3. **[Optional] Unused table**
   - `calendar_events` and the `CalendarEvent` model are never read or written.
   - Either use them in item 2 (to remember what was exported) or drop them in a migration.

---

## Chat (currently `app/Groq/`)

Not required by the brief. It's an extra: a streaming movie assistant over Reverb.

1. **Chatbot [Done]**
   - `POST /api/chat/messages` streams replies as `ChatDelta` events on the private `chat.{userId}.{conversationId}` channel.
   - `GET /api/chat/connection` returns public socket settings, and `POST /api/chat/auth` authorizes the channel.
   - The prompt keeps the bot on movie topics.
   - Frontend: real API (`ChatbotWidget`).
2. **[Refactor] Module named after a vendor**
   - `CLAUDE.md` says to name modules by what they do, not by technology.
   - Move the chat feature (`ChatController`, `ChatSocketController`, `ChatService`, `ChatMessageRequest`, `ChatSocketRequest`, `ChatMessageData`, `ChatResponse`, `ChatDelta`, `ChatUnavailable`) into `app/Chat/`.
   - Keep `app/Groq/` as the AI client only (`GroqService` and its interfaces, `GroqUnavailable`, provider). It is shared by Chat, Weather, MovieRecommendation and, later, Review sentiment.
   - Move the tests from `tests/Feature/Groq/ChatTest.php` to `tests/Feature/Chat/`.
3. **Tests [Done]**: `tests/Feature/Groq/ChatTest.php` passes.

---

## Cinemas (`app/Cinemas/`)

Brief objective 7: "Maps/Geolocation API – display nearby cinemas or movie-related locations".

1. **Nearby cinemas [Done, but see items 3 and 4]**
   - `GET /api/nearby-cinemas?latitude=&longitude=` (public, `throttle:30,1`) returns up to 50 cinemas within 30 km from the Geoapify Places API (`entertainment.cinema`).
   - It returns 503 when `GEOAPIFY_API_KEY` is missing.
   - Frontend: real API. `Cinemas.tsx` uses a Mapbox map and browser geolocation, and `cinemasApi.ts` turns Geoapify features into cinema cards.
2. **Showtimes [Done]**: the Cinemas page no longer shows showtimes or IMAX/4DX filters. The movie page's showtimes appear only for the design's demo movies.
3. **[Refactor] The whole feature lives in `NearbyCinemaController::index()`**
   - The controller currently does all of this itself:
     - inline `$request->validate()`
     - reads the API key from config
     - makes the `Http::` call to Geoapify
     - logs
     - builds three different error responses
     - returns Geoapify's raw `features`
   - Split it the way `Weather` is split:
     - `FormRequests/NearbyCinemasRequest` with `toDto()`, and `DTOs/Requests/NearbyCinemasQuery`.
     - `InterfaceServices/CinemaServiceInterface` and `Services/CinemaService`.
     - `InterfaceRepository/CinemaRepositoryInterface` and `Repository/CinemaRepository`, which holds the Geoapify call and caches results briefly.
     - `Exceptions/CinemaSearchNotConfigured` (503) and `Exceptions/CinemaSearchUnavailable` (502/503), each with `render()`.
     - `Providers/CinemaModuleServiceProvider`, registered in `bootstrap/providers.php`.
   - The controller then only calls the service.
4. **[Refactor] Return WatchWise's own shape, not Geoapify's**
   - Add `DTOs/Responses/CinemaResponse` (`id`, `name`, `address`, `latitude`, `longitude`, `distance_meters`, `website`) and map Geoapify's features in the service.
   - Then `cinemasApi.ts` stops parsing a vendor format, and changing provider won't touch the frontend.
   - Update `openapi/paths/cinemas.json` to match.
5. **[Refactor] Module name**: `CLAUDE.md` › section 1 names modules in the singular (`Watchlist`, `MovieNight`). Rename `app/Cinemas/` to `app/Cinema/`.
6. **[Missing] Tests**
   - There are none for this endpoint.
   - Add feature tests with `Http::fake()`: a successful search, validation errors, a missing key (503), and Geoapify failing or unreachable.
7. **Frontend leftover**: `pages/Cinemas/cinemasData.ts` (the old mock data) isn't imported anywhere any more. Delete it.

---

## Docs (`app/Docs/`)

Brief deliverable: technical documentation.

1. **OpenAPI UI and spec [Done]**
   - `GET /api/documentation` and `GET /api/documentation/openapi.json` are built from `openapi/paths/*.json`.
   - `OpenApiCoverageTest` keeps the spec and the routes in sync.
   - `cinemas.json`, `rooms.json` and the person paths in `tmdb.json` were added with the new routes.
2. **Keep it current [Ongoing]**: every new module must add its paths file, or the coverage test fails.
3. **Architecture document [Done]**: `backend/Backend.md` (now with a Room module section) and `backend/CLAUDE.md`. Update `Backend.md` as modules are added. Person, Rating and Cinemas don't have sections yet.

---

## Gemini (`app/Gemini/`)

1. **[Refactor] Unused module**
   - Nothing outside `app/Gemini/` uses `GeminiServiceInterface`. Weather and recommendations moved to Groq.
   - Either use it as the sentiment classifier for reviews (Review 6), or delete `app/Gemini/`, its provider entry in `bootstrap/providers.php`, `tests/Feature/Gemini/` and the Gemini config.
   - Don't keep two AI clients when only one is used.

---

## Genre (`app/Genre/`)

Brief objectives 2 and 3: genres.

1. **Genre list [Done]**: `GET /api/genres` (movie and tv) from TMDB through `TmdbHttpClient`. Frontend: real API (Discover).

---

## Movie (`app/Movie/`)

Brief objectives 2 (Movie Database), 3 (External Movie API) and 11 (local movie data).

1. **Popular and upcoming movies [Done]**: `GET /api/movies?list=popular|top_rated|now_playing|upcoming`. Frontend: real API.
2. **Search [Done]**: `GET /api/movies/search?q=&year=&type=movie|tv|all`. Frontend: real API.
3. **Filtering and sorting [Done]**: `GET /api/movies/discover` with genres, year range, minimum rating, runtime range, language and sort, for movies, tv or both. Frontend: real API (Discover).
4. **Detailed movie page [Done]**
   - `GET /api/movies/{id}` returns title, poster, backdrop, overview, release date, genres, cast (12), directors, TMDB rating, runtime, certification, language, budget, revenue, trailer and related titles. It's cached for 6 hours.
   - Frontend: real API for the TMDB data, your star rating (Rating) and "Add to list" (MovieList).
   - The community parts are still mock data and appear only on the design's demo movies: reviews, average user rating, showtimes.
5. **TV shows [Done]**: an extra, `?type=tv` on the same endpoints.
6. **[Refactor] Raw query input in the controller**
   - `MovieController::show()` reads `$request->query('type')` itself.
   - Add `FormRequests/MovieShowRequest` (`type` in `movie,tv`) with `toDto()`, and inject that instead of `Request`.
7. **Local movie catalogue [Missing]**
   - Admin "local movie data management" and the admin stats (most watched, popular genres) need movie data stored locally. Today the app stores only `tmdb_id`s.
   - Add a `movies` table (migration and model in `app/Models`): `tmdb_id` (unique), `title`, `poster_path`, `release_date`, `runtime`, `genre_ids` (json), `is_hidden`, `is_featured`, `synced_at`.
   - Upsert a row when a user first watchlists, rates or reviews, or lists a movie, through `MovieCatalogServiceInterface::remember(int $tmdbId)` called by those services.
   - Admin edits go through the same interface (Admin 9).
   - Hidden movies are left out of the app's own lists and stats.
8. **Average user rating on the movie page [Missing]**
   - The brief asks to "calculate and display average user ratings".
   - Star ratings are now stored (Rating), but nothing computes or returns an average.
   - The number comes from the Review module (Review 4). Show it either in the reviews response, or by having `MovieService` call `ReviewServiceInterface::summary($tmdbId)`. `MovieService` must not query `reviews` itself.

---

## MovieList (`app/MovieList/`)

Brief objective 8: Custom Movie Lists.

1. **Create, edit and delete personal lists [Done]**: `POST`, `PATCH` and `DELETE /api/lists/{list}`, with owner-only policy checks. Frontend: real API.
2. **Add and remove movies [Done]**
   - `tmdb_ids` on create and update replaces all of a list's items, keeping their order.
   - Single films can be added and removed with `POST /api/lists/{list}/items` (appended at the end; adding twice does nothing, even for concurrent requests) and `DELETE /api/lists/{list}/items/{tmdbId}`.
   - Frontend: real API (list dialog with TMDB search, and the movie page's "Add to list" pop-up).
3. **Title and description [Done]**: validated at 80 and 280 characters. Frontend: real API.
4. **Public or private [Done]**: `is_public`. Private lists are visible only to the owner. Frontend: real API.
5. **Browse other users' public lists [Done]**
   - `GET /api/lists?scope=public|following|mine&q=`.
   - `&contains={tmdbId}` adds `has_movie` to each list.
   - Frontend: real API (Lists, Discover › Lists, the movie page).
6. **[Modify] Activity hooks**
   - When the Activity module exists, `MovieListService` records `list_created` on create, and `list_movie_added` for films added through `update()` or `addMovie()`.
   - Use `ActivityServiceInterface`, with `is_public` copied from the list.
7. **[Refactor] Small convention gap**: `AddMovieListItemRequest` exposes `tmdbId()` instead of `toDto()`. See Cross-cutting 3.

---

## MovieNight (`app/MovieNight/`)

Brief objective 10: Movie Night Planner. Unchanged since the last check.

1. **Create a movie night with date, time, location or meeting link [Done]**: `POST /api/movie-nights` (`title`, `scheduled_at` in the future, `location`, `meeting_link`, `note`, `invitees`, `tmdb_ids`). Frontend: real API.
2. **Invite platform users [Done]**: `POST /api/movie-nights/{night}/invites` by username, organizer only. Frontend: real API.
3. **Shared shortlist of candidate movies [Done]**: candidates are set at creation, and a vote for a new title adds it to the shortlist. Frontend: real API.
4. **Invited users vote [Done]**
   - `PUT /api/movie-nights/{night}/vote`: one vote per user, replaceable.
   - Declined guests can't vote (`DeclinedGuestCannotVote`).
   - Voting closes once the night is confirmed or cancelled (`VotingClosed`).
   - Frontend: real API.
5. **Vote totals and winner [Done]**
   - The night response includes totals per candidate.
   - The organizer picks `winning_tmdb_id`, which must be on the shortlist, and confirms with `PATCH /api/movie-nights/{night}`.
   - Frontend: real API.
6. **Accept or decline [Done]**: `PUT /api/movie-nights/{night}/rsvp`. Guests who weren't invited get `NotInvitedToMovieNight` (403). Frontend: real API.
7. **Final details for all participants [Done]**: `GET /api/movie-nights/{night}` for the organizer and invitees, plus the overview in `GET /api/movie-nights`. Frontend: real API. The Google Calendar link is in the frontend.
8. **[Modify] `RsvpRequest::authorize()` returns `true`**
   - This is safe, because the service rejects users who weren't invited. `CLAUDE.md` › section 5 requires that case to be documented.
   - Either add a PHPDoc saying the service enforces it, or switch to `Gate::allows('view', $this->route('night'))` like `VoteRequest`.
9. **[Optional]** Notify guests about invites, RSVPs and the final pick. This was removed with notifications (see Notification).

---

## MovieRecommendation (`app/MovieRecommendation/`)

Brief objective 6: Recommendation System. "Personalized movie recommendations based on watched movies, ratings, favorite genres, actors/directors and movie similarity". Objective 7 adds "adapt movie suggestions to the current weather". Only the tests changed since the last check.

1. **Weather-adapted picks [Done]**
   - `GET /api/movie-recommendations?latitude=&longitude=` gets the current weather, discovers TMDB candidates in weather-matched genres, and has Groq pick up to 10 with a reason.
   - Frontend: real API (the For You weather row).
2. **[Modify] Recommendations are not personalized**: the service uses only weather. It doesn't look at the user at all, and the route doesn't even require sign-in.
3. **Personalized recommendations [Missing]**
   - Add `GET /api/recommendations` behind `auth.jwt`. Build the candidate pool from:
     - **Watched movies:** TMDB recommendations for the user's recently watched titles (Watchlist, through `WatchlistServiceInterface`).
     - **Ratings:** the user's star ratings are now stored (Rating). Weight titles similar to their 4–5 star films, and avoid genres of 1–2 star films, through the Rating/Review service interface.
     - **Favorite genres, actors and directors:** from preferences (User, through an interface method). TMDB Discover with `with_genres`, `with_cast` and `with_crew`.
     - **Movie similarity:** TMDB `/movie/{id}/recommendations` (already used by `MovieService::find`).
   - Exclude titles already on the watchlist or watched.
   - Return groups ("Because you watched X", "Your favorite genres", "Directors you like"), each item with a reason.
   - Frontend: For You's other shelves are static mock data (`forYouData.ts`). Wire them to this endpoint.
4. **[Modify] Weather plus taste**: when the caller is signed in, the weather endpoint should rank weather-matched candidates by the user's taste too, so suggestions adapt to the weather and stay personal.
5. **Layout:**
   - Keep `RecommendationController` with an extra `personal()` action.
   - Add `PersonalRecommendationQuery`, `PersonalRecommendationResponse` and a service method.
   - Talk to the other modules only through their service interfaces.
   - Add tests that use faked TMDB and Groq.

---

## Notification (`app/Notification/`, not required by the brief)

1. **[Optional] The notifications API was deleted in PR #34**
   - The `notifications` table, the `Notification` model and `app/Policies/NotificationPolicy.php` remain.
   - The frontend still calls the removed endpoints (`pages/Notifications/notificationsStore.ts`: `GET /notifications`, `PATCH /notifications/{id}`, `POST /notifications/read-all`, `DELETE /notifications/{id}`), and `AppNav` links to the page.
   - Decide one of:
     - (a) Rebuild it as `app/Notification/`, triggered by follows, movie-night invites, RSVPs, votes, room invites and the final pick, with an optional real-time push over Reverb.
     - (b) Remove the Notifications page and nav link from the frontend, and delete `NotificationPolicy`.
2. **The table is now shared with Room**
   - Room stores its read receipts as `notifications` rows of type `room_message`.
   - `GET /api/me` already leaves them out of `counts.unread_notifications`, and a rebuilt feed would have to leave them out too.
   - See Room 6 for moving them into a Room-owned table. That should happen before either choice above.

---

## Person (`app/Person/`)

Brief objectives 2 and 3: "actors, director" and "Actors and directors".

1. **Actor and director page [Done]**
   - `GET /api/people/{id}`, using TMDB `/person/{id}?append_to_response=movie_credits` through `TmdbHttpClient`.
   - Returns the profile, "known for" (6 titles) and the filmography. Cached for 6 hours; `PersonNotFound` returns 404. Under `throttle:tmdb`.
   - Frontend: real API (`personApi.ts`). `personData.ts` is kept only for the design's demo person (`/people/lena-okafor`).
2. **Frequent collaborators [Done]**
   - An extra: `GET /api/people/{id}/collaborators` returns the 3 people who most often appear in the credits of this person's best-known films.
   - Films TMDB has since removed are skipped.
   - Frontend: real API.
3. **Tests [Done]**: `tests/Feature/Person/PersonDetailsTest.php` and `Repository/PersonRepositoryTest.php`.
4. **[Optional] TV credits**: the filmography uses `movie_credits` only, so a person's TV work is missing even though the app supports TV shows. Switch to `combined_credits` if TV should appear.

---

## Rating (`app/Rating/`)

Brief objective 4: "Rate movies".

1. **Rate a movie [Done]**
   - `PUT /api/ratings/{tmdbId}` (`rating` 1–5, `throttle:60,1`) saves the signed-in member's rating.
   - A new rating is stored as an approved `reviews` row without text. Rating a film you already reviewed only changes the stars.
   - `GET /api/ratings/{tmdbId}` returns your rating, or `null`.
   - Frontend: real API (the movie page's "Your rating", loaded only when signed in).
2. **Schema [Done]**: migration `2026_10_26_000001_allow_rating_only_reviews` made `content` and `sentiment` nullable, and added the unique index on (`user_id`, `tmdb_id`), so each member has one review per film.
3. **Tests [Done]**: `tests/Feature/Social/RatingTest.php` (to be moved, see Cross-cutting 2).
4. **[Refactor] Two modules will own the `reviews` table**
   - A rating is a review without text, so it's one entity. When the Review module is built, move this module's controller actions, service and repository into `app/Review/`.
   - The `/api/ratings/{tmdbId}` routes can stay, pointing at the Review controller.
   - That way only one module writes `reviews` (`CLAUDE.md` › section 1).
5. **[Refactor] Small convention gaps**
   - `RateMovieRequest` exposes `rating()` instead of `toDto()` (see Cross-cutting 3).
   - The controller builds `['rating' => ...]` by hand instead of returning a response DTO.
6. **[Optional] Remove a rating**: there's no way to clear your stars. If added, removing the stars from a row that has review text should keep the review.
7. **[Modify] Activity hook**: record `rating` through `ActivityServiceInterface` when it exists (Activity 1).

---

## Review (new module: `app/Review/`)

Brief objective 4 (User Ratings and Reviews) and objective 7 (Sentiment Analysis).

This is still the largest gap.
- **Already in place:** star ratings (Rating), the `reviews` table (now with nullable `content` and `sentiment` and a unique index per member and film), the `Review` model and `app/Policies/ReviewPolicy.php`.
- **Missing:** there are no endpoints for written reviews, and the movie page's reviews are mock data.
- **Already relies on this data:** other modules read reviews (Watchlist ratings, profile `top4` and counts), so the data shape is fixed.

1. **Rate movies [Done]**: see Rating. Fold that module into this one (Rating 4).
2. **Write a review [Missing]**
   - `POST /api/movies/{tmdbId}/reviews` (`rating`, `content`).
   - Because of the unique index, this fills in the text on the member's existing rating row if there is one, rather than inserting a second row.
   - Requires sign-in, plus verified email if that decision is made (Auth 6).
3. **Edit and delete your own review [Missing]**
   - `PATCH /api/reviews/{review}` and `DELETE /api/reviews/{review}`, authorized by `ReviewPolicy`.
   - Move the policy to `app/Review/Policies/` and update `AuthorizationServiceProvider`.
   - Editing the text sends the review back to moderation and re-runs sentiment.
   - Deleting the text should keep the star rating, unless the member wants both gone.
4. **View other users' reviews and the average rating [Missing]**
   - `GET /api/movies/{tmdbId}/reviews?sort=newest|highest|lowest&page=` returns approved reviews with text, plus the viewer's own review (even if pending).
   - It also returns a `summary` with `average`, `count` and the distribution per star. The summary counts all approved ratings, including rating-only rows.
   - Frontend: mock data (`MovieDetails.tsx`, `ReviewCard`). The "Most helpful" sort has no backing data, so drop it or add helpful votes as **[Optional]**.
5. **Administrator moderation [Missing]**
   - `ReviewServiceInterface::pending()`, `approve()` and `reject()`, called by the Admin module.
   - Rating-only rows are saved approved (there's nothing to moderate).
   - Rows with text should start unapproved (pre-moderation), so they're only visible to their author and admins until approved. `ProfilePrivacy::seesUnapprovedReviews()` already follows this.
   - Keep pre-moderation, or switch to auto-approve and moderate afterwards. Decide once and test it.
6. **Sentiment classification [Missing]**
   - When a review with text is created or edited, classify it as `positive`, `neutral` or `negative` through an AI client interface: Groq structured output (already used by recommendations), or the unused Gemini module (see Gemini).
   - If the AI is unavailable, save the review as `neutral` and report the error. Never fail the write.
   - Rating-only rows keep `sentiment = null`.
   - Frontend: the `Sentiment` component exists.
7. **Reviews on public profiles [Rebuild]**
   - The brief says "View public profiles, reviews and movie lists".
   - The `reviews` list was removed from `GET /api/users/{username}` (only `counts.reviews` remains, which now counts only reviews with text).
   - Restore it through `ReviewServiceInterface::forProfile(User $owner, CurrentUser $viewer)`, honouring the `reviews` privacy section.
   - Frontend: real API, which reads `data.reviews ?? []`.
8. **Activity hooks [Missing]**: record `review` (or `rating` for rating-only) through `ActivityServiceInterface`.
9. **Layout:**
   - `Controllers/ReviewController`, including the rating actions moved from `app/Rating/`.
   - `FormRequests/StoreReviewRequest`, `UpdateReviewRequest`, `ReviewIndexRequest`, `RateMovieRequest`.
   - `DTOs/Requests/WriteReviewData`, `RateMovieData`, `ReviewListQuery`; `DTOs/Responses/ReviewResponse`, `ReviewPageResponse`, `RatingSummaryResponse`, `RatingResponse`.
   - `InterfaceServices/ReviewServiceInterface` and `SentimentAnalyzerInterface`, `Services/ReviewService`.
   - `InterfaceRepository/ReviewRepositoryInterface`, `Repository/ReviewRepository`.
   - `Policies/ReviewPolicy`, `Providers/ReviewModuleServiceProvider`, and `openapi/paths/reviews.json`.
10. **Tests:**
    - Only the author can edit or delete.
    - Pending reviews are hidden from others.
    - The average counts approved ratings only.
    - Writing a review on a rated film updates the same row.
    - Sentiment is stored, and an AI failure saves the review as neutral.
    - Admin approval makes a review visible.

---

## Room (`app/Room/`)

Brief objective 7: "Real-Time Communication – create movie discussion rooms using WebSockets or another real-time technology". Rebuilt since the last check on Laravel Reverb, with the full layer set (controllers, FormRequests, DTOs, service, repository, events, policy, provider). `Backend.md` › Room module describes it in detail.

1. **List rooms [Done]**
   - `GET /api/rooms` returns your rooms first, then invitations, then other public rooms, each with members and your unread count.
   - Frontend: real API, kept live over `rooms.public` and `rooms.user.{id}`.
2. **Create a room [Done]**: `POST /api/rooms` (`tmdb_id`, `name`, `spoilers`, `is_private`). The creator becomes the first member. Frontend: real API.
3. **Public and private rooms, join, invite, decline [Done]**
   - An extra: `POST /api/rooms/{room}/join`, `POST /api/rooms/{room}/invites` (by username, members of a private room) and `DELETE /api/rooms/{room}/invite`.
   - Access is enforced by `DiscussionRoomPolicy`.
   - Frontend: real API.
4. **Real-time messages [Done]**
   - `POST /api/rooms/{room}/messages` (members only) stores the message and broadcasts `RoomMessageSent` on the private `room.{id}` channel.
   - `RoomsChanged` updates everyone's rooms list.
   - Channels are authorized through `POST /api/rooms/socket-auth` and `routes/channels.php`.
   - Frontend: real API (`roomSocket.ts`, `laravel-echo`).
5. **Read receipts and message lifetime [Done]**
   - `POST /api/rooms/{room}/read` broadcasts `RoomMessagesRead`. A message is deleted once every member at the time it was sent has read it.
   - **Design note:** the server keeps only unread messages, and each browser keeps its own history in localStorage. A member on a new device, or someone who joins later, doesn't see older messages.
   - The brief doesn't require history, but make sure this is the behaviour you want to demo.
6. **[Refactor] Read receipts live in the `notifications` table**
   - The Room module writes `notifications` rows of type `room_message` (`Notification::ROOM_MESSAGE`). That table belongs to another feature, and `AccountResponse` already had to filter these rows out of the unread count.
   - Move the receipts into a Room-owned table, for example `room_message_reads` (`message_id`, `user_id`, `read_at`).
   - Then remove `Notification::ROOM_MESSAGE` and the filter in `AccountResponse`.
7. **[Refactor] `MessagePolicy` is still in `app/Policies/`**: move it to `app/Room/Policies/` next to `DiscussionRoomPolicy`, and update `AuthorizationServiceProvider`.
8. **[Refactor] Busiest rooms on Home**
   - `GET /api/home` returns `rooms`, but `User\Repository\HomeRepository` queries `DiscussionRoom` and its members directly.
   - `HomeService` should call a new `RoomServiceInterface::busiest(User $user, int $limit)` instead, because room data belongs to the Room module.
9. **[Missing] Moderation**
   - There's no way to delete a message (its author or an admin), to delete a room, or to leave one.
   - The brief asks for "access control ... for user-generated content", so at least message deletion by author and admin should exist. Leaving and deleting rooms are **[Optional]**.
10. **Tests [Done]**
    - `tests/Feature/Room/RoomMembershipTest.php`, `RoomMessagingTest.php` and the permission matrix in `tests/Feature/Auth/PolicyTest.php`. All pass.

---

## Support (`app/Support/`)

1. **Shared TMDB client and errors [Done]**: `TmdbHttpClient`, `Exceptions/TmdbNotFound`, `Exceptions/TmdbUnavailable`.
2. **Shared helpers [Done]**: `LikePattern::contains()`.

---

## User (`app/User/`)

Brief objective 1 ("User profile") and objective 9 (Follow Users).

1. **Own account [Done]**
   - `GET /api/me`.
   - `PATCH /api/me` edits name, username, email (changing it re-verifies), location, bio and profile visibility.
   - `counts.unread_notifications` leaves out room read receipts.
   - Frontend: real API (Settings).
2. **Avatar [Done]**: `POST /api/me/avatar` and `DELETE /api/me/avatar`. Frontend: real API.
3. **Change password [Done]**: `PUT /api/me/password`, rate-limited, with `IncorrectPassword` (422). Frontend: real API.
4. **Delete account [Done]**: `DELETE /api/me`, which requires the current password. Frontend: real API.
5. **Disconnect a social login [Done]**: `DELETE /api/me/social/{provider}`. Frontend: real API.
6. **Preferences [Done]**
   - `PATCH /api/me/preferences` sets favorite genres, privacy switches, integrations and notification toggles.
   - **[Modify]** `favorite_actor_ids` and `favorite_director_ids` exist in `user_preferences` but can't be set through the API.
   - Add them to `UpdatePreferencesRequest` and `UpdatePreferencesData`, because personalized recommendations need them (MovieRecommendation 3).
   - Frontend: real API for what exists. A picker for favorite actors and directors is missing. The Person page could offer "add to favorites".
7. **Public profile [Done]**
   - `GET /api/users/{username}` returns the user card, counts (`counts.reviews` now excludes rating-only rows), public lists, top-4 rated and followers/following previews.
   - Restricted profiles show only the name and handle.
   - Frontend: real API.
8. **Reviews on the profile [Rebuild]**: see Review 7.
9. **Activity on the profile [Rebuild]**: see Activity 3.
10. **Control what appears on the public profile [Done]**
    - `profile_visibility` can be `public`, `followers` or `private`.
    - Section switches cover `ratings`, `reviews`, `lists`, `watchlist`, `history` and `following`, enforced by `ProfilePrivacy` and `UserPolicy`.
11. **Follow and unfollow [Done]**
    - `POST /api/users/{username}/follow` and `DELETE /api/users/{username}/follow`.
    - You can't follow yourself. Restricted profiles can still be followed, because they show the name and handle.
    - Frontend: real API.
12. **Followers and following lists [Done]**
    - `GET /api/users/{username}/followers` and `.../following`.
    - When the `following` section is hidden, these return 403 (`ProfileSectionHidden`).
    - Frontend: real API.
13. **Find users [Done]**: `GET /api/users?q=&limit=`. Frontend: real API (Discover › People).
14. **Home dashboard [Done], with gaps**
    - `GET /api/home` returns the next movie night and the busiest rooms (public rooms plus the private ones you're in).
    - `activity` must come back (Activity 2), and `rooms` should come through the Room module (Room 8).
15. **[Refactor] Untyped response DTOs**
    - `ProfileResponse` and `HomeResponse` wrap a loose `array $payload`.
    - Give them typed constructor properties, as `WatchlistResponse` and `RoomResponse` have, so each response shape is defined in one place.
16. **[Refactor] Stale comment**: the `ProfilePrivacy` docblock mentions "every activity feed". It will be accurate again once Activity is rebuilt; otherwise remove it.

---

## Watchlist (`app/Watchlist/`)

Brief objective 5: Personal Watchlist. Unchanged since the last check.

1. **Add and remove movies [Done]**: `POST /api/watchlist` (returns 201 when new, 200 when it already exists) and `DELETE /api/watchlist/{watchlist}`. Frontend: real API (Watchlist, Home, Person).
2. **View your watchlist [Done]**: `GET /api/watchlist` returns the items and your ratings by `tmdb_id`. Frontend: real API.
3. **Mark as watched [Done]**: `PATCH /api/watchlist/{watchlist}` with `status` set to `to_watch`, `watching` or `watched`, which sets `watched_at`. Frontend: real API.
4. **Watch history [Modify]**
   - History is the set of `watched` rows ordered by `watched_at`, which the frontend's History tab groups by month.
   - Moving a title from `watched` back to `to_watch` erases its `watched_at`, and rewatches can't be recorded, so history is lost.
   - Add a `watch_history` table (`user_id`, `tmdb_id`, `watched_at`) written whenever a title is marked watched. Then `GET /api/watchlist/history` returns it, and the watchlist row only holds the current status.
   - Admin "most watched" (Admin 3) should count from this table.
5. **[Modify] Activity hook**: record `watchlist_add` through `ActivityServiceInterface` when a new item is added, honouring the `watchlist` privacy section.

---

## Weather (`app/Weather/`)

Brief objective 7: "Weather API – adapt movie suggestions to the current weather".

1. **Current weather [Done]**: `GET /api/weather?latitude=&longitude=` (Open-Meteo, falling back to the configured default location). Frontend: real API (Home, For You).
2. **Movie mood for the weather [Done]**: `POST /api/weather-mood` returns a short mood title and description from Groq. Frontend: real API (Home).
3. The weather-matched movie picks themselves live in MovieRecommendation (item 1).

---

## Cross-cutting work

1. **[Refactor] Global `app/Policies/` folder**
   - `MessagePolicy`, `NotificationPolicy` and `ReviewPolicy` still live in a type-based folder, which `CLAUDE.md` › section 1 forbids.
   - Move `MessagePolicy` to `app/Room/Policies/` now (the Room module exists).
   - Move `ReviewPolicy` to `app/Review/Policies/` with the Review module.
   - Move `NotificationPolicy` to `app/Notification/Policies/`, or delete it (see Notification).
   - Then delete `app/Policies/`.
2. **[Refactor] Tests grouped by an old area name**
   - `tests/Feature/Social/*` covers the User, MovieList, MovieNight, Watchlist and Rating modules: `AccountRulesTest`, `ContentTest`, `ListItemsTest`, `MovieNightRulesTest`, `PrivacyTest`, `ProfileTest`, `RatingTest`, `SearchTest`, `WatchlistRulesTest`.
   - The two new files were added there too.
   - Split them into `tests/Feature/User/`, `tests/Feature/MovieList/`, `tests/Feature/MovieNight/`, `tests/Feature/Watchlist/` and `tests/Feature/Rating/` (or `Review/`), per `CLAUDE.md` › section 10.
3. **[Refactor] FormRequests without `toDto()`**
   - `RateMovieRequest::rating()` and `AddMovieListItemRequest::tmdbId()` return a single scalar instead of a request DTO.
   - `CLAUDE.md` › section 4 says every FormRequest exposes `toDto()`. Either add `toDto()` with a small `*Data` class, or add an exception to `CLAUDE.md` for single-field requests. Pick one and apply it everywhere.
4. **[Refactor] Modules reading other modules' tables directly**
   - `CLAUDE.md` › section 5 says to go through the owning module's service interface. Fix these as the owning modules gain the right methods:
     - `User\Repository\HomeRepository` queries `DiscussionRoom` and its members (Room 8).
     - `Room\Repository\RoomRepository` writes `notifications` (Room 6).
     - `Watchlist\Repository\WatchlistRepository::ratingsByTmdbId()` and `User\Repository\ProfileRepository` (top 4, review counts) query `reviews`. Use `ReviewServiceInterface` once Review exists.
5. **Decision: repositories that throw exceptions**
   - `CLAUDE.md` › section 5 says repositories never throw domain exceptions. Yet `MovieRepository` (`MovieNotFound`), `PersonRepository` (`PersonNotFound`) and `WeatherRepository` (`WeatherUnavailable`) translate third-party errors into module exceptions.
   - That's a reasonable pattern for repositories that wrap an external API. Either allow it explicitly in `CLAUDE.md`, or move the translation into the services.
6. **Security [Done], with gaps**
   - **Done:** every write goes through a FormRequest with validation, ownership and room access are enforced by policies, auth cookies are HttpOnly, and auth, TMDB, AI, weather, rating, room and cinema routes are rate-limited.
   - **Gaps:**
     - The admin frontend route has no role guard (Admin 11).
     - Email verification isn't enforced anywhere (Auth 6).
     - `/api/weather-mood` and `/api/movie-recommendations` call the AI without sign-in, and `/api/nearby-cinemas` spends the Geoapify key without sign-in. Each is protected only by a rate limit; consider requiring `auth.jwt`.
     - Room messages can't be moderated (Room 9).
7. **Frontend still on mock data, or calling removed endpoints:**
   - Admin (all four pages).
   - The movie page's community parts (reviews, average rating, showtimes; demo movies only).
   - For You (every shelf except the weather row).
   - The Notifications page, which calls endpoints that no longer exist.
   - Now on the real API: Rooms, Person, Cinemas, star ratings and "Add to list".
8. **Database leftovers**
   - `calendar_events` is unused (Calendar 3).
   - `notifications` is used only by Room read receipts (Notification 2, Room 6).
   - `activity_feed` is kept for Activity, and `reviews` is now shared by Rating and the future Review module (Rating 4).
9. **Test status** (`php artisan test` on `56454c6`, after `composer install`): 297 passed, 1 failed.
   - The only failure is `AccountFlowsTest::test_social_redirect_sets_state_cookie`, because of the Google credentials in `.env` (Auth 12).
