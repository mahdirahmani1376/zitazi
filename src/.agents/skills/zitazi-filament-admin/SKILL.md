---
name: zitazi-filament-admin
description: "Use this skill whenever working in Zitazi's Filament admin panel: Filament resources, tables, filters, forms, infolists, relation managers, pages, widgets, or the admin panel provider. Covers the project's resource directory structure (Resources/Products, Resources/Variations, Resources/LogModels, ImportBatchResource), the shared table/form schema class pattern (ProductsTable::configure), common table filters (SelectFilter preload, TernaryFilter, date filters, source/brand/category/owner filters), toolbar bulk actions (bulk sync, excel export, promotion toggles, emergency deactivate), and known gotchas like deferred filters, column toggleability, and the Echo broadcast listener on ListProducts. Also use when the user reports Filament UI problems (filters not opening, columns not toggling)."
license: MIT
metadata:
  author: zitazi
---

# Zitazi Filament Admin Panel

## Layout convention

Resources live under `app/Filament/Resources/<Name>/` and are split into sub-folders — not one big file:

```
Resources/Products/
  ProductResource.php
  Pages/{ListProducts,CreateProduct,EditProduct,ViewProduct}.php
  Tables/ProductsTable.php          <- table definition (static configure)
  Schemas/{ProductForm,ProductInfolist}.php
  RelationManagers/{VariationsRelationManager,LogsRelationManager}.php
```

Each table/form/infolist is a **plain class with a `public static function configure(...)`** that mutates the Filament object and returns it. The `Resource` class just delegates:

```php
public static function table(Table $table): Table
{
    return ProductsTable::configure($table);
}
```

Follow this exact pattern for new resources — don't inline the table into the resource.

## Tables

### Common filters in `ProductsTable` / `VariationsTable`

- `SelectFilter::make('source')` with `->preload()` and a `->query()` closure for decathlon/trendyol.
- `SelectFilter::make('category'|'brand'|'owner')` with `->multiple()->preload()` and options built from `Product::query()->distinct()->pluck(...)`.
- `TernaryFilter::make('promotion')`, `TernaryFilter::make('is_deleted')`.
- Date filters (`created_at`, `updated_at`) using `DatePicker` from/to with `whereDate`.

### Gotchas (important)

1. **Deferred filters are default in Filament v4** — users must click **Apply** for filters to take effect. `->deferFilters(false)` makes them live.
2. **Column toggling only works on `->toggleable()` columns.** In the column-manager dropdown, non-toggleable columns render a disabled checkbox and clicking them does nothing. Only `created_at`/`updated_at` are toggleable in most tables — that's expected, not a bug.
3. **`Incorrect use of <label for=FORM_ELEMENT>`** console warning is a benign Chrome notice from the column-manager label wrapping a checkbox whose id is bound later by Alpine. Ignore it.
4. **`ListProducts` has an Echo listener** (`getListeners()`) on `echo:product-sync,.product.sync.status.updated` that writes to a public `$syncStatuses` array — every broadcast triggers a full Livewire re-render. In production with many concurrent syncs this can cause the filter dropdown to fail / `ERR_HTTP2_PROTOCOL_ERROR`. Prefer scoping this to a small widget, or `->skipRender()`, rather than a public array on the page.

### Bulk / toolbar actions

`->toolbarActions([...])` groups bulk actions. Common ones already in place: bulk sync (`Bus::batch` of `SendScrapeMessageJob`), Excel export (`Excel::download` with `FillamentProductExport`), promotion activate/deactivate, and "ناموجود کردن اضطراری" (`EmergencySyncJob`).

## Pages

- `ListProducts` also has `$syncStatuses` public array + `syncStatusUpdated()` listener (see gotcha 4).
- Header actions use `getHeaderActions()` with `CreateAction::make()`.

## Panel provider

`app/Providers/Filament/AdminPanelProvider.php`:
- panel id `admin`, path `/admin`, brand "Price Monitoring Admin Panel"
- amber primary color, login required
- `->databaseNotifications()->databaseNotificationsPolling('10s')`
- middleware includes `PreventRequestForgery` (Laravel 13 rename; was `VerifyCsrfToken`)

## Verification

- `php artisan filament:about` to see installed Filament version/components.
- `php artisan route:list --path=admin` to see panel routes.
- After changing assets: `php artisan filament:upgrade` then `npm run build` (or `composer run dev` for vite).
