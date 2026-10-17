---
name: zitazi-queue-flow
description: "Use this skill whenever working with Zitazi's scraping pipeline: Redis queues, the Node.js Puppeteer scraper, send/listen commands, or any job that pushes to or reads from the scrape queues. Covers the input queues (trendyol_scrape_product, decathlon_scrape_product, torob_scrape_product), the scrape_result output queue, the ScraperQueueService / ListenForScrapeResponseCommand flow, bulk vs single scrape messages, Torob-first routing, and debugging why a product's sync_status is stuck (ENQUEUED/PROCESSING/cooldown). Also use when adding a new crawl source or changing how scraped data flows from Laravel to the scraper daemon."
license: MIT
metadata:
  author: zitazi
---

# Zitazi Queue Flow (Laravel ↔ Node scraper)

## Architecture at a glance

Zitazi is split in two:

- **Laravel app** (`src/`) — API, Filament admin, queue workers.
- **Node.js scraper** (`scraper/`) — Puppeteer daemon (`daemon.js`) that listens on Redis queues, scrapes pages, and pushes results back.

The two halves communicate **only through Redis lists** (pub/sub style, not Laravel queues).

## Queue names

Defined in `src/config/queue.php`:

```php
'TR_QUEUE_IN'   => 'trendyol_scrape_product',
'DE_QUEUE_IN'   => 'decathlon_scrape_product',
'TOROB_QUEUE_IN'=> 'torob_scrape_product',
'OUT_QUEUE'     => 'scrape_result',
```

Gotcha: in Docker Compose, Redis queues get a **prefix** (`laravel_database_`) unless overridden. The scraper must use the same prefixed names (e.g. `laravel_database_torob_scrape_product` in `scraper/daemon.js`).

## Forward flow (Laravel → scraper)

1. `ScraperQueueService` (`app/Services/ScraperQueueService.php`) routes the product:
   - `sendToAllScraperQueues()` → if `belongsToTorob()`, push JSON to `TOROB_QUEUE_IN` first; otherwise `addToForeignQueue()`.
   - `addToForeignQueue()` → Trendyol → `TR_QUEUE_IN`, Decathlon → `DE_QUEUE_IN`; aborts (logs "product does not have source") if neither.
2. `PushToTorobQueueAction::execute()` re-pushes a product to the Torob queue only when no cached Torob min price exists (`getTorobMinPrice()`); otherwise it returns without pushing.
3. It sets `sync_status = ENQUEUED` via `$product->setSyncStatus(SyncStatusEnum::ENQUEUED)` **before** `Redis::lPush`.
4. The Node daemon `scraper/daemon.js` pops the message, scrapes (strategies in `Worker.js` / `*Strategy.js`), and publishes the result to `scrape_result`.

Message payload (Torob example):

```json
{
  "product": {
    "id": 123,
    "full_url": "https://api.torob.com/v4/base-product/details/?prk=..."
  },
  "bulk": false
}
```

For bulk syncs the `bulk: true` flag is set (`app:bulk-scrape`, scheduled daily at 13:00, pushes Torob-first with `bulk: true`).

## Reverse flow (scraper → Laravel)

- The daemon publishes results to `scrape_result`.
- `php artisan listen:scrape` (`ListenForScrapeResponseCommand`) consumes that queue and processes results — production runs it as the `scrape-result` container.

On `source === 'Torob'`:
1. `TorobServiceFacade::processResponse()` parses the lowest other seller → writes `rival_min_price` (or `null` when Zitazi's own shop `services.zitazi.torob_shop_id` is cheapest / no valid sellers), and sets `SUCCESSFUL_DATA_FETCH_TOROB` / `FAILED_DATA_FETCH_TOROB`.
2. If the product also belongs to Trendyol/Decathlon → `ScraperQueueService::addToForeignQueue($product, $bulk)` pushes it to the foreign queue (step 4 of the flow: fetch the source price, then price against the Torob rival).
3. Otherwise (pure-Torob product) → `TorobServiceFacade::updateProductAndSync()`.

## Debugging a stuck sync_status

`SyncStatusEnum` (`app/Enums/SyncStatusEnum.php`) values: `enqueued`, `processing`, `successful_data_fetch`, `successful_data_fetch_torob`, `failed_data_fetch`, `failed_data_fetch_torob`, `product_is_deleted`, `successful_update`, `skipped_update`, `cooldown`, `no_response_retrying`, `invalid_currency`, `invalid_woocommerce_id`, etc.

Common stuck states and where to look:

- **ENQUEUED forever** → message never picked up. Check the Redis list length (`LRANGE torob_scrape_product 0 -1`), the scraper daemon logs, and that queue prefixes match.
- **PROCESSING forever** → scraper crashed mid-scrape, or response never published. Restart `scraper/daemon.js`; check `no_response_retrying` retry logic.
- **COOLDOWN** → scraper hit bot detection (20-min cooldown in `scraper/daemon.js`). Don't re-push aggressively.
- **FAILED_DATA_FETCH_TOROB** → Torob API/HTTP failure; check the `error fetching torob data` log.

## Adding a new source

1. Add the source column/constant on `Product` (e.g. `SOURCE_XXX`).
2. Add a `belongsToXXX()` helper on `Product`.
3. Add a queue name in `config/queue.php`.
4. Branch in `ScraperQueueService` to push the right payload.
5. Handle the response in `ListenForScrapeResponseCommand` (or a new listener).
6. Add the source label in `Product::getAllSourceLabels()` for Filament filters.

## Verification

- `php artisan route:list` and `config:show queue` to confirm names.
- Inspect Redis directly: `docker exec zitazi-redis redis-cli LRANGE <queue> 0 -1`.
- Tests: `src/tests/Feature/ProductTest.php` uses HTTP fakes; keep queue pushes behind fakes where possible.
