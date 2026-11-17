# Funding sources

Funding choices for purchase orders are managed in the database. Administrators maintain them under **System → Funding sources**. Purchasing users select an active source when preparing an order; they do not create funding sources through the purchase form.

## Manage funding sources

1. Open **System → Funding sources** and choose **Add funding source**.
2. Enter a unique code, name, optional description, and active/inactive status. New codes are saved in uppercase. Codes accept letters, numbers, spaces, periods, underscores, slashes, and hyphens, starting with a letter or number.
3. Save. The source becomes available in purchase selectors when active.
4. Use **Edit** to maintain its name, description, or status. The code cannot be changed after creation because it identifies historical purchases and stock transactions. Create a separate source when a different code is needed.
5. Use **Deactivate** to stop new selection, or **Reactivate** to make the source available again. Deactivation preserves purchase links and stock history. There is no delete action.

The list supports search, status filters, and database pagination. Each detail page shows linked purchase history. Creating, updating, deactivating, or reactivating a source records the administrator and before/after values in the audit trail.

## Use a funding source on a purchase order

- Choose an active registered source under **Procurement & Deliveries** when saving a new order. Unknown or inactive codes are rejected by the server as well as excluded from new-order choices.
- For an editable order, open its detail page and use **Funding source** to select another active source. The change is audited and does not change inventory quantities. Existing order-editing rules determine which users and statuses allow this action.
- A retained inactive source remains visible on an existing order. Other editable details can be maintained without silently changing its funding code, but submitting an order requires an active source. Select a replacement or ask an administrator to reactivate the source when appropriate.
- Deactivation does not cancel an order that was already submitted for review. Its pending approval can finish with the recorded source; selecting a different source still requires an active entry.
- Approved, partially received, completed, and cancelled orders keep their recorded funding source. Deactivating a source does not rewrite approved orders or their delivery transactions. Receiving against an already approved order continues under the normal receipt rules.
- If no active source is available, an administrator must create or reactivate one before a new purchase can be saved.

The purchase retains both the registered source link and its recorded funding code. Deliveries carry that code into stock-in transactions. Changing a source's display name does not rewrite historical codes.

Purchase forms submit the selected database identifier as `funding_source_id`. The server resolves and saves the source's exact code, including retained legacy spelling or whitespace. Existing integrations may still submit a registered code through `funding_source`; the same active-source and order-status rules apply. If an integration submits both fields, they must identify the same source. Conflicting identifiers and codes are rejected rather than choosing one silently.

## Monthly inventory reporting

Monthly inventory reports and PDF exports use database funding sources, including inactive sources needed for history. Historical transaction codes that are not registered are also retained in the report. New sources therefore do not require a code change to appear as report columns.

Movements with no funding code appear under **Unclassified**. Opening quantities and later receipts/issues include these movements when calculating total balances. Unclassified stock is not assigned to JF, STF, or another source without supporting records. Source balances describe recorded movements; this feature does not create a budget-allocation or fund-accounting workflow.

## Database update

Apply migration `2026_11_17_000000_create_funding_sources_table.php` through the normal deployment process:

```bash
php artisan migrate
```

The migration:

- Creates the funding-source register and provisions **JF** and **STF** as active sources, using the code itself as the initial name.
- Preserves other nonblank funding labels already used by purchases or inventory transactions as inactive entries. An administrator can review and reactivate them when appropriate.
- Links existing purchase records to their matching source while retaining original funding text, stock quantities, transaction history, and purchase timestamps.
- Adds an indexed purchase-to-source reference protected against deleting linked sources.

Historical text is preserved exactly; the migration does not infer full names for abbreviations or map older labels to JF/STF. Review imported inactive entries before allowing them on new purchases. See [Deployment](DEPLOYMENT.md) and [Backup and restore](BACKUP-RESTORE.md) for the application's release process.

## Verification

The isolated feature tests cover source management and authorization, code validation and immutability, lifecycle/audit history, migration preservation, purchase selection/change/submission, delivery propagation, and dynamic monthly report balances. Run tests using the in-memory SQLite configuration in [TESTING.md](TESTING.md). Automated local checks do not establish the state of a deployed database or PostgreSQL-specific concurrency behavior.
