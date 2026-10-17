<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version
of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:

- PHP packages: run `composer show --direct` to list direct dependencies with versions, or
  `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you
work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling
  files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature
  tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run
  `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual
  alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before
  sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific
  syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient
  results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most
  relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not
  `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped
  framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open
  @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path (s) in scope,
  and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read
  and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to
  discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`,
  `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`,
  `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests
  with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
    - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty
  zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters:
  `function isAccessible(User $user, ?string $path = null): bool`
- Follow existing application Enum naming conventions.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and
  scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or
  resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list
  available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the
  correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other
  things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you
  should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be
  used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to
  use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit`
  to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to
  ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting
  issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to
  `php artisan test --filter=testName`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName`
  arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test`.

</laravel-boost-guidelines>

# AGENTS.md - Zitazi Codebase Guide

## Project Overview

Zitazi is an ecommerce price monitoring platform that crawls Trendyol, Decathlon, and other sources, then syncs pricing
data to Zitazi's WooCommerce store.

## Architecture

- **Laravel app** (`src/`) - API, admin panel (Filament), queue workers
- **Node.js scraper** (`scraper/`) - Puppeteer-based headless scraping via Redis queues
- **Docker Compose** - Separate dev/prod configurations

## Queue Flow (Critical)

Scraping uses Redis pub/sub with specific queue names:

1. **Input queues** (Laravel → Scraper):
    - `trendyol_scrape_product` (Trendyol products)
    - `decathlon_scrape_product` (Decathlon products)
    - `torob_scrape_product` (Torob products — checked **first**)

2. **Output queue** (Scraper → Laravel):
    - `scrape_result`

3. **Listener command**: `php artisan listen:scrape` processes results

Queue names defined in `src/config/queue.php`:

```php
'TR_QUEUE_IN'    => 'trendyol_scrape_product',
'DE_QUEUE_IN'    => 'decathlon_scrape_product',
'TOROB_QUEUE_IN' => 'torob_scrape_product',
```

Routing lives in `ScraperQueueService` (`sendToAllScraperQueues` / `addToForeignQueue`): Torob products are pushed to the Torob queue first; Trendyol/Decathlon products to their foreign queue. `ENQUEUED` is set before `Redis::lPush`.

## Torob Pricing (Business Logic)

- Before pricing a Decathlon/Trendyol product, the Torob price is fetched first to discover the lowest competing seller → stored in `products.rival_min_price` (`null` when Zitazi's own shop — `services.zitazi.torob_shop_id` — is cheapest or there are no valid sellers).
- **20% min profit floor**: the destination price never goes below `Currency::convertToRial($sourcePrice, 1.20)`.
- **99.5% rule**: recommended price = `rival_min_price * 0.995`, rounded down to the nearest 1000, clamped above the 20% floor.
- Foreign products (Torob + Trendyol/Decathlon) are re-pushed to the foreign queue after the Torob response so pricing applies against the rival. Pure-Torob products go through `TorobService::updateProductAndSync` → `SyncWoocommerceProductJob`.

## Development Commands

### Laravel (in `src/`)

```bash
# Run full dev stack (server, queue, logs, vite)
composer dev

# Individual services
php artisan serve
php artisan queue:listen --tries=1
php artisan pail --timeout=0
npm run dev  # Vite frontend

# Queue worker (production)
php artisan queue:work --tries=3
```

### Scraper (in `scraper/`)

```bash
npm install && npx nodemon daemon.js
```

### Docker (root)

```bash
# Development
docker compose -f docker-compose.dev.yml up -d

# Production
docker compose -f docker-compose.prod.yml up -d
```

## Key Artisan Commands

| Command              | Purpose                            |
|----------------------|------------------------------------|
| `listen:scrape`      | Process scrape results from Redis  |
| `listen:sync-status` | Process sync status updates        |
| `sync-products`      | Sync product data to external APIs |
| `sync-zitazi`        | Sync to Zitazi store               |
| `seed-products`      | Seed/update product variations     |

## Product Source Constants

Defined in `src/app/Models/Product.php`:

```php
SOURCE_TRENDYOL = 'trendyol'
SOURCE_DECATHLON = 'decathlon'
SOURCE_ETH = 'eth'
SOURCE_AMAZON = 'amazon'
SOURCE_SAZ_KALA = 'saz_kala'
SOURCE_Elele = 'elele'
```

## Destination Stores

- `base_source` on `products`/`variations` is `Product::ZITAZI` ('zitazi') or `Product::SATRE` ('satre') — never raw strings elsewhere.
- Per-store sync toggle: cache keys `sync_zitazi_enabled` / `sync_satreh_enabled` (fallback `services.zitazi_sync_enabled` / `services.satreh_sync_enabled`), selected by `base_source`. There is **no** single `sync_enabled` key anymore.

## Testing

- Framework: PHPUnit
- Run tests: `cd src && php artisan test`
- Test data: `src/tests/Data/` (JSON fixtures)
- Key test: `src/tests/Feature/ProductTest.php`

## Deployment

- CI/CD: GitHub Actions on `master` branch
- Builds Docker images to GHCR
- Deploys to VPS via SSH
- Post-deploy: runs migrations automatically

## Important Files

| File                                                          | Purpose                                           |
|---------------------------------------------------------------|---------------------------------------------------|
| `src/config/queue.php`                                        | Queue configuration including scraper queue names |
| `src/app/Console/Commands/ListenForScrapeResponseCommand.php` | Scraping result processor                         |
| `src/app/Services/ScraperQueueService.php`                    | Torob-first scrape queue routing                  |
| `src/app/Services/TorobService.php`                           | Torob rival pricing + pure-Torob sync             |
| `scraper/daemon.js`                                           | Scraper entry point with queue listeners          |
| `docker-compose.dev.yml`                                      | Development services                              |
| `docker-compose.prod.yml`                                     | Production services with monitoring stack         |

## Gotchas

- **Queue prefix**: Docker Compose prepends `laravel_database_` to queue names
- **Cooldown**: 20-minute cooldown on bot detection (configurable in `scraper/daemon.js`)
- **Sync status**: Products have a `sync_status` field tracking their lifecycle
- **Test data**: Always use HTTP fakes in tests (see `ProductTest::setUp()`)
