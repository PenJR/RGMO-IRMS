# Purchase autosave — implementation verification

Date: November 17, 2026. Base checkout: `2cb9704`. Scope: preserve unfinished New PO entries across reload/navigation using the application's Laravel, policy, service, Bootstrap/Blade and Vite patterns.

## Design and behavior

`procurement_drafts` is a regular persistent staging table, not a connection-scoped SQL temporary table. It contains a UUID, owner, JSON form state, revision, lifecycle status, private receipt metadata, resulting PO reference and timestamps. Owner/status/time columns are indexed. Existing purchases and quantities are not migrated or rewritten.

- Saves accept bounded incomplete header/item values without applying final procurement requirements. Unselected rows and partial numeric text remain recoverable.
- Only the creator with purchase-management access can read/write/discard the entry or download its receipt; another Administrator cannot access it.
- The browser debounces edits, periodically flushes continuous typing, serializes writes, retries failures without clearing fields, and reports waiting/saving/saved/error/conflict states. It does not store private draft data in local storage.
- The purchases page lists private unfinished entries with Continue/Discard actions and pagination. Restore retains saved receipts and identifies unavailable master-data selections. Failed final validation retains original field values and IDs rather than silently dropping a retired selection.
- The browser waits for an acknowledged save before explicit final submission and normal navigation/logout. Closing/reloading uses a best-effort background save and an unsaved-changes warning. Unacknowledged edits and large in-progress uploads are not guaranteed after abrupt shutdown or loss of connectivity.
- Optimistic revisions and database row locks reject stale writes. A conflicted tab can load saved work or keep its own fields as a new entry; a cached attachment is not silently shared with a new copy.
- Explicit Save draft locks the checkpoint through business validation and PO creation, creates an ordinary Draft PO once, transfers the private receipt, and closes the checkpoint in the same database transaction. Duplicate final submissions return the same PO. Autosaving itself creates no PO, reservation, delivery, stock movement or approval.
- Completed/discarded identifiers remain closed so late requests cannot recreate them. Completed raw form data is cleared; the PO owns its receipt. Receipt replacement/removal cleanup runs after commit. Missing attachments require explicit replacement/removal before final saving.

See [the user/deployment guide](../PURCHASE-AUTOSAVE.md).

## Checks performed

Runtime: PHP 8.3.36 / PHPUnit 11.5.56, Node 22.23.4, Vite 7.3.6 and installed Chrome. PHP tests use guarded in-memory SQLite, array sessions/cache/mail and synchronous queues. No configured application database, production records or deployed host was changed.

| Check | Result |
|---|---|
| Three autosave PHP test files | **22 passed; 254 assertions** |
| Focused autosave/funding/PO lifecycle run | **42 passed; 474 assertions** |
| Browser queue tests | **15 passed** |
| Real headless Chrome flow | **Passed** |
| Production build | **Passed**, 1,953 modules; native config loader used because sandbox parent-directory access prevented the default config bundler |
| New PHP files and tests, Pint | **Passed** |
| PHP syntax and whitespace | Changed/new PHP syntax checks and `git diff --check` passed |
| Final full PHP suite | **311 tests; 2,727 assertions; 298 passed; 8 failures; 5 errors** |

Backend coverage: [draft API/storage](../../tests/Feature/ProcurementDraftTest.php), [conversion/idempotency](../../tests/Feature/ProcurementDraftConversionTest.php), and [private recovery/validation UI](../../tests/Feature/ProcurementDraftInterfaceTest.php). These cover partial state, bounded payloads, permissions, stale requests, terminal markers, receipt validation/replacement/removal, validation failure recovery, missing files, draft-only creation and replay behavior.

[Queue tests](../../scripts/tests/purchase-draft-autosaver.test.mjs) use deterministic timers and deferred network responses to verify debounce, serialized revisions, edits during requests, acknowledgment integrity, retries, conflict latching, stopped callbacks and receipt deduplication.

The [Chrome check](../../scripts/tests/purchase-draft-browser-smoke.mjs) used an actual Laravel-rendered purchase-page fixture and the actual production JS/CSS bundle, against a local API simulator. It verified partial quantity `1.`, empty extra rows, receipt upload and display after reload, navigation before debounce expiry, recovery, two-tab conflicts, keeping local work as a new entry, discard and explicit PO submission. The simulator is not a live application database; real authorization/storage/transactions were checked separately in PHP.

Representative commands:

```bash
php vendor/bin/phpunit tests/Feature/ProcurementDraftTest.php tests/Feature/ProcurementDraftConversionTest.php tests/Feature/ProcurementDraftInterfaceTest.php --no-coverage --do-not-cache-result
node --test scripts/tests/purchase-draft-autosaver.test.mjs
node node_modules/vite/bin/vite.js build --configLoader native
node scripts/tests/purchase-draft-browser-smoke.mjs /path/to/isolated-rendered-purchase-page.html
php vendor/bin/phpunit --no-coverage --do-not-cache-result
```

## Existing full-suite failures and limits

All 13 remaining nonpassing tests match the preceding funding-source implementation run:

- Two controlled-stock alias tests still construct numeric inventory routes after public-ID routing changes.
- One inventory export test expects the removed SKU value.
- Two request-reservation assertions and five errors use old final-approval behavior without required verification/endorsement stages.
- One existing FIFO display-order assertion remains unresolved.
- One role-catalog test still identifies the undefined `view-incidents` permission.
- One logo test expects different exact alt text.

These were not changed as part of autosave. Passing local feature tests does not establish PostgreSQL-specific concurrency, live deployment, external access or durable hosting storage. Apply the included migration and rebuild assets through the normal release process; private receipt storage must remain available across deployments.
