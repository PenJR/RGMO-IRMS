# Controlled stock adjustments

Only an active Administrator can correct an existing stock balance. The `adjustStock` inventory policy checks the administrator role directly; granting another role inventory-management permission does not grant adjustment access.

## Record a correction

1. Open **Inventory**, view the item, and find **Administrator Stock Adjustment**.
2. Choose Increase stock or Decrease stock and enter the number of units to add or remove.
3. Enter the reason and the incident report reference, for example `IR-2026-001`.
4. Confirm the adjustment. The application records the item, direction, quantity, reason, incident reference, authenticated administrator, time, adjustment reference, and balances before and after the change.

The stock change, delivery-lot allocation, transaction, and audit entry are committed together. A decrease cannot consume stock reserved for approved requests or make the balance negative. An increase cannot exceed the stock integer limit. Repeating the same submission does not change stock twice; reusing its key for different details is rejected.

## History and ordinary movements

Item history identifies adjustments explicitly and shows their incident references and before/after balances. History is paginated so older entries remain available. Saved adjustments cannot be edited or deleted through the application model; use another documented adjustment to correct a mistake. An item with adjustment history can be archived, but cannot be permanently deleted through the model. The administrator's name is also saved in the transaction metadata to preserve attribution.

Item editing no longer changes stock balances. A spreadsheet row for an existing item is skipped if it changes stock; missing stock columns preserve its balance. New item opening balances, PO delivery receiving, and approved-request withdrawals remain their own recorded movements. Adjustments count in stock movement reports but are excluded from consumption forecasts.

Historical edit adjustments are identified during migration. Their existing details are retained; missing incident references and before/after balances are not invented.

## Existing integrations

The existing manual web stock-in/out, API increase/decrease, and operations stock-in/out endpoints use the same adjustment service. They require administrator access, `quantity`, `reason`, `incident_report_reference`, and `idempotency_key`. Legacy `source` or `destination` can supply the reason. An `Idempotency-Key` header can supply the submission key. The dedicated form also sends `adjustment_type` (`stock_in` or `stock_out`). Caller-supplied actor IDs, balances, references, or metadata cannot replace the values derived by the service.

The old raw transaction-log insertion endpoint returns a validation error. Actual stock movements create their own history; inserting a second disconnected history entry is no longer supported.

## Migration and checks

Apply `2026_11_07_010000_add_controlled_stock_adjustments.php` with `php artisan migrate`. It adds nullable correction details and an adjustment marker to the transaction table while retaining existing transactions and stock quantities.

The isolated SQLite tests in `tests/Feature/ControlledStockAdjustmentTest.php` cover permissions, required details, aliases, stock protection, replay, edit/import guards, and visible history. Follow [TESTING.md](TESTING.md) for test-database isolation and separate hosted verification.
