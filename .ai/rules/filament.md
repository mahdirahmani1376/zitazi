---
paths:
  - src/app/Filament/**
  - src/app/Providers/Filament/**
---

# Zitazi Filament Admin

## Structure

- Resources use the sub-folder layout: `app/Filament/Resources/<Name>/{Pages,Tables,Schemas,RelationManagers}`.
- Table/form/infolist definitions are **plain classes with `public static function configure(...)`**; the Resource delegates. Do not inline tables into the resource class.

## Tables & filters

- Table filters: source filters via `SelectFilter::make('source')->preload()` with a `->query()` closure; category/brand/owner via `->multiple()->preload()` with `Product::query()->distinct()->pluck(...)`.
- **Filament v4 filters are deferred by default** — users must click Apply. Don't "fix" this unless the UX requires live filtering (`->deferFilters(false)`).
- **Only `->toggleable()` columns can be toggled** in the column manager; others render disabled checkboxes. Not a bug.
- The `Incorrect use of <label for=FORM_ELEMENT>` console warning is benign (column-manager label/checkbox + Alpine) — ignore it.
- Bulk actions live in `->toolbarActions([...])` as `BulkAction::make(...)`.

## Broadcasting

- `ListProducts` (and `ViewProduct`, `ProductRelationManager`) listen on `echo:product-sync,.product.sync.status.updated` and write to a public `$syncStatuses` array. Every broadcast re-renders the whole page — in production with many concurrent syncs this breaks the filter dropdown. Prefer a small widget or `->skipRender()` over a public array if touching this.

## Sync toggle widgets

- The old single `SyncWidget` was split into `SyncZitaziWidget` + `SyncSatrehWidget` (each `columnSpan = 1`, on the dashboard). They toggle per-store cache keys `sync_zitazi_enabled` / `sync_satreh_enabled`, falling back to `services.zitazi_sync_enabled` / `services.satreh_sync_enabled`. Use these same keys anywhere a per-store "sync enabled" gate is needed.
