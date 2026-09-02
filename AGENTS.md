# AGENTS.md - Zitazi Codebase Guide

## Project Overview

Zitazi is an ecommerce price monitoring platform that crawls Trendyol, Decathlon, and other sources, then syncs pricing
data to Zitazi's WooCommerce store.

## Architecture

- **Laravel 11 app** (`src/`) - API, admin panel (Filament), queue workers
- **Node.js scraper** (`scraper/`) - Puppeteer-based headless scraping via Redis queues
- **Docker Compose** - Separate dev/prod configurations

## Queue Flow (Critical)

Scraping uses Redis pub/sub with specific queue names:

1. **Input queues** (Laravel → Scraper):
    - `trendyol_scrape_product` (Trendyol products)
    - `decathlon_scrape_product` (Decathlon products)

2. **Output queue** (Scraper → Laravel):
    - `scrape_result`

3. **Listener command**: `php artisan listen:scrape` processes results

Queue names defined in `src/config/queue.php`:

```php
'TR_QUEUE_IN' => 'trendyol_scrape_product',
'DE_QUEUE_IN' => 'decathlon_scrape_product',
```

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
| `scraper/daemon.js`                                           | Scraper entry point with queue listeners          |
| `docker-compose.dev.yml`                                      | Development services                              |
| `docker-compose.prod.yml`                                     | Production services with monitoring stack         |

## Gotchas

- **Queue prefix**: Docker Compose prepends `laravel_database_` to queue names
- **Cooldown**: 20-minute cooldown on bot detection (configurable in `scraper/daemon.js`)
- **Sync status**: Products have a `sync_status` field tracking their lifecycle
- **Test data**: Always use HTTP fakes in tests (see `ProductTest::setUp()`)
