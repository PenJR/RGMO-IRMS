# Suppliers and deliveries

## Record a delivery

1. Register the supplier under **Suppliers**, including its company name, unique supplier code, contact details, address, and TIN where applicable. Inactive suppliers remain in historical records but cannot be selected for a new purchase order.
2. Under **Procurement & Deliveries**, create a purchase order using an active registered supplier. Add each inventory item, ordered quantity, unit, and price. Submit the order for approval.
3. Open an approved or partially received order. Under **Receive a delivery**, enter the date, delivery receipt number, invoice number if applicable, and any additional reference. The signed-in inventory manager is recorded as the receiver.
4. Enter the quantity received against each PO item. Enter zero for items that have not arrived. Confirm the actual unit prices; they default to the individual PO line prices.
5. Save. Each positive quantity creates its own delivery lot, stock-in transaction, and audit entry. The receipt's lines share a receipt key. A partial delivery leaves the order open until every line is fully received.

Receipts cannot exceed an item's undelivered balance or add stock to an archived inventory item. If any line fails, the entire receipt is rolled back. Repeating the same submission does not add stock again, including when the original submission completed the order. Existing single-item integrations are retained; multi-item orders must specify quantities by PO line ID.

## View history and compare prices

- PO details show every delivered item, quantity, remaining lot balance, receiver, receipt/invoice numbers, and actual unit price.
- Supplier profiles show paginated purchase and delivery history, including every item on multi-item orders.
- Inventory item details include all linked PO lines and delivery history, including items that are not the first item on an order.
- **Compare supplier prices** is available from Procurement & Deliveries and the item/supplier history screens for users with procurement access. Choose an item and optional delivery date range. Each supplier/unit combination shows the lowest, highest, and quantity-weighted average unit price. Delivery details below the comparison show the supporting receipts.
- Received value on the procurement overview uses delivery prices. Outstanding value continues to use the prices of the unreceived PO quantities.

## Database update

Run `php artisan migrate` in the configured application environment to apply `2026_11_07_000000_complete_delivery_records.php`. This adds the PO line link, receipt grouping key, delivery receipt number, invoice number, unit price, and unit of measurement to delivery records.

Existing quantities, references, stock balances, and transaction history are retained. Historical prices and units are copied from the matching PO line, falling back to the historical single-item PO price and item unit. Old generic references are preserved as generic references because their original document type is unknown. Existing supplier records are retained.

## Verification

`tests/Feature/SupplierDeliveryTest.php` exercises partial and complete multi-item receiving, retry protection, rollback, archived/foreign items, prices, dates, permissions, history screens, and migration preservation. Use the isolated SQLite test configuration described in [TESTING.md](TESTING.md).

For manual acceptance, receive only the second item on a two-item PO, receive the remaining quantities on another receipt, and verify each inventory profile and the supplier history. Compare that item's prices across two suppliers and a restricted date range. The tests do not establish PostgreSQL-specific behavior or a hosted deployment result.
