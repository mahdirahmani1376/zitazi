---
paths:
  - src/app/Models/**
---

# Zitazi Models

## Product model conventions

- Source constants live on `App\Models\Product` (`SOURCE_TRENDYOL`, `SOURCE_DECATHLON`, `SOURCE_ETH`, `SOURCE_AMAZON`, `SOURCE_SAZ_KALA`, `SOURCE_Elele`). Add a new source as a constant + a `belongsToXxx()` helper + a column before wiring anything else.
- `base_source` uses `Product::SATRE` ('satre') or `Product::ZITAZI` ('zitazi') — never raw strings elsewhere.
- `sync_status` is cast to `SyncStatusEnum`; always set it via `$product->setSyncStatus(SyncStatusEnum::X, broadcast: true)` so the `product-sync` channel event fires for the Filament UI.
- Pricing helpers: `getRatio()` returns `1 + markup/100` (default 1.6). Keep money as the field's native unit; `rial_price` is Rials on variations.
- **Torob pricing fields**: `rival_min_price` = the lowest competing seller's price on Torob (`null` when Zitazi's own shop is cheapest — matched via `services.zitazi.torob_shop_id` — or when there are no valid sellers). `min_price` = the minimum allowed price (the 20% profit floor). `getTorobMinPrice()` reads the cache key `getTorobCacheKey()` (`torob:mp:{id}`).
- **Min-profit floor (20%)**: a destination price must never go below `Currency::convertToRial($sourcePrice, 1.20)` (see `SeedVariationsForDecathlonAction`/`SeedVariationsForTrendyolAction`); Decathlon also stores `min_price = rial_price * 1.2`.
- **Recommended price (99.5% rule)**: `$rivalMinPrice * (99.5 / 100)`, rounded down to the nearest 1000, and clamped to never go below the 20% floor. Same formula lives in `TorobService::updateProductAndSync()`, `TorobCrawler::getZitaziTorobPriceRecommend()`, and the seed actions — keep them in sync when changing the rule.
- When adding a model relationship, prefer the explicit relationship methods already in `Product.php` over anonymous `where` closures.

## Variation model conventions

- `Variation` belongs to `Product` via `product_id`; `own_id` is the Zitazi store variation id.
- `stock`, `price`, `rial_price`, `is_deleted`, `status` drive sync behavior. `is_deleted` / stock 0 means "make unavailable" on the destination store.
- Use `Variation::TableFilters()` / `Variation::baseSources()` when building Filament filter options rather than inline arrays.
