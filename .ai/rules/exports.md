---
paths:
  - src/app/Exports/**
---

# Zitazi Excel Exports

- Exports implement the `Maatwebsite\Excel\Concerns` interfaces: `FromQuery` (preferred for large data) or `FromCollection`, plus `WithHeadings` and `WithMapping`.
- Download via `Excel::download(new SomeExport($records), "name_{$now}.xlsx")` from routes or Filament bulk actions.
- The `Fillament*` exports (`FillamentProductExport`, `FillamentVariationExport`) accept records passed from the Filament bulk-action UI.
