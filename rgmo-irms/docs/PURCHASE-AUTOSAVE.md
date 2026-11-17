# Automatically saved purchase entries

Unfinished **New PO** entries are saved to the database separately from purchase orders. This includes incomplete headers and item rows. An unfinished entry does not reserve stock, add inventory, enter approval, or appear in procurement totals.

## Continue preparing a purchase

1. Open **Inventory → Purchases → New PO** and enter the purchase details. Changes are saved after a short typing pause, with a periodic save during continuous typing.
2. Read the status above the form. **Changes waiting to save** and **Saving** mean the latest changes have not yet been acknowledged. **Entries saved** identifies an acknowledged database save. A visible error means the latest changes remain in the current tab.
3. **Close** keeps the unfinished entry. After reloading or navigating back, use **Unfinished entries → Continue** to restore the headers, item rows and saved receipt.
4. Choose **Save draft** when the purchase is ready to become an actual PO. Normal supplier, active funding source, item, date, quantity and price validation still applies. The system finishes the unfinished entry and creates one PO with Draft status. Submit that PO for approval through the existing workflow.
5. Use **Discard** in the unfinished entries list to remove unwanted work and its private draft receipt. This does not cancel an existing PO.

Only users who can prepare purchases can use this feature. Unfinished entries belong to their creator; another user, including another Administrator, cannot read, change, discard or download their attachments.

## Receipts and incomplete fields

PDF, JPG and PNG receipts up to 5 MB are saved privately. Browsers cannot refill a file-picker after a reload, so the form displays a saved receipt link instead. That stored attachment is reused when the PO is created; it does not need to be selected again. A replacement or explicit removal is also saved.

Invalid receipts produce a field warning while other entries can still be saved. Correct or remove an invalid receipt before creating the PO. If private file storage has lost an attachment, the saved link is marked unavailable and it must be attached again.

Unfinished quantities and prices are retained as entered, including intermediate text such as `1.`. Full number validation applies at explicit PO saving. Empty extra rows may remain while preparing the form; a row containing entries must be completed or removed before saving the PO.

If a saved supplier, funding source or item is no longer selectable, the form identifies the unavailable selection rather than silently choosing another. Select a valid replacement before creating the PO.

## Multiple tabs and connection problems

Each saved entry has a revision. Writes are serialized in the browser and checked on the server. If another tab saves or finishes the same entry, the stale tab does not overwrite it. Its current fields remain visible. Choose **Load saved version** to replace them, or **Keep entries as a new draft** to preserve them separately. A new copy needs its own receipt attachment if the browser no longer holds the original file.

Normal in-app navigation and logout wait for pending saves. Reload/closing uses a best-effort save and a browser warning when changes are pending. Large files cannot use the browser's background keepalive allowance. Wait for **Entries saved** before closing the tab when the latest changes matter; a lost connection or abrupt browser/device shutdown cannot guarantee saving unacknowledged edits. No local-storage fallback copies private drafts onto the device.

## Database and completion lifecycle

The implementation uses a regular persistent `procurement_drafts` staging table. A SQL `TEMPORARY` table is tied to a connection/session and is unsuitable for restoring work reliably across independent web requests.

The table stores a UUID, owner, bounded JSON form data, revision, status, private receipt metadata and timestamps. Active entries remain available until explicitly saved or discarded. Completed and discarded identifiers retain small closed records so delayed requests cannot recreate them. Completed form data is cleared, and the resulting PO owns its retained receipt. Retried final submissions return the same created PO rather than creating another.

Apply `2026_11_17_010000_create_procurement_drafts_table.php` through the normal deployment process:

```bash
php artisan migrate
npm run build
```

The database stores the entry and receipt metadata; the file itself uses the application's private storage. That storage must remain available across deployments if draft receipts are to survive. No live deployment or configured application database is changed by the automated tests.

## Verification

Backend coverage is in `ProcurementDraftTest`, `ProcurementDraftConversionTest` and `ProcurementDraftInterfaceTest`. The browser queue has deterministic Node tests:

```bash
php vendor/bin/phpunit tests/Feature/ProcurementDraftTest.php tests/Feature/ProcurementDraftConversionTest.php tests/Feature/ProcurementDraftInterfaceTest.php --no-coverage --do-not-cache-result
node --test scripts/tests/purchase-draft-autosaver.test.mjs
```

`scripts/tests/purchase-draft-browser-smoke.mjs` can run against an isolated Laravel-rendered purchase-page HTML fixture. It uses real headless Chrome with a local API simulator to check editing, receipt saving, reload/recovery, conflicting tabs and final submission. It complements the real PHP database/storage tests; it is not a deployed-service test.
