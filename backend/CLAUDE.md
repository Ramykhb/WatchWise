# Commit Attribution

Do not add Claude, Anthropic, any Claude model, or GitHub Copilot as a commit co-author. Never insert their `Co-Authored-By` trailers. Preserve legitimate human authors and co-authors.

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.4. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- This project uses the streamlined Laravel 11+ structure: register middleware, exceptions, and routing in `bootstrap/app.php` and service providers in `bootstrap/providers.php`. There is no `app/Http/Kernel.php` or `app/Console/Kernel.php`, and commands in `app/Console/Commands/` auto-register.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.

- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>

# WatchWise Module Architecture (mandatory)

This section is project-specific and sits outside the Boost block so Boost updates don't overwrite it. It describes the structure `app/Auth`, `app/Genre`, `app/Movie`, `app/MovieList`, `app/MovieNight`, `app/Watchlist` and `app/User` follow. **All new backend code must follow it. Do not write code in the old layered style (`app/Http/*`, `app/Services/*`) and do not leave code to be "refactored later".** When in doubt, open `app/Auth` or `app/Watchlist` and copy their shape.

## 1. Organise by feature, never by technical type

- Each feature (entity or business area) is one self-contained module folder under `app/`: `app/<Feature>/`. Everything the feature needs lives inside it: controllers, requests, DTOs, services, repositories, exceptions, policies, resources, provider.
- Do not create or revive `app/Http/`, `app/Services/`, `app/Repositories/`, `app/Policies/` or any other folder that groups files by technical type across features. Those folders were deleted on purpose.
- **Exceptions that stay global:** `app/Models/` (all Eloquent models, shared by every module; never move them into modules), `app/Providers/` (framework-level providers such as `AuthorizationServiceProvider`), `app/Console/`, and `app/Support/` (see section 6).
- A new module is a new top-level folder under `app/`. This is the one case where a new base folder is acceptable, but tell the user first, per the rule above.
- Name the module after the entity or business area, singular and PascalCase (`Watchlist`, `MovieNight`, `User`). Code that belongs to one entity must not be placed in another module's folder; if two modules need it, it goes in `app/Support/` or one module exposes it through its service interface.

## 2. Standard module layout

Create only the folders the feature needs, but use exactly these names:

```
app/<Feature>/
  Controllers/          one controller per feature/layer, thin
  FormRequests/         validation + authorization, one per input shape, each with toDto()
  DTOs/
    Requests/           final readonly input objects, built by fromValidated()/toDto()
    Responses/          output objects implementing JsonSerializable
  InterfaceServices/    <Name>ServiceInterface
  Services/             <Name>Service implements the interface; business logic lives here
  InterfaceRepository/  <Name>RepositoryInterface
  Repository/           <Name>Repository implements the interface; all Eloquent/queries live here
  Exceptions/           domain exceptions that render themselves
  Policies/             <Model>Policy for models this module owns
  Resources/            Eloquent API Resources (only when the shape is shared or model-backed)
  Providers/            <Feature>ModuleServiceProvider (bindings)
```

Folder names are fixed: `InterfaceServices`, `InterfaceRepository` and `Repository` (singular) are the project convention. Do not rename them to `Contracts`, `Repositories`, etc.

## 3. One controller per feature, thin

- **One controller per feature/layer.** Related actions go in the same controller (`UserController` holds home, me, preferences, password, avatar, social disconnect, index, show, followers, following, follow, unfollow). Never split one entity across `MeController`/`ProfileController`/`HomeController`-style siblings, and never create a second controller for the same entity. Only add another controller when it is a genuinely separate concern with its own routes (as `AuthController` vs `SocialAuthController` vs `PasswordResetController` in Auth).
- Controllers are **plain classes**. There is no base `Controller`; do not add one and do not `extends Controller`.
- Controller methods do only this: take a FormRequest (or route-bound model), call one service method with a DTO or the validated primitives, and return a response (a Response DTO, a Resource, or `response()->json(...)` with the right status). Nothing else.
- Controllers must not contain: validation, queries or Eloquent calls, business rules, transactions, `if` chains over domain state, or HTTP calls to third parties. All of that belongs in the service or repository.
- Inject the **service interface**, never a concrete service, repository or model query. Inject the authenticated user through `App\Auth\CurrentUser\CurrentUser`, not `auth()` or `$request->user()`.
- For route-bound actions (`show`, `update`, `destroy` on a model), authorize in the controller with `Gate::authorize('<ability>', $model)`. See section 5.

## 4. Requests, DTOs and responses

- **FormRequests** live in `FormRequests/`, named after the action (`StoreWatchlistRequest`, `UpdateMovieNightRequest`). They own validation rules and `authorize()`. Every FormRequest exposes a `toDto()` that returns the request DTO, so controllers never read raw `$request->input()`.
- **Request DTOs** (`DTOs/Requests/`) are `final readonly` classes with a typed constructor and a static `fromValidated(array $data): self`. Name them after the intent (`AddToWatchlistData`, `UpdateWatchlistStatusData`, `CreateMovieListData`), suffix `Data`.
- **Response DTOs** (`DTOs/Responses/`) are classes implementing `JsonSerializable`, suffix `Response` (`WatchlistResponse`, `MovieNightOverviewResponse`). They are the single place that defines the JSON shape of an endpoint, so the API contract does not drift between controller and service. Use Eloquent Resources only when you are serialising a model (or collection) that is reused across modules (`MemberResource`, `MovieListResource`).
- Services return DTOs/models/collections, never `JsonResponse`. If the HTTP status depends on the outcome (e.g. created vs existing), the service returns a response DTO carrying the flag (`AddToWatchlistResponse::$created`) and the controller picks 201 or 200.
- Do not pass the `Request` object, arrays of loose validated input, or `CurrentUser` internals deeper than the controller/service boundary when a DTO would be clearer.

## 5. Services, repositories, policies

- **Services** (`Services/`) hold all business logic and orchestration: rules, transactions, calling other modules' service interfaces, calling the HTTP client for third parties. They implement an interface from `InterfaceServices/` and depend only on **interfaces** (their own repository interfaces, other modules' service interfaces, `CurrentUser`).
- **Repositories** (`Repository/`) are the only place that touches Eloquent, `DB::`, query builders or `Http::`-style persistence calls for that module's data. Services never call `Model::query()`, `->where()`, `->create()` directly. Repositories contain no business rules and never throw domain exceptions; they return models, collections, scalars or `null`.
- Every service and repository has an interface in its `Interface*` folder, and the interface is what everything else type-hints. This keeps services testable and swappable.
- A service may use another module's **service interface**. It must not use another module's repository, controller, FormRequest or DTO internals. If you need data owned by another module, add a method on that module's service interface.
- **Policies** live in `<Feature>/Policies/`, named `<Model>Policy`, and are registered in `app/Providers/AuthorizationServiceProvider.php` (the Gate is bound to `CurrentUser` there). Authorization rules go in policies, never inline in controllers or services.
  - Route-bound model actions: `Gate::authorize('ability', $model)` in the controller.
  - Create/list actions with no bound model: `Gate::allows(...)` inside `FormRequest::authorize()` so authorization runs **before** validation.
  - A FormRequest `authorize()` that returns `true` is only acceptable when the service enforces the rule itself (document that in a PHPDoc block).

## 6. Exceptions, shared code and errors

- **Domain exceptions** live in `<Feature>/Exceptions/`, named for the failure in plain words (`NotInvitedToMovieNight`, `VotingClosed`, `IncorrectPassword`), and implement `render()` so they produce the right status and JSON body themselves. Services throw them; controllers never catch them to build responses. Do not use `abort(...)` or `response()->json([...], 4xx)` for domain errors outside a service/exception.
- Do not throw generic `\Exception`/`\RuntimeException` for expected business failures.
- **`app/Support/`** is for code that is genuinely shared by several modules and has no single owner: the TMDB HTTP client (`TmdbHttpClient`), shared exceptions (`TmdbNotFound`, `TmdbUnavailable`), small helpers (`LikePattern`). Before putting something in `Support/`, confirm at least two modules use it. If only one module uses it, it lives in that module (for Auth, `app/Auth/Support/`).
- Do not copy a helper between modules. Move it to `Support/` and import it.

## 7. Service providers and registration

- Each module has `Providers/<Feature>ModuleServiceProvider.php` with a `public array $bindings` map from each interface to its implementation (service and repository). Register it in `bootstrap/providers.php`.
- Never `new` a service or repository inside a controller or another service, and never bind in `AppServiceProvider` for a module's classes.
- Policy to model mapping stays in `AuthorizationServiceProvider`.

## 8. Routes and API documentation

- Routes call module controllers by their module namespace (`use App\User\Controllers\UserController;`). Group related routes with `Route::controller(...)`/`prefix(...)` as `routes/social.php` does; keep one routes file per area as today. Use `apiResource` when the controller follows REST conventions.
- Route names, URLs and response shapes are a **public contract**. Do not rename or remove endpoints, or remove/rename response fields, as a side effect of a refactor. If a change is breaking, tell the user, check the frontend (`../frontend/src`) for callers, and update both together.
- The API is documented by hand in `openapi/paths/*.json`. Any route added, removed or changed must be updated there in the same change, otherwise `OpenApiCoverageTest` fails.

## 9. Naming and style

- Suffixes are part of the convention: `*Controller`, `*Request`, `*Data` (request DTO), `*Response` (response DTO), `*Resource`, `*Service`, `*ServiceInterface`, `*Repository`, `*RepositoryInterface`, `*Policy`, `*ModuleServiceProvider`. Exceptions are named for the failure with no `Exception` suffix.
- Name services by what they do for the feature, not by technology (`AccountService`, `ProfileService`, `HomeService`); a module may have several services when it has several distinct responsibilities, each with its own interface.
- Use `final readonly` for DTOs; constructor property promotion; explicit return types; curly braces always (see the PHP rules above).
- Do not leave dead code, unused imports, commented-out code, or leftover `use` lines. After moving or renaming a class, grep for every reference to the old namespace and fix it.
- Keep the code in a file in its single correct place. Do not leave a copy "for now".

## 10. Tests

- Mirror the module in `tests/Feature/<Feature>/` and prefer feature tests that hit the HTTP endpoints, as the existing suites do. Add a test for every new endpoint and every new business rule (the happy path, the authorization failure and each domain exception).
- Use factories and their states; never create models manually when a factory exists.
- Tests that depend on third parties (TMDB, Gemini, Open-Meteo, Socialite) must fake them and set any config they need inside the test, never rely on `.env` values.
- Run the narrowest tests while working, then the full suite (`php artisan test --compact`) before finishing a refactor. Run `vendor/bin/pint --dirty --format agent` after any PHP change.

## 11. Adding or changing a feature: checklist

1. Find the owning module (or, if none fits, propose a new one to the user). Never put the code in a shared type-based folder.
2. Route -> FormRequest (validation + `authorize()` + `toDto()`) -> controller action (thin) -> service interface method -> service (logic) -> repository interface method -> repository (queries) -> Response DTO/Resource.
3. Add interface methods first, then implement them; bind them in the module provider.
4. Domain failures become exception classes with `render()` in the module.
5. Update `AuthorizationServiceProvider` if you add a policy.
6. Update `openapi/paths/*.json` if routes changed.
7. Add/adjust feature tests, run Pint, run the tests.
8. Verify nothing references a removed/moved namespace (`grep` for the old `App\...` path) and that no endpoint or response field changed unintentionally.

## 12. Refactoring existing code

- Refactor module by module on a dedicated branch, in small commits (move/split, then wire up, then tests, then docs), merging each into the integration branch.
- **Move code; do not silently delete or rewrite behaviour.** If the user asks to delete code or a feature, delete it fully (controllers, services, routes, OpenAPI paths, tests, callers) and list the removed endpoints/fields and what depends on them (including the frontend) before finishing.
- Models, migrations and database tables are left in place unless the user explicitly asks otherwise.
- When two controllers/services/resources for the same entity exist, merge them into one in the entity's module instead of keeping both.
