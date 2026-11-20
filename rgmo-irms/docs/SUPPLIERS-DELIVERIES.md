# Suppliers and deliveries

## Record a delivery

1. Register the supplier under **Suppliers**, including its company name, unique supplier code, contact details, address, and TIN where applicable. Inactive suppliers remain in historical records but cannot be selected for a new purchase order.
2. Under **Procurement & Deliveries**, create a purchase order using an active registered supplier and funding source. Administrators maintain funding choices under **System → Funding sources**; see [Funding sources](FUNDING-SOURCES.md) for deactivation and historical-record rules. Add each inventory item, ordered quantity, unit, and price. Submit the order for approval.
3. Open an approved or partially received order. Under **Receive a delivery**, enter the date, delivery receipt number, invoice number if applicable, and any additional reference. The signed-in inventory manager is recorded as the receiver.
4. Enter the quantity received against each PO item. Enter zero for items that have not arrived. Confirm the actual unit prices; they default to the individual PO line prices.
5. Save. Each positive quantity creates its own delivery lot, stock-in transaction, and audit entry. The receipt's lines share a receipt key. A partial delivery leaves the order open until every line is fully received.

Receipts cannot exceed an item's undelivered balance or add stock to an archived inventory item. If any line fails, the entire receipt is rolled back. Repeating the same submission does not add stock again, including when the original submission completed the order. Existing single-item integrations are retained; multi-item orders must specify quantities by PO line ID.

Unfinished **New PO** entries save automatically and can be recovered from **Unfinished entries → Continue**. They remain private preparation work until **Save draft** creates a PO; stock and approvals are unaffected by autosaving. See [Purchase autosave](PURCHASE-AUTOSAVE.md) for recovery, receipts, conflicts and discarding entries.

## Correct a recorded delivery

Only an active, unlocked Administrator with the dedicated `correct-deliveries` and `view-delivery-corrections` grants can correct or void a delivery. Ordinary inventory-management or PO-approval access does not grant correction authority. RGMO Heads and Auditors can read correction history; Storekeepers, Unit Heads, Project Managers, and Staff cannot use the correction workflow. Both permission grants are checked explicitly, including for Administrators.

1. Open the purchase order or inventory delivery history and choose **Correct delivery** beside the affected delivery. Each row identifies a received item/batch. A correction applies to that row; correct every affected row when a shared document reference needs changing.
2. Choose **Correct receipt details or quantity**, then enter the correct total received, actual unit cost, date, receipt number, invoice, and reference. Enter the corrected total quantity, rather than the number to add or remove.
3. Enter the reason and an incident report or supporting document reference. Review the current receipt and confirm **Record correction**.
4. To reverse an unused delivery entirely, choose **Void this unused delivery and reverse its stock**. Its received quantity becomes zero, its remaining stock is reversed, and the PO's undelivered balance reopens. Receive any replacement through the approved PO using a new receipt submission. Voided rows remain visible and cannot be reopened.
5. Review **Reports → Delivery Corrections** or the delivery's **Correction history**. History preserves the receipt before the first correction, each before/after version, administrator name, reason, reference, time, stock delta, and related PO status.

After stock has been released, document and price corrections remain available. A quantity correction must retain all units already released or otherwise consumed from that delivery. A correction cannot move its received date after an existing linked release, change item/batch/supplier identity, exceed the approved PO quantity, remove reserved usable stock, or make item/batch stock negative. The original stock-in, release allocations, saved release costs, and usage records remain preserved. Corrected prices affect delivery totals and supplier price comparisons; they do not change the approved PO price, item valuation policy, or previously recorded release costs. Archived items allow document and price corrections only; restore an item before changing its quantity.

Corrections update the delivery, its remaining lot balance, batch stock, item stock, PO received count/status, inventory movement, and audit record together. Repeating an accepted submission returns the same correction. A stale form or reuse of its submission key for different details is rejected; reopen the form and review current values before trying again. Direct edits and deletion of correction history are unavailable.

Quantity corrections create adjustment movements dated when the correction is recorded. Monthly and biological reports net these against receipts/additions and identify the correction in remarks, including negative net receipts for a prior-period reversal. They do not inflate consumption forecasts. Previously saved report snapshots keep their original results.

## View history and compare prices

- PO details show every delivered item, quantity, remaining lot balance, receiver, receipt/invoice numbers, and actual unit price.
- Supplier profiles show paginated purchase and delivery history, including every item on multi-item orders.
- Inventory item details include all linked PO lines and delivery history, including items that are not the first item on an order.
- **Compare supplier prices** is available from Procurement & Deliveries and the item/supplier history screens for users with procurement access. Choose an item and optional delivery date range. Each supplier/unit combination shows the lowest, highest, and quantity-weighted average unit price. Delivery details below the comparison show the supporting receipts.
- Received value on the procurement overview uses delivery prices. Outstanding value continues to use the prices of the unreceived PO quantities.

## Database update

Run `php artisan migrate` in the configured application environment to apply `2026_11_07_000000_complete_delivery_records.php`. This adds the PO line link, receipt grouping key, delivery receipt number, invoice number, unit price, and unit of measurement to delivery records.

The correction workflow also requires `2026_11_20_010000_add_controlled_delivery_corrections.php`. It adds receipt revision/original/void information and the permanent correction ledger. Existing delivery quantities, prices, receipt documents, stock balances, and transactions are retained; no historical corrections are invented.

Existing quantities, references, stock balances, and transaction history are retained. Historical prices and units are copied from the matching PO line, falling back to the historical single-item PO price and item unit. Old generic references are preserved as generic references because their original document type is unknown. Existing supplier records are retained.

## Verification

`tests/Feature/SupplierDeliveryTest.php` exercises partial and complete multi-item receiving, retry protection, rollback, archived/foreign items, prices, dates, permissions, history screens, and migration preservation. Use the isolated SQLite test configuration described in [TESTING.md](TESTING.md).

`DeliveryCorrectionTest` and `DeliveryCorrectionIntegrationTest` cover all seven roles, revoked/injected permissions, inactive/locked accounts, correction/void/retry/stale forms, post-release costs and documents, reservations, batch balances, rollback, history immutability, migration preservation, price comparisons, and reports.

For manual acceptance, receive only the second item on a two-item PO, receive the remaining quantities on another receipt, and verify each inventory profile and the supplier history. Compare that item's prices across two suppliers and a restricted date range. The tests do not establish PostgreSQL-specific behavior or a hosted deployment result.
