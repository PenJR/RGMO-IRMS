# Procurement, deliveries, reservations, and partial withdrawals

Implemented locally on 2026-11-04. The local database migration has been applied. This change has not been pushed or deployed to Render.

## Office workflow

1. Under **Inventory → Procurement & Deliveries**, record the inventory item, supplier name and contact number, purchase date, purchased quantity, official receipt (OR) number, unit cost, and funding source. A PDF/JPG/PNG receipt attachment is optional (maximum 5 MB).
2. If this is a new item, create its inventory record with **zero opening stock** first. Recording a purchase does not increase stock.
3. Open the purchase and choose **Receive into Inventory**. Enter the actual received quantity, received date, and optional delivery reference. Separate deliveries can fulfill one purchase; receiving more than the purchased balance is blocked.
4. Submit a resource request. Approval reserves its quantity for its project. Another request can only be approved against unreserved stock.
5. Authorized issuing staff open the request and enter quantities actually issued, withdrawal date, recipient name, and optional notes. Enter zero for lines not being collected.
6. An incomplete release is **Partially Withdrawn**. Its remaining quantity stays reserved. The request becomes **Completed** only after every requested item has been fully withdrawn.
7. Each issue creates a permanent WD record with date, issuer, recipient, quantities, saved item values, and delivery/supplier/OR links. These records remain viewable/printable after completion.
8. Record utilization against the quantities actually withdrawn. A partial withdrawal is eligible; an unwithdrawn reservation is not.

| Example | On hand | Reserved for Project A | Available to other projects | Request status |
|---|---:|---:|---:|---|
| Receive 100 sacks | 100 | 0 | 100 | — |
| Approve Project A's request for 30 | 100 | 30 | 70 | Approved |
| Issue 20 to Project A | 80 | 10 | 70 | Partially Withdrawn |
| Issue the final 10 | 70 | 0 | 70 | Completed |

**Available = on hand − outstanding reservations.** Approval does not physically deduct stock. Issuing stock reduces both on-hand quantity and that request's reservation within a database transaction.

## Traceability and historical records

- New tables: `procurements`, `deliveries`, `withdrawals`, and `withdrawal_items`.
- `request_items.withdrawn_quantity` tracks issued quantities. Outstanding approved/partially withdrawn quantities provide the reservation balance.
- Supplier information is saved on each purchase as a historical snapshot. A purchase represents one item; purchases may share an OR number when the same receipt contains multiple items.
- Deliveries retain the purchase link, date, receiver, quantity, and remaining stock. Inventory transactions retain their delivery references.
- Lot consumption uses historical/unlinked stock first, then deliveries ordered by received date and ID. This is an allocation convention, not a claim about an unrecorded physical batch selection. Issue stock according to that order for physical traceability.
- A withdrawal cannot predate approval or the delivered stock allocated to it. It cannot be future-dated.
- Existing inventory and movement records are not assigned invented suppliers. Existing completed request lines are backfilled as fully withdrawn without deducting stock again. Existing approved requests become reservations.
- Original approved-request slips remain unchanged. New WD records are the permanent evidence of actual issues, including partial issues.
- New supplies-issuance report entries use actual withdrawal dates/quantities and saved unit values, so later item-price changes do not rewrite those new entries. Existing historical report limitations in the manual audit are not all resolved by this change.
- Only pending requests can have their contents edited. Changing approved quantities or releasing a reservation through cancellation would require a separately designed approval/amendment workflow.

## Permissions and safeguards

- Existing `manage-inventory` permission controls purchase creation and delivery receipt; currently this is the Administrator role.
- Existing `view-inventory` permission controls viewing purchase/delivery records and authenticated receipt downloads.
- Existing approvers approve/reserve; Staff/Administrator with `record-withdrawal` record issues; existing utilization permissions are retained.
- Approvals lock the request and relevant inventory rows and check available stock before committing. API approvals use the same service.
- Manual stock-out, stock adjustment/edit, and the inspected API mutation paths cannot reduce stock below reservations. Items with reservations cannot be archived through the inventory services.
- Delivery and withdrawal submission keys prevent duplicate processing of the same form submission. A repeat after request completion may be rejected by the completed-state authorization guard, but does not issue stock again.
- Withdrawals validate request-line ownership, quantities, and dates. A failure on any line rolls the entire withdrawal back.
- Private receipt files are stored on Laravel's local disk under `procurement-receipts`; they are not exposed through a public storage URL. Production hosting must retain that private storage across redeploys, or configure a durable private filesystem before relying on attachments.

## Migration and deployment

Migration: `2026_11_04_100000_add_procurement_and_partial_withdrawals.php`.

- Back up the target database before deploying.
- Deploy the code and run `php artisan migrate --force` with writes paused during the schema change.
- Migration preflight refuses to adopt older approved requests whose combined quantities exceed stock. Reconcile those requests with the office before retrying; do not silently reduce their approved quantities.
- Build frontend assets and cache views through the normal deployment process.
- Verify receipt storage, permissions, and the 100 → reserve 30 → issue 20 → issue 10 example on the target environment.
- The migration's rollback drops the new transaction tables. Use the backup/recovery procedure rather than rolling back after real purchases or withdrawals have been recorded.

Local pre-migration backup: `/tmp/rgmo-before-procurement-20261104-223222.sqlite` (temporary local artifact, not an off-machine backup).

## Verification

- Eight focused feature tests pass (74 assertions): receipt-only stock increases, split deliveries, over-receipt rejection, duplicate submissions, reservations, competing API approvals, partial completion, invalid request lines, stock protection, supplier/OR traceability, utilization limits, rollback, saved issuance values, private receipt access, and delivery-date validation.
- Full suite: **172 passed, 3 failed (1,288 assertions)**. The remaining existing failures are `ManuscriptAccessControlTest` API audit/health authorization assertions and `ReliabilityUsabilityTest`'s older login-logo alt-text expectation. Those unrelated changes were not included in this workflow implementation.
- Browser: complete purchase → receive 100 → request/approve 30 → issue 20 → issue 10 → open supplier-linked WD record. Desktop (1440 px) and phone (390 px) pages checked, with no document-width overflow or JavaScript errors. Wide tables scroll within their containers.
- Build, Blade compilation, changed-PHP syntax, and patch-whitespace checks pass.
- Local database comparison preserved original columns and rows for 5 users, 37 inventory items, 224 inventory transactions, 4 projects, 20 requests, 39 request lines, and 19 utilization records. Browser sample transactions were created only in an isolated `/tmp` database.
- Concurrency controls were inspected and competing approvals were tested sequentially. A simultaneous multi-user PostgreSQL load test and hosted verification have not been performed.

Inventory now shows a **Use first · FIFO** column with the earliest remaining delivery, arrival date, supplier, and quantity. Expand **Delivery queue** to see remaining batches in arrival order with OR references. Equal arrival dates use delivery ID order. Exhausted batches disappear from the queue. Existing unlinked stock is shown separately and consumed first. A regression test verifies older arrivals are consumed first even when recorded after newer arrivals.
