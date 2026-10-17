---
name: zitazi-woocommerce-sync
description: "Use this skill whenever working with Zitazi's product syncing to external systems: WooCommerce/Zitazi/Satre store updates, base_source (zitazi vs satre), the sync lifecycle on Product.sync_status, SyncZitaziJob, SyncWoocommerceProductJob, BatchSyncZitaziJob, EmergencySyncJob, or any job/DTO that pushes price/stock to the destination store. Covers the ZitaziUpdateDTO payload (stock_quantity, price, regular_price), the per-store sync toggle (sync_zitazi_enabled / sync_satreh_enabled), the Torob rival-min-price pricing rules (20% profit floor, 99.5% recommended price), retry/release logic in sync jobs, emergency deactivation, bulk sync via Bus::batch, and debugging failed_update / skipped_update states."
license: MIT
metadata:
  author: zitazi
---

# Zitazi → WooCommerce / Satre Sync

## Core models

- `Product` — has `base_source` (`satre` | `zitazi`), `sync_status` (`SyncStatusEnum` cast), source columns (`trendyol_source`, `decathlon_url`, `amazon_source`, `digikala_source`, `sazkala_source`, `elele_source`, `eth_source`, `matilda_source`, `torob_source`, `torob_id`), pricing (`min_price`, `rival_min_price`, `markup`), and `promotion` bool.
- `Variation` — belongs to `Product` (`product_id`), has `price`, `rial_price`, `stock`, `own_id` (Zitazi variation id), `url`, `size`, `color`, `item_type`, `status`, `is_deleted`, `base_source`.

## base_source semantics

- `Product::ZITAZI` ('zitazi') — synced to Zitazi's WooCommerce store.
- `Product::SATRE` ('satre') — synced to the Satre store (`SyncSatrehCommand`, `ResyncSatreJob`, `SatreVariationExport`).
- `Product::SATRE`/`Product::ZITAZI` constants live on the `Product` model; filters in Filament use these.
- **Per-store toggle**: every "sync enabled" gate uses cache key `sync_zitazi_enabled` or `sync_satreh_enabled` (fallback `services.zitazi_sync_enabled` / `services.satreh_sync_enabled`), selected by `base_source`. `BaseVariationCrawler::syncZitazi()` and `TorobService::updateProductAndSync()` both follow this pattern. Never check a single global `sync_enabled` — that key no longer exists.

## The sync lifecycle (`sync_status`)

`SyncStatusEnum` tracks each product through:
`enqueued → processing → successful_data_fetch / successful_data_fetch_torob / failed_data_fetch / failed_data_fetch_torob → successful_update / failed_update / skipped_update → cooldown / no_response_retrying / product_is_deleted / invalid_currency / invalid_woocommerce_id / variation on promotion / variation_is_deleted / no-route-found-for-variation`

Set status via `$product->setSyncStatus(SyncStatusEnum::X, broadcast: true)` — the broadcast dispatches `ProductSyncStatusChangedEvent` on the `product-sync` channel (used by Filament's `ListProducts` listener).

## Torob pricing rules

- `rival_min_price` is set by `TorobService::processResponse()` to the lowest other seller's price on Torob; `null` when Zitazi's own shop (`services.zitazi.torob_shop_id`) is cheapest or there are no valid sellers.
- **20% profit floor**: the destination price must never go below `Currency::convertToRial($sourcePrice, 1.20)`. Decathlon products also store `min_price = rial_price * 1.2`.
- **99.5% recommended price**: `$rivalMinPrice * (99.5 / 100)`, floored to the nearest 1000, and clamped so it never goes below the 20% floor. Implemented in:
  - `TorobService::updateProductAndSync()` (pure-Torob products → `SyncWoocommerceProductJob`)
  - `SeedVariationsForDecathlonAction` / `SeedVariationsForTrendyolAction` (foreign products, after `ScraperQueueService::addToForeignQueue` re-push)
  - `TorobCrawler::getZitaziTorobPriceRecommend()` (legacy direct-crawl path)
  - Keep all copies in sync when changing the rule.

## Sync jobs

- `SyncZitaziJob` — one variation → destination. Injected `BaseVariationCrawler` (see note below). If `syncZitazi()` returns `3`, it `release($this->attempts() * 60)` and `tries() = 2`.
- `SyncWoocommerceProductJob($productId, $recommendedPrice)` — product-level price push via `ZitaziUpdateDTO::getPriceUpdatePayload()`, using `$product->base_source` for the WooCommerce client. Dispatched from `TorobService::updateProductAndSync()`.
- `SyncProductJob` / `SyncProductsAction` / `CrawlerManager` — legacy direct-crawl path (per-product crawl for non-queue flows).
- `BatchSyncZitaziJob` / `SyncChunkProductsWithZitaziJob` — chunked/batch upserts to `products` table (`DB::table('products')->upsert($results, ['own_id'], ['rial_price','stock','updated_at'])` — uniqueBy is required non-empty on Laravel 13).
- `EmergencySyncJob` — bulk "make unavailable" (ناموجود کردن اضطراری). Dispatched from Filament bulk actions with `source: null, invalid: true, ids: [...]`.
- `ScraperQueueService` — pushes scrape requests to the Node scraper (see `zitazi-queue-flow` skill).

## ZitaziUpdateDTO

`app/DTO/ZitaziUpdateDTO.php` — created via `ZitaziUpdateDTO::createFromArray([...])`. Typical keys:
- `stock_quantity` — set to `0` when out of stock / deleted.
- `price` / `rial_price` — price to push to the store.
- `getPriceUpdatePayload(int $price)` — product-level price-only payload (`sale_price=null`, `regular_price`, `price`), used by `SyncWoocommerceProductJob`.

## Gotchas

1. **Injected `BaseVariationCrawler` in sync jobs**: `SyncZitaziJob::handle(BaseVariationCrawler $baseVariationCrawler)` relies on container resolution. Subclasses like `EleleCrawler`/`SazKalaCrawler` require an `HttpService` constructor arg, but `AppServiceProvider` registers crawlers with `new XxxCrawler()` (no args) — that path throws `ArgumentCountError`. Prefer resolving via `app(HttpService::class)` / the container instead of direct `new`.
2. **rial_price is the price in Rials**; `price` is likely the raw source currency. Always confirm which field the destination store expects before mapping.
3. **Promotion**: a variation on promotion is skipped (`VARIATION_HAS_PROMOTION_ACTIVE`). Bulk "activate/deactivate promotion" actions just flip the `promotion` bool on the Product.
4. **Deletes**: `variation-delete` route sets stock to 0 and `is_deleted = true`.
5. **Laravel 13 upsert**: `uniqueBy` must be non-empty or Laravel throws `InvalidArgumentException`.
6. **Per-store toggle**: don't reintroduce a single `sync_enabled` flag; always branch on `base_source` (see above).

## Verification

- Check a product's lifecycle: `LogModel` entries (`app/Models/LogModel.php`) record per-product messages.
- `php artisan tinker --execute 'App\\Models\\Product::where("own_id", ...)->first()->sync_status;'`
- Tests: `tests/Feature/ProductTest.php` — use HTTP fakes (see its `setUp()`); add fake-based tests rather than hitting real WooCommerce APIs.
