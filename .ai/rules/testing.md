---
paths:
  - src/tests/**
---

# Zitazi Testing

- Framework: PHPUnit. Create tests with `php artisan make:test {name}` (no `tests/` prefix in the name).
- Test data lives in `src/tests/Data/` (JSON fixtures).
- `ProductTest::setUp()` uses HTTP fakes — always fake external HTTP (WooCommerce, currency APIs, source sites) in tests; never hit real endpoints.
- Run the narrowest suite for a change: `php artisan test --filter=<TestName>`.
- Do not delete tests without approval.
