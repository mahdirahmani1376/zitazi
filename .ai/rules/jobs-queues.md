---
paths:
  - src/app/Jobs/**
  - src/app/Console/Commands/**
---

# Zitazi Jobs & Queues

## Scraper queue flow

- Laravel talks to the Node scraper **only** through Redis lists defined in `config/queue.php`: `TR_QUEUE_IN` (`trendyol_scrape_product`) and `DE_QUEUE_IN` (`decathlon_scrape_product`). The scraper replies on `scrape_result`, consumed by `listen:scrape`.
- `SendScrapeMessageJob` decides the queue from the product source, sets `ENQUEUED`, then `Redis::lPush`. Keep that order (status before push).
- Never assume queue names match Redis keys in Docker: Compose prefixes queues (`laravel_database_`).

## Sync jobs

- `SyncZitaziJob::handle(BaseVariationCrawler $baseVariationCrawler)` — if the crawler returns `3`, `release($this->attempts() * 60)`; `tries()` is `2`.
- Bulk operations use `Bus::batch([...])` with `->name()` (see `ProductsTable` toolbar bulk sync).
- Laravel 13: `DB::table(...)->upsert($rows, $uniqueBy, [...])` requires a non-empty `$uniqueBy` or it throws.

## Known trap

- `AppServiceProvider` registers crawlers with `new EleleCrawler()` / `new SazKalaCrawler()` but their `BaseVariationCrawler` base requires an `HttpService` constructor arg — that path throws `ArgumentCountError`. Resolve via `app(HttpService::class)` / container instead of bare `new` when touching crawler wiring.
