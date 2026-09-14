<div align="center">

# Zitazi - Ecommerce Price Monitoring Platform

**Automated price crawling, tracking, and syncing for ecommerce stores**

</div>

## About

Zitazi is a distributed price monitoring system that automatically crawls major ecommerce platforms (Trendyol,
Decathlon, and others), extracts product pricing and variation data, and syncs it to a WooCommerce store. The platform
runs scheduled crawlers that scan target sites daily and detect price updates across thousands of products.

Built with a hybrid architecture — **Laravel 11** for orchestration, API, and admin panel, paired with a
**Node.js/Puppeteer** scraper service for headless browser automation.

## Features

### Crawling & Scraping

- Multi-source crawling — Trendyol, Decathlon, and extensible for additional sources
- Headless browser scraping with Puppeteer Extra + Stealth Plugin (avoids bot detection)
- Dynamic content extraction from JavaScript-rendered pages
- Cooldown mechanism — automatic backoff when bot detection is triggered (20-minute cooldown)
- Retry logic for failed/deleted products
- Distributed queue-based scraping via Redis (BLPOP pattern)

### Data Pipeline

- Queue-based architecture with Redis pub/sub for decoupled processing
- Automated price and variation extraction
- Invalid currency detection and handling
- Product lifecycle tracking with sync status management
- Bulk scrape support

### Admin & API

- Laravel Filament admin panel for managing products, queues, and monitoring
- RESTful API for product and price data
- WooCommerce integration for syncing prices to storefront
- Excel export support via Laravel Excel

### Infrastructure

- Docker Compose (separate dev/prod configurations)
- Redis for queue management and caching
- MySQL for persistent storage
- Laravel Horizon for queue monitoring
- CI/CD via GitHub Actions (Docker build + VPS deploy)

## Architecture

```
                    ┌─────────────────────────────────────┐
                    │         Cron Scheduler               │
                    │   (php artisan schedule:run)         │
                    └──────────────┬──────────────────────┘
                                   │ triggers sync commands
                                   ▼
┌──────────────────────────────────────────────────────────────────┐
│                      Laravel Application                         │
│                                                                  │
│  ┌──────────────┐   ┌──────────────────┐   ┌─────────────────┐  │
│  │ Queue Workers │   │  API / Admin     │   │ Sync Commands   │  │
│  │  (Horizon)    │   │  (Filament)      │   │ sync-products   │  │
│  │               │   │                  │   │ sync-zitazi     │  │
│  └──────┬───────┘   └──────────────────┘   └─────────────────┘  │
└─────────┼────────────────────────────────────────────────────────┘
          │ publishes to Redis queues
          ▼
┌──────────────────────────────────────────────────────────────────┐
│                    Redis (Queue Layer)                            │
│                                                                  │
│  trendyol_scrape_product ──►  decathlon_scrape_product           │
│                                                                  │
│                        scrape_result ◄── (output)                │
└─────────────────────────┬────────────────────────────────────────┘
                          │ BLPOP
                          ▼
┌──────────────────────────────────────────────────────────────────┐
│                   Node.js Scraper Service                        │
│                                                                  │
│  ┌─────────────┐  ┌──────────────┐  ┌────────────────────────┐  │
│  │  daemon.js  │  │  scraper.js  │  │  Puppeteer (Chrome)    │  │
│  │  (workers)  │──▶│  (scrape    │──▶│  headless browser      │  │
│  │             │   │   logic)    │  │  + Stealth Plugin      │  │
│  └─────────────┘  └──────────────┘  └───────────┬────────────┘  │
└──────────────────────────────────────────────────┼────────────────┘
                                                   │ HTTP requests
                                                   ▼
                              ┌────────────────────────────────────┐
                              │      Ecommerce Websites            │
                              │  Trendyol  │  Decathlon  │  ...    │
                              └────────────┴────────────┴─────────┘
                                                   │
                                                   ▼
                              ┌────────────────────────────────────┐
                              │   Scraped Price & Variation Data   │
                              └────────────┬───────────────────────┘
                                           │ RPUSH to scrape_result
                                           ▼
                              ┌────────────────────────────────────┐
                              │     Laravel Listener               │
                              │  (php artisan listen:scrape)       │
                              └────────────┬───────────────────────┘
                                           ▼
                              ┌────────────────────────────────────┐
                              │  External Services / WooCommerce   │
                              └────────────────────────────────────┘
```

## Queue Flow (Scraping Pipeline)

The scraping pipeline uses Redis pub/sub for decoupled, asynchronous processing:

1. **Laravel** publishes product scrape jobs to input queues (`trendyol_scrape_product`, `decathlon_scrape_product`)
2. **Node.js daemon** listens via `BLPOP` and processes each product using Puppeteer
3. **Results** are pushed back to the `scrape_result` output queue
4. **Laravel listener** (`php artisan listen:scrape`) consumes results and updates the database

## Technology Stack

### Backend

| Technology      | Purpose               |
|-----------------|-----------------------|
| PHP 8.2         | Runtime               |
| Laravel 11      | Framework             |
| Laravel Horizon | Queue monitoring      |
| Redis           | Queue broker, caching |
| MySQL           | Persistent storage    |
| Filament        | Admin panel           |

### Scraping

| Technology                  | Purpose                              |
|-----------------------------|--------------------------------------|
| Node.js                     | Scraper runtime                      |
| Puppeteer / Puppeteer Extra | Headless browser automation          |
| Puppeteer Stealth Plugin    | Bot detection evasion                |
| ioredis                     | Redis client for queue communication |

### Infrastructure

| Technology                       | Purpose               |
|----------------------------------|-----------------------|
| Docker & Docker Compose          | Containerization      |
| Nginx                            | Web server            |
| GitHub Actions                   | CI/CD                 |
| GHCR (GitHub Container Registry) | Docker image registry |

## Quick Start

### Prerequisites

- PHP 8.2+
- Node.js 18+
- Composer
- Docker & Docker Compose
- Redis

### Development Setup

```bash
# 1. Clone the repository
git clone <repo-url>
cd zitazi

# 2. Laravel setup
cd src
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed

# 3. Scraper setup
cd ../scraper
npm install

# 4. Start services (Docker)
docker compose -f docker-compose.dev.yml up -d

# 5. Start Laravel dev server
cd src
composer dev   # Runs: serve + queue:listen + pail + vite
```

### Docker (Production)

```bash
docker compose -f docker-compose.prod.yml up -d
```

### Running the Scraper

```bash
cd scraper
npx nodemon daemon.js
```

## Artisan Commands

| Command              | Purpose                                        |
|----------------------|------------------------------------------------|
| `listen:scrape`      | Process scrape results from Redis output queue |
| `listen:sync-status` | Process product sync status updates            |
| `sync-products`      | Sync product data to external APIs             |
| `sync-zitazi`        | Sync pricing data to Zitazi WooCommerce store  |
| `seed-products`      | Seed or update product variations              |

## Environment Variables

Key environment variables (see `.env.example` for full list):

| Variable         | Description                   |
|------------------|-------------------------------|
| `REDIS_PASSWORD` | Redis authentication password |
| `DB_*`           | MySQL connection settings     |
| `WOCOMMERCE_*`   | WooCommerce API credentials   |
| `SCRAPER_*`      | Scraper service configuration |

## Project Structure

```
zitazi/
├── src/                    # Laravel application
│   ├── app/
│   │   ├── Console/        # Artisan commands
│   │   ├── Models/         # Eloquent models
│   │   └── Filament/       # Admin panel resources
│   ├── config/             # Configuration files
│   ├── database/           # Migrations & seeders
│   ├── routes/             # API & web routes
│   └── tests/              # PHPUnit tests
├── scraper/                # Node.js scraping service
│   ├── daemon.js           # Queue worker entry point
│   ├── scraper.js          # Scraping logic (Puppeteer)
│   └── package.json
├── docker/                 # Docker configuration
├── docker-compose.dev.yml  # Dev services
├── docker-compose.prod.yml # Production services
└── infra/                  # Infrastructure scripts
```

## Testing

```bash
cd src
php artisan test
```

Test fixtures are located in `src/tests/Data/`.

## Deployment

The project uses GitHub Actions CI/CD:

1. Push to `master` branch triggers the pipeline
2. Docker images are built and pushed to GHCR
3. VPS deployment via SSH
4. Post-deploy migrations run automatically

## License

MIT