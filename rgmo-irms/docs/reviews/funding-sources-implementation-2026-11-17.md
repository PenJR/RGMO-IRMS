# Database-managed funding sources — implementation verification

Date: November 17, 2026. Scope: replace hard-coded purchase funding choices with a managed register, preserve existing records, and integrate funding labels into monthly inventory reporting.

## Result

- Administrators manage sources under **System → Funding Sources**, following the supplier module's Laravel controller/policy, Bootstrap/Blade, validation, pagination and audit patterns.
- Sources have a code, name, description and active/inactive status. New codes are normalized to uppercase and checked for case-insensitive duplicates. Existing codes cannot be renamed; deactivation replaces deletion in the application workflow.
- The migration provisions JF/STF as active entries, imports other existing purchase/transaction codes as inactive entries, and backfills an indexed purchase-to-source foreign key. Original source strings, stock, receipt history and purchase timestamps remain intact. Rollback removes only the new register/reference.
- Purchase forms submit source IDs; the server derives the exact code snapshot. Registered code-based requests remain compatible. Conflicting ID/code pairs, unknown sources and new inactive selections are rejected.
- Funding can change only while the purchase is editable. An unchanged inactive reference can remain during metadata edits, but a new submission requires an active source. Retirement does not cancel a review that was already submitted. Approved/received/cancelled purchases cannot change funding through the application.
- Existing approved orders can receive deliveries after a source is retired. Delivery transactions retain the purchase's recorded funding code, including legacy whitespace or markup-like text; UI output is escaped.
- Purchase lists display and filter by funding source, including inactive entries for history. New-order choices include active entries only.
- Monthly inventory HTML/PDF reports use managed and observed historical source codes and an explicit Unclassified bucket. Previously omitted JF/STF and unclassified opening movements now contribute to totals. Missing attribution is not assigned to another fund. A code appearing after report headings are prepared contributes to Unclassified for that render and is classified on refresh.

This is funding-source management, not a budget allocation or general fund-accounting module. Historical valuation policy and unrelated agricultural templates were not changed. See [the user guide](../FUNDING-SOURCES.md).

## Checks performed

Runtime: portable PHP 8.3.36, PHPUnit 11.5.56, existing installed dependencies. Tests used the repository's guarded in-memory SQLite configuration, with array mail/cache/session and synchronous queues. No configured application database, production records, environment settings or deployed host were changed.

| Check | Result |
|---|---|
| Four funding test files | **27 passed; 358 assertions** |
| Earlier broader purchasing/supplier/funding integration run | **50 passed; 485 assertions**, before additional canonical-ID regression cases |
| Final full suite | **289 tests; 2,473 assertions; 276 passed; 8 failures; 5 errors** |
| PHP syntax checks | **31 changed/new PHP files; zero syntax errors** |
| Whitespace check | `git diff --check` passed |

Funding coverage:

- [Management tests](../../tests/Feature/FundingSourceManagementTest.php): authorization across roles, invalid inputs, duplicate codes, code immutability, audit history, retirement/reactivation, retained references and unavailable deletion.
- [Migration tests](../../tests/Feature/FundingSourceMigrationTest.php): defaults without running seeders, mixed-case/long/whitespace/transaction-only/numeric legacy labels, blank/null handling, backfill, rollback and reapplication without rewriting history.
- [Purchase tests](../../tests/Feature/ProcurementFundingSourceTest.php): active choices, ID/code compatibility and conflicts, invalid/inactive IDs, legacy single-item compatibility, funding changes, submission checks, approved locks, history filters, empty catalog, permission boundaries, and exact legacy codes through actual receiving.
- [Report tests](../../tests/Feature/FundingSourceReportTest.php): active/inactive/unregistered/unclassified movements, period boundaries, heading snapshots, HTML, empty states and generated PDF output.

Normal commands, using a configured PHP executable:

```bash
php vendor/bin/phpunit tests/Feature/FundingSourceManagementTest.php tests/Feature/FundingSourceMigrationTest.php tests/Feature/FundingSourceReportTest.php tests/Feature/ProcurementFundingSourceTest.php --no-coverage --do-not-cache-result
php vendor/bin/phpunit --no-coverage --do-not-cache-result
```

Existing purchase fixtures that submitted the old, unregistered `RGMO` value were updated to select the migration-provisioned active `JF` entry. Historical-record tests still retain old labels where appropriate. This resolves six previously observed funding-validation failures/errors without removing their substantive assertions.

## Remaining full-suite failures

All 13 remaining nonpassing tests were observed before this feature; no new failing test was introduced in the final full run.

| Existing area | Nonpassing tests | Observed issue |
|---|---|---|
| Controlled stock adjustment aliases | `test_every_manual_alias_uses_controlled_adjustments_and_requires_incident_reference`; `test_inventory_permission_does_not_allow_non_administrators_to_adjust_through_any_endpoint` | Numeric item URLs return 404 after public-ID routing changes. |
| Export expectations | `HighPriorityFixesTest::test_inventory_csv_and_excel_exports_include_more_than_250_items` | Old expectation requires a removed SKU column/value. |
| Request reservation workflow | `test_legacy_shortage_migrates_without_rewriting_history_and_can_be_replenished`; `test_competing_api_approval_and_manual_stock_changes_cannot_take_reserved_stock` | Old final-approval/API expectations conflict with required verification/endorsement stages. |
| Request reservation workflow errors | `test_partial_withdrawal_keeps_remaining_reservation_until_completion`; `test_provenance_and_utilization_follow_actual_partial_release`; `test_wrong_request_lines_and_unauthorized_procurement_are_rejected`; `test_multi_item_failure_rolls_back_every_stock_and_withdrawal_change`; `test_partial_issuance_report_uses_saved_value_and_dates` | Fixtures call final approval without the earlier stages. Their later assertions are not reached. |
| FIFO display | `ProcurementReservationTest::test_fifo_uses_arrival_order_and_inventory_shows_next_supplier` | Existing display-order assertion remains unresolved; this result alone does not establish missing FIFO allocation. |
| Permission catalog | `RbacConfigurationTest::test_role_permissions_are_defined_permissions` | Existing role references to `view-incidents` are absent from the permission catalog. |
| Logo wording | `ReliabilityUsabilityTest::test_layout_uses_local_frontend_assets_and_logo_has_alt_text` | Exact alt-text expectation differs from the existing wording. |

## Deployment step and limits

Apply `2026_11_17_000000_create_funding_sources_table.php` with `php artisan migrate` through the normal release process before using the new screens. The configured application database was intentionally not migrated during this code-and-test task. Migration preservation and rollback were exercised only in isolated test databases.

No PostgreSQL server, deployed scheduler, browser session or Docker image build was exercised. The changes introduce no JavaScript/CSS dependency or build-input changes; server-rendered screens and PDF output were exercised through application tests. Passing funding tests do not make the unrelated full-suite failures pass.
