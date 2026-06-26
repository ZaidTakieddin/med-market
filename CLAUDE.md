# CLAUDE.md

Operating guide for working in this repository. Read this first, then consult the
detailed docs it links to. Authoritative rules live in
[`.cursor/rules/codebase_rules.mdc`](.cursor/rules/codebase_rules.mdc) — this file
summarizes and points to them; if anything conflicts, the rules file wins.

## What this is

**Med Market** — a Laravel 12 API-first backend for a B2B medical delivery system
connecting **suppliers** (wholesalers/retailers) with **buyers** (pharmacies and
salesmen). Single source of truth for all business logic; clients (web + mobile) are
thin consumers.

- **Framework**: Laravel 12, PHP 8.2+ (8.4 in Docker/prod)
- **DB**: PostgreSQL on Neon (pooled connection for API, unpooled for seeders/queue)
- **Auth**: Laravel Sanctum (bearer tokens)
- **Queue**: database driver (Redis-ready); jobs in `app/Jobs`
- **Cache**: file-based locally, Redis-ready for prod
- **API docs**: L5-Swagger at `/api/documentation`, base path `/api/v1`

## Architecture: modular monolith + DDD

Business logic lives in **`app/Domain/<Context>/`**, not in controllers or models.

```
app/
├── Domain/           # Business logic lives HERE (services, DTOs, state machines)
│   ├── Orders/       # OrderCreationService, OrderStateTransitionService (states: Constants\OrderStatus)
│   ├── Inventory/    # StockMovementService
│   ├── Pricing/      # PricingService, PriceResult DTO  (multi-tier price resolution)
│   ├── Products/  Companies/  Deliveries/  Cart/  Users/
├── Http/Controllers/Api/V1/   # Thin controllers — validate, authorize, delegate
├── Http/Requests/             # Form Request validation (extend BaseFormRequest)
├── Http/Resources/            # API response transformers (camelCase output)
├── Jobs/                      # Queued: SendOrderNotification, GenerateInvoice, LowStockAlert, ExportOrders
├── Policies/                  # Authorization (role + company scoped)
├── Observers/                 # OrderObserver, StockMovementObserver, ProductObserver
├── Constants/                 # Enums-as-constants (see below)
└── Models/
```

> Note: `app/Services/` exists but is empty — shared/business logic goes in `app/Domain/`.

Each domain has its own README — **read the relevant one before changing that domain**:
[Orders](app/Domain/Orders/README.md) ·
[Inventory](app/Domain/Inventory/README.md) ·
[Pricing](app/Domain/Pricing/README.md) ·
[Products](app/Domain/Products/README.md) ·
[Companies](app/Domain/Companies/README.md) ·
[Deliveries](app/Domain/Deliveries/README.md)

## Hard rules (non-negotiable)

These come straight from the codebase rules — violating them is a correctness/data bug:

1. **Controllers are thin.** No DB queries, no business rules. Validate → authorize →
   call a domain service → return a standardized response.
2. **Money/stock/order changes are transactional.** Wrap in DB transactions. Roll back
   on any failure.
3. **Never hard-delete** Orders, Invoices, or Stock movements — use soft deletes for all
   critical tables.
4. **Never overwrite stock directly.** Every stock change is a `StockMovement` recording
   type, quantity, before/after, actor, order (if any), timestamp. Stock cannot go negative.
5. **Orders are a validated state machine** (see below). Invalid transitions are rejected.
   Every transition is logged to `OrderStatusLog`.
6. **Authorization is role-based AND company-based.** Never trust client role claims.
   Guard sensitive actions with Policies. Users only touch their own company's data.
7. **All input validated** in Form Request classes. Never swallow exceptions; log
   unexpected ones.
8. **No magic strings/numbers** — use the constants in `app/Constants/`.
9. **Standardized API responses**: `{ success, data }` or `{ success: false, error, errors }`.
   Version under `/api/v1`. Paginate all list endpoints (`per_page`, max 100; `?per_page=all` to disable).

## Order state machine

States: `pending → accepted → prepared → shipped → delivered`, with `cancelled` reachable
from `pending`, `accepted`, or `prepared` — **not once `shipped`** (shipped can only go to
`delivered`). `delivered` and `cancelled` are final. Single source of truth: `App\Constants\OrderStatus`.

- Stock is **deducted** on transition to `accepted`/`prepared`; **reverted** on `cancelled`.
- Transition to `accepted` auto-dispatches `GenerateInvoiceJob`.
- All handled by `OrderStateTransitionService` — do not transition orders by hand.

## Multi-tier pricing (read before touching prices)

Price resolution priority (`Pricing/PricingService::resolve`):
**custom price list → wholesale tier (if buyer eligible & qty ≥ `wholesale_min_qty`) →
retail/`pharmacist_price` → product discount → promotional offers**.

- Wholesale min qty is enforced **per line item**, not per order.
- Salesman below min qty falls back to **retail** (not rejected).
- Wholesale-only suppliers are **hidden** from pharmacy buyers (`canBuyerSeeSupplier`).
- Every cart/order item records `pricing_type` (`wholesale`/`retail`/`custom`) for audit.

Full detail: [Pricing README](app/Domain/Pricing/README.md).

## Constants (use these, never string literals)

`OrderStatus`, `UserRole` (admin/supplier/buyer/driver/salesman), `CompanyType`,
`SupplierClassification` (wholesale/retail/hybrid), `BuyerClassification`
(pharmacy/salesman), `PricingType`, `PaymentTerms` (cash/net_15/net_30/net_60),
`DiscountType`, `OfferType`, `StockMovementType` — all in `app/Constants/`.

## Documentation discipline (required by the rules)

**[PROGRESS.md](docs/PROGRESS.md) is the project brain.** After any change, update it: what was
done, when, why, and any mistakes/resolutions. Keep its "Last Updated" date current.

- Queue changes → also update [QUEUE_WORKERS.md](docs/QUEUE_WORKERS.md)
- Deployment/env changes → also update [DEPLOYMENT.md](docs/DEPLOYMENT.md)
- Domain changes → update that domain's README
- API changes → **regenerate Swagger**: `php artisan l5-swagger:generate` and update
  OpenAPI annotations on the touched endpoints.

No change ships without updating the relevant docs.

## Common commands

```bash
php artisan serve                      # dev server
php artisan migrate                    # run migrations (--force in prod)
php artisan db:seed                    # seed (uses unpooled connection; Arabic seed data)
php artisan l5-swagger:generate        # regenerate API docs after endpoint changes
php artisan queue:work --queue=notifications,invoices,alerts,exports
php artisan suppliers:sync-categories-manufacturers   # rarely needed (ProductObserver auto-syncs)
php artisan test                       # tests live in tests/Feature and tests/Unit
```

## Code quality (Pint + Larastan)

Two tools gate code quality; run them before declaring a task done:

```bash
composer lint:dirty   # Pint — auto-format only git-changed files (preferred)
composer analyse      # PHPStan/Larastan static analysis (level 5)
composer quality      # both: pint --dirty, then phpstan
```

- **Pint** uses the Laravel preset. The repo is fully normalized, so keep it that way —
  format **changed** files with `--dirty` before finishing; `pint --test` must stay green.
- **Larastan** runs at **level 8** with a **baseline** (`phpstan-baseline.neon`, **4**
  grandfathered findings — down from 379 at level 5). Only *newly introduced* errors
  fail. Fix the root cause — never add `@phpstan-ignore`/baseline entries to silence new
  errors. Controllers get the authenticated user via `BaseApiController::currentUser()`
  (non-null) rather than `auth()->user()`. Continue raising the `level` as debt is paid.
- **CI** (`.github/workflows/ci.yml`) runs `pint --test` + Larastan + `php artisan test`
  on push to `main` and every PR.
- A **Stop hook** (`.claude/hooks/code-quality.php`, wired in `.claude/settings.json`)
  runs this gate when a turn finishes: it skips if no PHP changed, clears the PHPStan
  result cache, auto-formats with Pint, and blocks on new PHPStan errors. Pinned to
  `shell: powershell` because Herd's `php` resolves there, not in Git Bash.

## Current status (see docs/PROGRESS.md for the full picture)

Implemented & audited: auth, orders + state machine + invoices, products/categories/
manufacturers, companies + classifications, multi-tier pricing (all 6 phases), cart +
offers, drivers + regions, salesman management + on-behalf ordering, buyer-supplier
relationships + credit (enforced), custom price lists, location (cities/regions),
background jobs, API resources, observers.

**Testing & tooling**: PHPUnit feature/unit suite with model factories and a cross-DB
(sqlite) test database; GitHub Actions CI; repo normalized to the Pint baseline.
Authorization is Policy-based on every resource model; controllers are thin and never
leak exception messages.

**Notably NOT done yet** (good candidates for new work):
- Broader caching — only the Category/Manufacturer reference lists are cached so far.
- Dashboard/statistics depth, user-management (admin), product images, payment
  integration, real-time/notifications infra — see "Missing Features" in docs/PROGRESS.md.

**Known technical debt**: at **level 8** the PHPStan baseline is down to **4**
grandfathered findings (a generic-paginator helper return type, a Larastan dynamic-pivot
limitation ×2, and a collection-count nit). The codebase is fully typed and nullable-safe
through level 8. Remaining: level 9 (strict `mixed` handling — likely impractical given
framework magic) and broader caching (needs a visibility-aware cache-key design).

## Final rule

If a decision affects **data integrity, security, or money: STOP → THINK → IMPLEMENT
SAFELY.**

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.

## Foundational Context
This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.4
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v11

## Conventions
- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts
- Do not create verification scripts or tinker when tests cover that functionality and prove it works. Unit and feature tests are more important.

## Application Structure & Architecture
- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling
- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Replies
- Be concise in your explanations - focus on what's important rather than explaining obvious details.

## Documentation Files
- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

## Laravel Boost
- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan
- Use the `list-artisan-commands` tool when you need to call an Artisan command to double-check the available parameters.

## URLs
- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Tinker / Debugging
- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool
- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)
- Boost comes with a powerful `search-docs` tool you should use before any other approaches when dealing with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- The `search-docs` tool is perfect for all Laravel-related packages, including Laravel, Inertia, Livewire, Filament, Tailwind, Pest, Nova, Nightwatch, etc.
- You must use this tool to search for Laravel ecosystem documentation before falling back to other approaches.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries to start. For example: `['rate limiting', 'routing rate limiting', 'routing']`.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax
- You can and should pass multiple queries at once. The most relevant results will be returned first.

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

## PHP

- Always use curly braces for control structures, even if it has one line.

### Constructors
- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

### Type Declarations
- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Comments
- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless there is something very complex going on.

## PHPDoc Blocks
- Add useful array shape type definitions for arrays when appropriate.

## Enums
- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

=== laravel/core rules ===

## Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Database
- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation
- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources
- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

### Controllers & Validation
- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

### Queues
- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

### Authentication & Authorization
- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

### URL Generation
- When generating links to other pages, prefer named routes and the `route()` function.

### Configuration
- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

### Testing
- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

### Vite Error
- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

## Laravel 12

- Use the `search-docs` tool to get version-specific documentation.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

### Laravel 12 Structure
- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console\Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

### Database
- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models
- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

## Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

## PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should test all of the happy paths, failure paths, and weird paths.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

### Running Tests
- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).
</laravel-boost-guidelines>
