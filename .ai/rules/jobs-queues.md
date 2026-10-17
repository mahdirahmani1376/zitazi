---
paths:
  - src/app/Jobs/**
  - src/app/Console/Commands/**
---

# Zitazi Jobs & Queues

## Scraper queue flow

- Laravel talks to the Node scraper **only** through Redis lists defined in `config/queue.php`: `TR_QUEUE_IN` (`trendyol_scrape_product`), `DE_QUEUE_IN` (`decathlon_scrape_product`), and `TOROB_QUEUE_IN` (`torob_scrape_product`). The scraper replies on `scrape_result`, consumed by `listen:scrape`.
- `ScraperQueueService` routes products: `sendToAllScraperQueues()` pushes Torob products to `TOROB_QUEUE_IN` first, otherwise the foreign queue; `addToForeignQueue()` handles Trendyol/Decathlon. Set `ENQUEUED` **before** `Redis::lPush` (via `$product->setSyncStatus(SyncStatusEnum::ENQUEUED)`), then push.
- `PushToTorobQueueAction::execute()` re-pushes a product to the Torob queue only when no cached Torob min price exists (`getTorobMinPrice()`).
- Never assume queue names match Redis keys in Docker: Compose prefixes queues (`laravel_database_`).

## Scrape response handling (`ListenForScrapeResponseCommand`)

- Branches on `source === 'Torob'`: `TorobServiceFacade::processResponse()` sets `rival_min_price` from the lowest other seller; then Torob+foreign products are pushed to the foreign queue via `addToForeignQueue()`, while pure-Torob products go to `TorobServiceFacade::updateProductAndSync()`.
- Trendyol/Decathlon success responses flow to `SeedVariationsForDecathlonAction` / `SeedVariationsForTrendyolAction`.

## Sync jobs

- `SyncZitaziJob::handle(BaseVariationCrawler $baseVariationCrawler)` — one variation → destination store; if the crawler returns `3`, `release($this->attempts() * 60)`; `tries()` is `2`.
- `SyncWoocommerceProductJob($productId, $recommendedPrice)` — product-level price push (used by Torob pure-product sync). Uses `ZitaziUpdateDTO::getPriceUpdatePayload()` and `$product->base_source`.
- Bulk operations use `Bus::batch([...])` with `->name()` (see `ProductsTable` toolbar bulk sync). `app:bulk-scrape` is scheduled daily at 13:00 and pushes Torob-first to `TOROB_QUEUE_IN`.
- Laravel 13: `DB::table(...)->upsert($rows, $uniqueBy, [...])` requires a non-empty `$uniqueBy` or it throws.

## Known trap

- `AppServiceProvider` registers crawlers with `new EleleCrawler()` / `new SazKalaCrawler()` but their `BaseVariationCrawler` base requires an `HttpService` constructor arg — that path throws `ArgumentCountError`. Resolve via `app(HttpService::class)` / container instead of bare `new` when touching crawler wiring.
