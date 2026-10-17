# One-million inventory benchmark

Executed October 3, 2026 (Asia/Manila) with PHP 8.3.6 and local SQLite.

Run from the Laravel project root:

```sh
php scripts/benchmark-inventory.php
```

The script snapshots the configured SQLite database into a unique `/tmp` file, switches only its own process to that snapshot, adds exactly 1,000,000 synthetic inventory rows in batches of 500, and calls the existing `InventoryService::getAllItems()` with eight rows per page. It never switches the running application to the snapshot. It rejects other database drivers. Each rerun creates another snapshot and overwrites the JSON report, so remove unused snapshots to reclaim disk space.

The original database still contains 37 inventory rows. The snapshot contains 1,000,037 rows, occupies 160.54 MiB, and remains at the path in `inventory-million-results.json`. Batch insertion took 26.43 seconds; the script's peak PHP allocation was 34 MiB. Insertion bypasses the import, notifications, transaction history, and audit logging, so this is not an Excel/CSV import throughput result.

Median elapsed service time over five sequential runs:

| Operation | Original 37 records | With 1,000,000 added |
| --- | ---: | ---: |
| First inventory page | 1.52 ms | 511.27 ms |
| Page 125,000 | 0.72 ms (empty) | 2,392.89 ms |
| Matching search | 0.50 ms (no matches) | 918.74 ms |
| No-match search | 0.48 ms | 474.25 ms |
| Low-stock filter | 1.50 ms | 392.98 ms |
| Stock descending | 2.22 ms | 494.16 ms |

All measured queries completed. Performance degrades substantially at this volume, particularly deep pagination. Default-list and search query plans show full table scans and temporary sorting. A composite index supporting active-item name ordering, a search strategy suited to substring matching, and cursor pagination are candidates for follow-up measurement. No application optimization was made in this benchmark.

These measurements include pagination counts, Eloquent hydration, and category eager loading. They exclude HTTP middleware, HTML rendering, browser behavior, concurrent traffic, dashboards, reports/exports, import memory behavior, and deployed infrastructure. Baseline deep-page and matching-search results return different row counts from the loaded dataset; they are context, not like-for-like throughput comparisons. No response-time acceptance target was supplied, so completion does not establish a performance pass.

The results JSON contains all five timings for each case and query-plan evidence. The synthetic dataset uses one category, varied stock, and short descriptions; more varied real data may behave differently.
