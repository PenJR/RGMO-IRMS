# Batch, expiry, and critical-stock monitoring

## References and design

This implementation follows two established inventory patterns:

- [ERPNext Batch documentation](https://docs.frappe.io/erpnext/batch): an item has separate batch identities, and batch references travel with receipts and issues. Here, the same item/batch number reuses one batch profile and can have multiple supplier deliveries. Different expiry dates for the same batch number are rejected until its profile is explicitly corrected.
- [Odoo expiration-date documentation](https://www.odoo.com/documentation/19.0/applications/inventory_and_mrp/inventory/product_management/product_tracking/expiration_dates.html) and [FEFO removal](https://www.odoo.com/documentation/19.0/applications/inventory_and_mrp/inventory/shipping_receiving/removal_strategies/fefo.html): perishable lots carry individual dates and are issued in expiry order. Here, the earliest valid expiry is selected first, with delivery arrival order breaking ties. Ordinary, non-expiring supplies keep FIFO.
- [ERPNext reorder configuration](https://docs.frappe.io/erpnext/item): stock thresholds belong to item profiles. RGMO keeps its existing `min_stock` as the critical/low threshold and `reorder_level` as the reorder point. These trigger monitoring; this change does not automatically create purchase orders.

## Receiving and opening stock

Enable **Track batch / lot numbers** on an item. **This item expires** also requires batch tracking. A tracked opening balance requires its batch number; perishable opening stock also requires its expiry date.

For an approved PO, enter one or more batches for each tracked item under **Receive a delivery**. Each batch has a number, received quantity, and expiry date where applicable. The total is calculated from its batch rows and must match the item quantity. Each receipt line retains its supplier, delivery receipt, invoice, price, receiver, date, and batch link. A receipt is atomic: an invalid batch rolls back the complete receipt. Repeating the same receipt key does not add stock again.

A newly received perishable batch cannot expire before its recorded receiving date. Expiry dates are calendar dates; stock remains usable through its expiry day.

## Balances and releases

- **On hand** is the physical count, including blocked stock.
- **Expired** is the quantity in batches whose expiry day has passed.
- **Quarantined** is perishable stock with a required expiry date that is unknown.
- **Usable** excludes expired and quarantined quantities.
- **Available** is usable stock minus outstanding approved reservations, bounded at zero.

Normal request approval and withdrawal cannot consume expired or quarantined stock. A reservation may become short when stock expires; staff must replenish stock or reconcile the request before release. Releases preserve both batch/expiry snapshots and delivery-source allocations, including on the saved withdrawal record. Dated batch quantities are picked with FEFO; other supplies use FIFO.

## Monitoring and corrections

Open **Inventory → Batches & Expiry** to filter by item, batch number, expired, expiring, missing date, or depleted status. Item profiles show batch balances and link to batch history. Authorized inventory managers can correct batch number/date metadata; these edits are audited and do not change stock quantities. Prior issue snapshots retain the identity/date recorded at release.

Critical-stock checks, reorder indicators, and forecasts use usable stock so a warehouse full of expired supplies is not shown as healthy. The scheduled `app:low-stock-alert` command checks critical stock and positive-stock batch expiry risks. The alert window is `inventory.expiry_alert_days` (default 30 days). In-app notifications are deduplicated per recipient and batch/item state. Depleted batches do not generate expiry warnings. The command does not send email.

The administrator adjustment form supports selecting an existing batch or adding a documented batch increase. A tracked decrease must identify its batch. Expired-batch disposal can reduce blocked physical stock while preserving usable reservations. Incident references, actor snapshots, before/after balances, and retry protection remain required.

## Existing data

Migration `2026_11_07_020000_add_inventory_batches.php` preserves physical stock, original transactions, and original delivery quantities. Existing arrivals and unlinked balances become clearly labelled **Legacy lot** records with unknown manufacturer numbers. Existing item expiry dates are carried forward to these legacy lots; missing required dates remain quarantined. Only known delivery provenance is linked into historical batch movements; missing history is not invented.

If tracking is enabled later for untracked opening stock, the current balance receives a legacy lot without adding stock. Open that batch profile to record the actual number and date. Item-wide expiry edits cannot overwrite recorded batch dates, and expiry tracking cannot be disabled to make blocked batches usable.

## Verification

`BatchInventoryTest` covers receipts, FEFO/FIFO, expiry boundaries, reservations, corrections, batch movements, opening stock, and legacy migration. `BatchMonitoringTest` covers register/detail permissions, configured alert windows, recipient deduplication, usable-stock critical thresholds, and dashboard/report consistency. Tests use isolated SQLite; they do not establish hosted deployment or PostgreSQL concurrency results.
