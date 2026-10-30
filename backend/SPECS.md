# WatchWise Feature Specification

Every feature the Web2 project brief (PROG302-EC10, "Modern Movie Discovery & Social Platform") requires, grouped by backend module the way `app/` is laid out. Each feature is checked against the code on `dev` at commit `9f7c7bd` (2026-10-30). Test results were not re-run for this update.

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
| Activity | 9 (recent activity) | **[Rebuild]** new module. Still not started. |
| Admin | 11 (dashboard) | **[Done]**, merged into `dev` (PR #52): backend, wired frontend, role guard, moderator role and review reports. Only the member-facing Report button is missing. |
| Auth | 1 (authentication) | Done, with 1 decision (email verification) |
| Calendar | 7 (calendar integration) | Done in the frontend. A backend module is **[Optional]**. |
| Chat (now `Groq`) | not in brief | Done. Rename is a **[Refactor]**. |
| Cinema | 7 (maps / geolocation) | **[Done]**: Google Places-backed module; the page still uses Mapbox |
| Docs | 5 (documentation) | **[Done]** |
| Gemini | 7 (AI) | Unused. **[Refactor]**: use it or delete it. |
| Genre | 2, 3 | **[Done]** |
| Movie | 2, 3, 11 (local data) | Done, with 1 refactor and 1 missing piece (hidden films in the app's own lists) |
| MovieList | 8 | **[Done]**, lists hold movies and TV shows (+ activity hooks) |
| MovieNight | 10 | **[Done]**, with 1 small fix |
| MovieRecommendation | 6, 7 (weather) | **[Modify]**: weather only, not personalized |
| Notification | not in brief | **[Optional]**: deleted. The table now also holds room read receipts. |
| Person | 2, 3 (actors / directors) | **[Done]** |
| Rating | 4 (rate movies) | **[Done]**, movies and TV shows. **[Refactor]**: merge into Review, which now exists. |
| Review | 4, 7 (sentiment) | **[Done]** on the movie page (write, view, average, sentiment, auto-moderation, helpful votes, reports). Profile reviews, activity hooks, the Report button and the module cleanup are still open. |
| Room | 7 (real-time rooms) | **[Done]**, with 3 refactors and missing moderation |
| Support | shared | **[Done]** |
| User | 1 (profile), 9 (follow) | Mostly done. Profile reviews and activity need **[Rebuild]**. Favorite actors and directors can't be set. |
| Watchlist | 5 | Done, with 1 modification (history) |
| Weather | 7 (weather) | **[Done]** |

Suggested order of work: Activity (with the Review, Rating, Watchlist and MovieList hooks and the profile reviews list), personalized recommendations, the hidden-film filter, the email-verification decision, then the remaining refactors (absorbing Rating into Review is one of them).

### What changed since the last check (`56454c6`)

- **Admin** (PR #52, merged): the admin pages are on the real API, `/admin` is behind a role guard, and the moderator role, review reports, "active today", range picker and richer movie fields exist. See Admin.
- **Review** (`app/Review/`): movie-page reviews with sentiment, automatic moderation, helpful votes, spoiler marking and member reports, for movies and TV shows. See Review.
- **TV shows**: ratings, reviews, the watchlist and lists now store a `media_type` (`movie` or `tv`), so a TV show and a movie with the same TMDB id no longer clash. TV shows are kept out of the admin movie stats and the local catalogue.
- **Rooms**: messages are kept on the server for 24 hours (see Room 5).
- **Migrations**: `reviews.has_spoilers`, `review_votes`, `reviews.moderation_labels`, `reviews.removed_at`, `review_reports`, `users.last_active_at`, the `moderator` role, `movies.director`, `movies.edited_at`, and `media_type` on `reviews`, `watchlists` and `movie_list_items`.
- **Not changed**: Activity, personalized recommendations, favorite actors and directors, watch history, room moderation, email-verification enforcement, the Notifications page and all the module refactors.

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

Brief objective 11: Administrator Dashboard. Built on `Admin-Features` and wired to the frontend and Auth on `Admin-Wiring`. The mock-only parts of the original design (Moderator role, Unverified status, flagged reviews with reports, "Active today", range picker, movie director, runtime and genre editing) were built into the backend rather than cut from the UI. Details are in `Backend.md` › Admin module.

All routes go under `/api/admin` with `auth.jwt` and `role:admin,moderator`, plus a policy check per action (gates `admin.dashboard`, `admin.users`, `admin.reviews`, `admin.movies`, `admin.users.manage`, backed by `AdminPolicy` and defined in `AuthorizationServiceProvider`). Routes live in `routes/admin.php`.

| Route | admin | moderator |
|---|---|---|
| `GET /admin/stats`, `GET /admin/users` | yes | yes, read-only |
| `PATCH /admin/users/{user}` | `role` and `is_active` | `is_active` only, on a plain `user`, never themselves |
| `DELETE /admin/users/{user}` | yes | no |
| `/admin/reviews*` | yes | yes |
| `/admin/movies*` | yes | no |

1. **Number of registered users [Done]**: `GET /api/admin/stats` returns `users_total` and `new_users` (`last_7_days`, `last_30_days`), plus `active_today` and `active_yesterday` (from `users.last_active_at`, stamped by `auth.jwt` at most every 5 minutes). Frontend: real API.
2. **Number of reviews [Done]**
   - `reviews_total`, `reviews_pending` (unapproved), `flagged_reviews` (open reports) and `new_reviews` (7 and 30 days), in the same stats response.
   - Only rows with text count. Rating-only rows are star ratings, not reviews.
   - Frontend: real API.
3. **Most watched movies [Done]**: `most_watched`, the top 5 `tmdb_id`s by count of `watched` watchlist entries, with titles from the local catalogue. Hidden films are left out. Titles are `null` for films that were activity before the catalogue existed and were never back-filled (see Movie 7). Frontend: real API.
4. **Most highly rated movies [Done]**: `top_rated`, the top 5 by average approved rating (star ratings included), needing at least 3 ratings. Frontend: real API.
5. **Most popular genres [Done]**: `popular_genres`, the top 8 genres weighted by watched plus rated counts, with `share` in percent. Genre ids come from the local catalogue, names from `GenreServiceInterface` (falls back to "Genre {id}" if TMDB is down). Only films in the catalogue count (Movie 7). Frontend: real API.
6. **User activity and date range [Done]**
   - `activity` is a daily series of `signups`, `ratings`, `reviews`, `lists`, `watches` and `messages` (zeros on quiet days).
   - `?range=7|30|90|year` picks the window (`?days=` up to 90 still works; `range` wins). The response's `range` block gives the new users and reviews for the window and for the equally long window before it, for the change figures.
   - It is sourced from `users.created_at`, `reviews.created_at`, `movie_lists.created_at`, `watchlists.watched_at` and `messages.created_at`, not from `activity_feed`, because the Activity module isn't rebuilt yet.
   - Frontend: real API (range select, line chart, CSV export).
7. **User management [Done]**
   - `GET /api/admin/users?q=&role=&status=&page=&per_page=` (paginated; `role` is `user`, `moderator` or `admin`; `status` is `active`, `unverified` or `inactive`; `q` matches name, username or email). Rows carry `status`, `reviews_count` and `last_active_at`; a site-wide `summary` gives the totals.
   - `PATCH /api/admin/users/{user}` changes `role` (`user`, `moderator`, `admin`) and `is_active`. Deactivating revokes the user's refresh tokens through `AuthServiceInterface::revokeSessions()`; login, refresh and existing access tokens stop working.
   - `DELETE /api/admin/users/{user}`, admins only.
   - An admin cannot demote, deactivate or delete themselves (`CannotModerateSelf`, 403). A moderator cannot touch themselves, staff accounts or roles (`CannotModerateStaff`, 403).
   - Frontend: real API (`AdminUsers.tsx`). Moderators get no role editor or delete, and suspend is disabled on rows they cannot change.
8. **Review moderation and reports [Done]**
   - `GET /api/admin/reviews?status=pending|approved|flagged|removed` (default `pending`; the four never overlap) lists reviews with text, their `reports_count` and `reasons`, and a site-wide `summary` (flagged and removed totals, sentiment split).
   - `PATCH /api/admin/reviews/{review}` with `status` = `approved` or `rejected`. Rejecting clears the text and sentiment, keeps the star rating and sets `reviews.removed_at`. Approving or rejecting closes the review's open reports.
   - `DELETE /api/admin/reviews/{review}` removes the row.
   - Members report a review with `POST /api/reviews/{review}/report` (`reason`: Spam, Links, Spoilers or Offensive language; one report per member per review; `throttle:10,1`). It is refused for your own review, an unapproved, text-less or removed review, and a review whose author's privacy hides it from you. Table `review_reports`.
   - The logic lives in the `app/Review/` module (`ReviewServiceInterface`, `MovieReviewServiceInterface`); Admin calls it through the interface.
   - Frontend: real API (`AdminReviews.tsx`). The member-facing **Report** button on the movie page's review cards is **[Missing]**: add a reason picker to the shared `ReviewCard`.
9. **Local movie data management [Done]**
   - `GET /api/admin/movies?q=&page=&per_page=` returns catalogue rows plus a `summary.sync` block (`cached`, `overrides`, `last_synced_at`).
   - `PATCH /api/admin/movies/{tmdbId}` can hide, feature and edit the title, overview, `director`, `runtime`, `release_date` and `genre_ids`. `null` clears the nullable fields, an empty `genre_ids` clears the genres, leaving a field out keeps it. An id that isn't in the catalogue returns 404. Editing content sets `edited_at` (`is_edited`); hiding or featuring does not.
   - The data and logic live in the Movie module's local catalogue (`MovieCatalogServiceInterface`, Movie 7). `movies.director` is filled from TMDB when a film is first cached.
   - Frontend: real API (`AdminMovies.tsx`). Genre names come from `GET /api/genres`.
10. **[Optional] Room moderation**: let admins delete a room or a message, through `RoomServiceInterface` (see Room 9). Not built.
11. **Frontend guard [Done]**
    - `RequireAdmin` in `RouteGuards.tsx` wraps the `/admin` routes: guests go to Sign in; admins open everything; moderators open Overview, Users and Reviews and are redirected from `/admin/movies`; everyone else goes to `/home-page`. The backend role check is still the real protection.
    - The main nav shows an **Admin** link to admins and moderators only. The admin sidebar has a **Back to WatchWise** link, and shows the signed-in user instead of the old mock name.
12. **Layout [Done]:**
    - `Controllers/AdminController` (one controller for the dashboard, users, reviews and movies).
    - `FormRequests/` (`AdminStatsRequest`, `AdminUserIndexRequest`, `UpdateUserRequest`, `AdminReviewIndexRequest`, `ModerateReviewRequest`, `AdminMovieIndexRequest`, `UpdateLocalMovieRequest`).
    - `DTOs/Requests/` (`AdminStatsQuery`, `AdminUserQuery`, `UpdateUserData`, `ReviewModerationQuery`, `ModerateReviewData`). The movie requests use the Movie module's `LocalMovieQuery` and `UpdateLocalMovieData`, because they are part of `MovieCatalogServiceInterface`.
    - `DTOs/Responses/` (`AdminStatsResponse`, `RankedMovieResponse`, `GenreShareResponse`, `ActivityPointResponse`, `AdminUserResponse`, `AdminReviewResponse`, `AdminMovieResponse`, `AdminPageResponse`).
    - `InterfaceServices/AdminServiceInterface`, `Services/AdminService`.
    - `InterfaceRepository/AdminStatsRepositoryInterface` and `AdminUserRepositoryInterface`, with their `Repository/` implementations.
    - `Exceptions/CannotModerateSelf`, `Exceptions/CannotModerateStaff`, `Policies/AdminPolicy`, `Providers/AdminModuleServiceProvider`.
    - Migrations: `2026_10_29_000001` (moderator role, `users.last_active_at`), `000002` (`review_reports`, `reviews.removed_at`), `000003` (`movies.director`, `movies.edited_at`).
    - Paths are in `openapi/paths/admin.json` (and the report endpoint in `social.json`).
    - Frontend: `lib/adminApi.ts` (typed hooks and mutations), `components/AdminPagination/`, and `pages/Admin/*`. `adminData.ts` now holds only UI constants.
13. **Tests [Done]:** `tests/Feature/Admin/` covers 401 and 403 on every admin route, the moderator permission matrix, stat counts and range windows, ranking rules, user status and insight fields, self-demotion and self-deletion blocks, deactivation blocking login, refresh and access tokens, review approve, reject, delete and reports, and the movie catalogue edits. `tests/Feature/Review/ReviewReportTest.php` covers the member report endpoint.
14. **Controls dropped from the original mock** because no endpoint backs them: invite admin, resend verification, warn user, undo or reopen a review, add movie, sync from TMDB, poster upload, reset to TMDB, movie delete, the "TMDB API connected" chip, and the "Active today" and rating columns on the movies table.
15. **[Follow up] Frontend not yet verified:** `npm ci` fails on this machine with a TLS certificate error, so `npm run lint` and `npm run build` have not been run on the admin work. Run both before merging.

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
   - `role:admin,moderator` is used by the Admin routes (`routes/admin.php`). `moderator` is a new role value (Admin 7), and the frontend `RequireAdmin` guard reads `user.role` from `/auth/me`.
   - `auth.jwt` stamps `users.last_active_at` at most every 5 minutes (not exposed in any payload).
9. **[Done] `SocialAuthController` is thin**
   - `SocialAuthServiceInterface::redirectUrl()` checks the OAuth credentials (a missing or `CHANGE_ME` client id or secret throws `SocialLoginNotConfigured`, 503) and returns the provider URL. `providerUser()` returns the signed-in provider's user.
   - The Discord authorize URL, code exchange and profile fetch live in `Auth/Support/DiscordOAuthClient`, which throws `SocialProviderFailed` for an unusable answer. The controller only turns the results into redirects, and still maps any provider failure to the `provider_error` redirect reason.
10. **[Done] Persistence moved to the repository**: `SocialAuthService` calls `UserRepositoryInterface::clearAvatar()` instead of saving the model itself.
11. **[Done] Controllers no longer catch to build responses**
    - `InvalidCredentials` (401) and `InvalidResetToken` (422) render themselves, and the try/catch in `AuthController::login()` and `PasswordResetController::reset()` is gone.
    - `AuthController::refresh()` keeps its catch on purpose, because it must also clear the auth cookies (documented in its PHPDoc).
    - The `EmailRequired`, `UnverifiedProviderEmail` and `AccountDisabled` catches in the social callback stay, because they map to redirect reasons.
12. **[Done] Social redirect test no longer depends on `.env`**: `AccountFlowsTest::test_social_redirect_sets_state_cookie` sets the Google client ID and secret in config itself, so it passes even when `.env` has `GOOGLE_CLIENT_ID=CHANGE_ME`.
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

## Cinema (`app/Cinema/`)

Brief objective 7: "Maps/Geolocation API – display nearby cinemas or movie-related locations".

1. **Nearby cinemas [Done]**
   - `GET /api/nearby-cinemas?latitude=&longitude=` (public, `throttle:30,1`) returns up to 20 cinemas within 30 km from Google Places Nearby Search (`movie_theater`).
   - It returns 503 when `GOOGLE_PLACES_API_KEY` is missing and 502/503 when Google Places fails.
   - Frontend: real API. `Cinemas.tsx` uses a Mapbox map and browser geolocation; `cinemasApi.ts` consumes the app-owned cinema response shape.
2. **Showtimes [Done]**: the Cinemas page no longer shows showtimes or IMAX/4DX filters. The movie page's showtimes appear only for the design's demo movies.
3. **Response and module [Done]**
   - The `Cinema` module validates coordinates, calls Google Places from its service and returns a `cinemas` array with `id`, `name`, `address`, `latitude`, `longitude`, `distance_meters` and `website`.
   - `NearbyCinemaController` only delegates to the service. Provider errors render as 502/503 responses.
   - `cinemasApi.ts` maps the app-owned response shape to the existing page model; `openapi/paths/cinemas.json` documents the contract.
4. **Google Maps attribution [Follow up]**: Google Places content shown on a map must be displayed on a Google Map. The current page uses Mapbox, so replace that map before displaying Google Places results on it.
5. **Frontend leftover**: `pages/Cinemas/cinemasData.ts` (the old mock data) isn't imported anywhere any more. Delete it.

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
   - Frontend: real API for the TMDB data, your star rating (Rating), "Add to list" (MovieList), and the reviews with the average rating (Review, `MovieReviews.tsx`).
   - Showtimes are still mock data and appear only on the design's demo movies.
5. **TV shows [Done]**: an extra, `?type=tv` on the same endpoints.
6. **[Refactor] Raw query input in the controller**
   - `MovieController::show()` reads `$request->query('type')` itself.
   - Add `FormRequests/MovieShowRequest` (`type` in `movie,tv`) with `toDto()`, and inject that instead of `Request`.
7. **Local movie catalogue [Done], except review and hiding**
   - **Done:** the `movies` table (`tmdb_id` unique, `title`, `overview`, `poster_path`, `release_date`, `runtime`, `genre_ids`, `is_hidden`, `is_featured`, `synced_at`), the `LocalMovie` model and factory, and `MovieCatalogServiceInterface` (`remember()`, `paginate()`, `update()`, `summaries()`, `hiddenTmdbIds()`) with its repository. Admin edits and stats use it (Admin 3, 5, 9). Hidden movies are left out of the admin stats.
   - **Done:** `remember(int $tmdbId)` is called by `WatchlistService::add()`, `RatingService::rate()` and `MovieListService` (`create()`, `update()` when films are sent, `addMovie()`). It stores a film once, from TMDB, and never overwrites admin edits. If TMDB can't be reached the film is skipped and the user's action still succeeds. Films added before this change are not back-filled.
   - **Done:** the Review write flow (`MovieReviewService::save()`) calls `remember()` for movies. TV shows are not stored in the catalogue.
   - **Missing:** hidden movies are not yet left out of the app's own lists (search, discover, recommendations). Only Admin and the catalogue use `hiddenTmdbIds()`.
   - **Note:** the base `Tests\TestCase` stubs `MovieCatalogServiceInterface` so no test reaches TMDB by accident; the Admin tests bind the real one.
8. **Average user rating on the movie page [Done through Review]**
   - `GET /api/reviews` returns a `summary` with the average, the star distribution and the sentiment split (Review 4). The movie page shows it.
   - `GET /api/movies/{id}` itself doesn't carry the average, and `MovieService` doesn't call the Review module. Add that only if another consumer needs it; `MovieService` must not query `reviews` itself.

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
5. **TV shows in lists [Done]**: list items carry a `media_type` (migration `2026_10_28_154529`), unique per list, TMDB id and type, so a list can hold movies and TV shows.
6. **Browse other users' public lists [Done]**
   - `GET /api/lists?scope=public|following|mine&q=`.
   - `&contains={tmdbId}` adds `has_movie` to each list.
   - Frontend: real API (Lists, Discover › Lists, the movie page).
7. **[Modify] Activity hooks**
   - When the Activity module exists, `MovieListService` records `list_created` on create, and `list_movie_added` for films added through `update()` or `addMovie()`.
   - Use `ActivityServiceInterface`, with `is_public` copied from the list.
8. **[Refactor] Small convention gap**: `AddMovieListItemRequest` and `RemoveMovieListItemRequest` have no `toDto()`. See Cross-cutting 3.

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
   - `PUT /api/ratings/{tmdbId}` (`rating` 1–5, optional `type` of `movie` or `tv`, `throttle:60,1`) saves the signed-in member's rating.
   - A new rating is stored as an approved `reviews` row without text. Rating a film you already reviewed only changes the stars.
   - `GET /api/ratings/{tmdbId}` returns your rating, or `null`.
   - Frontend: real API (the movie page's "Your rating", loaded only when signed in).
2. **Schema [Done]**: migration `2026_10_26_000001_allow_rating_only_reviews` made `content` and `sentiment` nullable, and added the unique index on (`user_id`, `tmdb_id`), so each member has one review per film.
3. **Tests [Done]**: `tests/Feature/Social/RatingTest.php` (to be moved, see Cross-cutting 2).
4. **[Refactor] Two modules own the `reviews` table**
   - `app/Review/` now exists and writes review text to the same `reviews` rows that `app/Rating/` writes stars to. A rating is a review without text, so it's one entity: move this module's controller actions, service and repository into `app/Review/`.
   - The `/api/ratings/{tmdbId}` routes can stay, pointing at the Review controller.
   - That way only one module writes `reviews` (`CLAUDE.md` › section 1).
5. **[Refactor] Small convention gaps**
   - `RateMovieRequest` and `ShowRatingRequest` have no `toDto()` (see Cross-cutting 3).
   - The controller builds `['rating' => ...]` by hand instead of returning a response DTO.
6. **[Optional] Remove a rating**: there's no way to clear your stars. If added, removing the stars from a row that has review text should keep the review.
7. **[Modify] Activity hook**: record `rating` through `ActivityServiceInterface` when it exists (Activity 1).

---

## Review (`app/Review/`)

Brief objective 4 (User Ratings and Reviews) and objective 7 (Sentiment Analysis).

The movie page's reviews now run on the real API for movies and TV shows. The module has two services, one for the movie page (`MovieReviewServiceInterface`) and one for admin moderation (`ReviewServiceInterface`), plus `ReviewModerationService` and `SentimentClassifier`. Star ratings still live in `app/Rating/` (Rating 4).

1. **Rate movies [Done]**: see Rating. Fold that module into this one (Rating 4).
2. **Write and edit a review [Done]**
   - `PUT /api/reviews/mine` (`tmdb_id`, `type`, `rating`, `content`, `has_spoilers`; `throttle:20,1`) creates or replaces the member's review. The text goes onto the member's existing rating row, so each member has one row per title.
   - Frontend: real API (`MovieReviews.tsx`).
   - **[Missing]** It requires sign-in only. Whether a verified email is needed is still undecided (Auth 6).
3. **Delete your own review [Done]**: `DELETE /api/reviews/mine?tmdb_id=&type=` removes the text and keeps the star rating. There is no way to delete the stars together with the text (Rating 6).
4. **View reviews and the average rating [Done]**
   - `GET /api/reviews?tmdb_id=&type=&sort=&page=` returns a `summary` (average, count, distribution per star, sentiment in percent), your own review and one page of other members' reviews, with `has_more`.
   - Frontend: real API. The "Most helpful" sort is backed by the helpful votes (item 8).
5. **Moderation [Done]**
   - **Automatic:** `ReviewModerationService` asks Groq to label the text `offensive language`, `spoiler` or `spam`. A review with no labels is approved at once. A review with labels, or one saved while Groq is down, waits as pending. The labels are stored in `reviews.moderation_labels`, and a spoiler label also sets `has_spoilers`.
   - **Administrators and moderators:** the queue, approve, reject and delete are in Admin 8, through `ReviewServiceInterface`. Rejecting clears the text, keeps the stars and sets `removed_at`.
   - Rating-only rows are saved approved. `ProfilePrivacy::seesUnapprovedReviews()` lets authors and staff see unapproved reviews.
   - **[Refactor]** `ReviewPolicy` is still in `app/Policies/` (Cross-cutting 1). Move it to `app/Review/Policies/`.
6. **Sentiment classification [Done]**
   - `SentimentClassifier` labels the text `positive`, `neutral` or `negative` through Groq structured output. If Groq fails, the member's stars decide (4–5 positive, 3 neutral, 1–2 negative), so the write never fails.
   - Rating-only rows keep `sentiment = null`. Frontend: the `Sentiment` component.
   - The unused Gemini module was not used for this (Gemini 1).
7. **Reviews on public profiles [Rebuild]**
   - The brief says "View public profiles, reviews and movie lists".
   - The `reviews` list was removed from `GET /api/users/{username}` (only `counts.reviews` remains, which counts only reviews with text).
   - Restore it through a `ReviewServiceInterface::forProfile(User $owner, CurrentUser $viewer)` method, honouring the `reviews` privacy section.
   - Frontend: real API, which reads `data.reviews ?? []`.
8. **Helpful votes and spoilers [Done, an extra]**
   - `POST` and `DELETE /api/reviews/{review}/helpful` (`throttle:60,1` on the vote) mark a review helpful. A vote needs a review the member is allowed to see, and votes are cleared when a review is resubmitted. Table `review_votes`.
   - `has_spoilers` is set by the author or by the moderation labels.
9. **Reports [Done in the backend]**
   - `POST /api/reviews/{review}/report` (`reason`: Spam, Links, Spoilers or Offensive language; `throttle:10,1`). See Admin 8 for the rules and the moderation queue.
   - **[Missing]** The Report button on the movie page's review cards.
10. **Activity hooks [Missing]**: record `review` (or `rating` for rating-only) through `ActivityServiceInterface` once Activity exists.
11. **Layout [Done, with gaps]**
    - `Controllers/MovieReviewController` (index, save, destroy, helpful, unhelpful, report).
    - `FormRequests/` (`MovieReviewIndexRequest`, `SaveReviewRequest`, `MyReviewRequest`, `ReportReviewRequest`). `MyReviewRequest` has no `toDto()`.
    - `InterfaceServices/` (`MovieReviewServiceInterface`, `ReviewServiceInterface`, `ReviewModerationServiceInterface`), `Services/` (`MovieReviewService`, `ReviewService`, `ReviewModerationService`, `SentimentClassifier`), with `MovieReviewRepository` and `ReviewRepository`.
    - **[Refactor]** `SentimentClassifier` has no interface. The old plan named a `SentimentAnalyzerInterface`.
    - **[Missing]** `Policies/` (see item 5). The routes are in `routes/social.php`, and the paths in `openapi/paths/social.json`.
12. **Tests [Done]**: `tests/Feature/Social/MovieReviewsTest.php` and `tests/Feature/Review/ReviewReportTest.php`, plus the admin moderation tests (Admin 13). `MovieReviewsTest` should move to `tests/Feature/Review/` (Cross-cutting 2).

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
   - `POST /api/rooms/{room}/read` broadcasts `RoomMessagesRead`. A message is shown for 24 hours, then only to the members who haven't read it; it's deleted once its 24 hours are up and every member at the time it was sent has read it (the scheduled `rooms:prune-messages` deletes the ones read in time).
   - **Design note:** the server is the only copy; browsers save nothing. A member on a new device, or someone who joins later, sees the last 24 hours of messages.
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

Brief objective 5: Personal Watchlist. Watchlist rows now carry a `media_type`, so TV shows can be added too.

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
   - `MovieReviewsTest` and `TvShowsTest` were added there too. Review tests go to `tests/Feature/Review/`.
   - Split them into `tests/Feature/User/`, `tests/Feature/MovieList/`, `tests/Feature/MovieNight/`, `tests/Feature/Watchlist/` and `tests/Feature/Rating/` (or `Review/`), per `CLAUDE.md` › section 10.
3. **[Refactor] FormRequests without `toDto()`**
   - `RateMovieRequest`, `ShowRatingRequest`, `AddMovieListItemRequest`, `RemoveMovieListItemRequest`, `MyReviewRequest`, `RoomSocketRequest`, `ChatSocketRequest` and `UploadAvatarRequest` have no `toDto()`.
   - `CLAUDE.md` › section 4 says every FormRequest exposes `toDto()`. Either add `toDto()` with a small `*Data` class, or add an exception to `CLAUDE.md` for single-field requests. Pick one and apply it everywhere.
4. **[Refactor] Modules reading other modules' tables directly**
   - `CLAUDE.md` › section 5 says to go through the owning module's service interface. Fix these as the owning modules gain the right methods:
     - `User\Repository\HomeRepository` queries `DiscussionRoom` and its members (Room 8).
     - `Room\Repository\RoomRepository` writes `notifications` (Room 6).
     - `Watchlist\Repository\WatchlistRepository::ratingsByTmdbId()` and `User\Repository\ProfileRepository` (top 4, review counts) query `reviews`. Use `ReviewServiceInterface`, which now exists, and add the methods those modules need.
5. **Decision: repositories that throw exceptions**
   - `CLAUDE.md` › section 5 says repositories never throw domain exceptions. Yet `MovieRepository` (`MovieNotFound`), `PersonRepository` (`PersonNotFound`) and `WeatherRepository` (`WeatherUnavailable`) translate third-party errors into module exceptions.
   - That's a reasonable pattern for repositories that wrap an external API. Either allow it explicitly in `CLAUDE.md`, or move the translation into the services.
6. **Security [Done], with gaps**
   - **Done:** every write goes through a FormRequest with validation, ownership and room access are enforced by policies, auth cookies are HttpOnly, and auth, TMDB, AI, weather, rating, room and cinema routes are rate-limited.
   - **Gaps:**
     - The admin frontend routes now have a role guard (Admin 11). Reporting a review checks the same author-privacy rule as helpful votes.
     - Email verification isn't enforced anywhere (Auth 6).
     - `/api/weather-mood` and `/api/movie-recommendations` call the AI without sign-in, and `/api/nearby-cinemas` spends the Google Places key without sign-in. Each is protected only by a rate limit; consider requiring `auth.jwt`.
     - Room messages can't be moderated (Room 9).
7. **Frontend still on mock data, or calling removed endpoints:**
   - Admin: all four pages are on the real API (Admin 1 to 9). Still missing is the member-facing Report button on review cards (Admin 8, Review 9).
   - The movie page's reviews and average rating are on the real API. Only showtimes are still mock data (demo movies only).
   - For You (every shelf except the weather row).
   - The Notifications page, which calls endpoints that no longer exist.
   - Now on the real API: Rooms, Person, Cinemas, star ratings, "Add to list", reviews and the admin pages.
   - Other pages still import mock files (`homeData.ts`, `listsData.ts`, `listDetailData.ts`, `nightsData.ts`, `nightDetailData.ts`, `roomsData.ts`, `settingsData.ts`, `watchlistData.ts`, `profileData.ts`, `personData.ts`, `discoverData.ts`, `movieData.ts`). Some are only fallbacks or demo data; check each before deleting.
8. **Database leftovers**
   - `calendar_events` is unused (Calendar 3).
   - `notifications` is used only by Room read receipts (Notification 2, Room 6).
   - `activity_feed` is kept for Activity, and `reviews` is now shared by Rating and the future Review module (Rating 4).
9. **Test status**: not re-run for this update. The last recorded run was 355 passing tests on `Admin-Features`, before the Review, moderator and report work. The social redirect test that used to fail because of the Google credentials in `.env` is fixed (Auth 12).
