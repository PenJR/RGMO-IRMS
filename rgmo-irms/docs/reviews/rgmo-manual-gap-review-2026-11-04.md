# RGMO Operations Manual — system gap review

Date: 2026-11-04. Reviewed checkout: `1be5fbd`.

Source: supplied **RGMO Operations Manual (2022)**, `/home/rhimar-clifford-capunong/Downloads/RGMO-Operations-Manual-2022.pdf`. Section numbers and printed page numbers below refer to this manual, not PDF viewer page numbers.

## Conclusion and scope

The application implements an inventory/request/utilization system with reporting and forecasting. The manual describes a much wider institution: enterprises, production, procurement, finance, personnel, machinery, marketing, and academic activities. Missing institution-wide modules are potential scope extensions, not automatically defects in the capstone.

The immediate priorities are reliable inventory reports, consistent API permissions, permanent withdrawal records, and agreement on the recommendation/approval chain. The current four-role configuration and its tests reflect a separate manuscript-oriented workflow; changing that matrix to implement the manual requires an explicit requirements decision.

This is an audit of local source, migrations, routes, views, and selected automated behavior. It does not establish the state of Render, actual office practice, or whether the 2022 manual has been amended. References to procurement, monetary thresholds, and accounting rules describe the supplied manual only; this is not a determination of current law. No application code, roles, or live records were changed for this review. Tests used in-memory SQLite.

## Existing coverage

- Inventory items, types, units, quantities, stock-in/out history, opening-balance transactions, adjustments, low-stock alerts, and item-level expiry fields.
- Resource requests linked to a currently active project; approval/rejection, cancellation, and fulfillment. Fulfillment deducts stock transactionally and checks availability again.
- A printable/PDF withdrawal slip for approved requests.
- Separate utilization entries linked to a completed release, with dates, activities, and a cumulative quantity limit. Usage does not deduct warehouse stock a second time.
- Projects with dates, status, descriptions, and manager assignments.
- Inventory, resource usage, requests, biological assets, supplies issuance, monthly inventory, and audit reporting; selected PDF/CSV exports.
- Four configured roles, authentication, notifications, audit logging, and inventory forecasting.

Evidence: [request service](../../app/Services/ResourceRequestService.php), [usage service](../../app/Services/ResourceUsageService.php), [inventory service](../../app/Services/InventoryService.php), [report controller](../../app/Http/Controllers/ReportController.php), [role matrix](../../config/rbac.php).

## Priority 1 — defects and record reliability within existing scope

### G01. Monthly inventory quantities can disagree with actual stock — reproduced

**Manual:** §§3.10(4), 3.12(1a), 3.13, pp. 24–26: input ledgers, periodic inventory reporting, and perpetual records.

`ReportService::getMonthlyInventoryReport()` calculates beginning balances only from transactions tagged `RGMO`, `DA Grant`, or `DA Hybrid`. Opening balances created by `InventoryService::createItem()` have no funding source. Request fulfillment also creates stock-out transactions without a funding source. These movements therefore disappear from source-filtered opening balances in subsequent months.

Isolated examples:

1. Create 10 units on January 15 using the inventory service. With no further movements, actual stock is **10**, but February's report gives an ending balance of **0**.
2. Receive 10 units tagged RGMO, then fulfill a request for 3 in January. Actual stock is **7**, but February's report gives an ending balance of **10**.

**Recommended correction:** establish a consistent funding-source/allocation model for opening balances, receipts, adjustments, and releases; reconcile unclassified and historical transactions; verify that every report's beginning + receipts − issues equals ending stock. Do not simply assign all historical movements to RGMO without evidence.

Evidence: [InventoryService::createItem](../../app/Services/InventoryService.php), [ResourceRequestService::fulfillRequest](../../app/Services/ResourceRequestService.php), [ReportService::getMonthlyInventoryReport](../../app/Services/ReportService.php).

### G02. Historical report amounts change when an item's price changes — reproduced

**Manual:** §§3.7, 3.12, pp. 22, 25: retained operational records and periodic reporting.

Supplies issuance and other valuation reports multiply historical quantities by the item's **current** `price`. The withdrawal slip also uses the current item price. No transaction unit-cost snapshot is present in the inspected request-line/transaction models.

Diagnostic: a January issuance of 3 units at ₱20 reports ₱60. Editing the item price to ₱50 changes that same January issuance to **₱150**.

**Recommended correction:** record the approved unit cost/value with each relevant receipt/issue and retain report-period snapshots or a reproducible valuation history. Confirm the valuation method with the office; the manual does not specify a software costing algorithm.

Evidence: [ReportService::getSuppliesIssuanceReport](../../app/Services/ReportService.php), [withdrawal template](../../resources/views/requests/withdrawal-slip.blade.php), [InventoryTransaction](../../app/Models/InventoryTransaction.php), [RequestItem](../../app/Models/RequestItem.php).

### G03. API access does not consistently enforce the configured audit boundary — reproduced

This is an **existing application permission defect**, not a claim that the manual prescribes the four software roles.

The checked-in access-control tests expect audit/health access to be restricted. Two tests fail because `/api/ops/audit/logs` and `/api/dashboard/health` return HTTP 200 where HTTP 403 is expected. The explicit RGMO Head health assertion reproduces the latter.

API route groups permit `generate-reports` **or** `view-audit-trail`; `PermissionMiddleware` accepts any listed permission. Audit controller methods do not add the narrower check. Related audit-user/filter and recent-activity endpoints deserve the same review, but the test run stops at earlier assertions and does not independently prove every endpoint.

**Recommended correction:** enforce `view-audit-trail` consistently at each protected API boundary, then rerun the role matrix tests. Preserve the agreed role model unless the user requests a scope change.

Evidence: [API routes](../../routes/api.php), [permission middleware](../../app/Http/Middleware/PermissionMiddleware.php), [operations controller](../../app/Http/Controllers/OperationsController.php), [existing tests](../../tests/Feature/ManuscriptAccessControlTest.php).

### G04. Withdrawal evidence is temporary and unavailable after completion — confirmed behavior

**Manual:** §§3.7, 3.10(2,4), pp. 22, 24: recordkeeping and a supporting approved withdrawal slip.

The slip exists, but:

- P.O./P.R. numbers and issuer/receiver/signatory names are editable print/download parameters. The controller validates and renders them; it does not persist them as approval or receipt evidence.
- `ensureWithdrawalReceiptIsAvailable()` allows only `approved` requests. After fulfillment marks a request `completed`, viewing and downloading its slip returns 403. An existing test explicitly enforces this behavior.
- There is no saved recipient acknowledgment, approved document version, or attachment in the inspected request schema. The audit trail identifies the fulfillment actor but does not replace a receiver's acknowledgment.

**Recommended correction:** retain a permanent issue record and approved slip snapshot, support authorized reprints after release, and distinguish approval, physical issue, and receipt acknowledgment. Changing the approved-only rule also requires updating its existing acceptance test.

Evidence: [ResourceRequestController](../../app/Http/Controllers/ResourceRequestController.php), [ResourceRequest](../../app/Models/ResourceRequest.php), [RequestWorkflowTest](../../tests/Feature/RequestWorkflowTest.php).

## Priority 2 — process alignment close to the system's purpose

| ID / area | Manual expectation | Current coverage and gap | Suggested addition |
|---|---|---|---|
| G05 — Recommendation and approval | §3.10(2), p. 24: Project Manager recommendation and Director approval of withdrawal slips. §§2.3–2.4 distinguish Directors, VP-RGMO, and project managers. | Requests have one approver/timestamp. Project Managers cannot record recommendations; there is no Director role/stage. An RGMO Head account is not evidence of a Director's formal authority. | Agree on who maps to each manual responsibility; record separate recommendation and approval decisions, actors, dates, and remarks. Retain the storekeeper/release step. |
| G06 — Budget and PPMP linkage | §§3.2(1–2), 3.6, 3.8, 3.10(1,3), pp. 17–18, 21–24: approved plans/budgets, consolidated PPMP, and supplementary approvals. | Active-project checks and available stock are enforced. No budget period, approved budget line, PPMP item, procurement case, or supplementary approval links were found. A P.O./P.R. textbox on a slip is not a procurement workflow. | Start with links/attachments and approval references for externally approved plans; then add budget allocations, request checks, PPMP consolidation, and purchasing/receiving records if included in scope. |
| G07 — Project responsibility and assignments | §§3.2(4), 3.7(2–3), pp. 18, 22: normally one project per manager, exceptions where necessary; project assets and monthly accountability. | Managers can be assigned to multiple projects with no exception record. Request validation checks that a project is current, not that the requester is assigned to it. Project Managers see usage for their own requests rather than all usage of their assigned project. | Add a named responsible manager and approved assignment exceptions. Confirm cross-project request rules with RGMO before enforcing them; assigned-project restrictions are a design recommendation, not an explicit manual software rule. Provide responsible managers an appropriate project-level activity summary. |
| G08 — Storekeeper and asset accountability | §§3.8(5), 3.10(4), 3.13, pp. 22, 24–26: accountable custodians, storekeeper ledgers, central warehouse, and physical safeguards. | Stock and actor histories exist, but no warehouse/bin, accountable asset holder, asset handover, or designated storekeeper record was found. Generic Staff can release approved requests. | Record stock location, designated issuing responsibility, asset custody and handover. A count/reconciliation workflow is a useful implementation recommendation; software alone cannot establish physical safeguards. |
| G09 — Farm activities and production results | §§3.1(6), 3.7(1), 3.9(14), 3.10(3), 4.4, pp. 17, 22, 24, 29–30: activity logs, production information, plans, and technical ratios. | Utilization captures date, activity, quantity, and notes. It does not capture a full farm logbook, production cycle/plot area, work accomplished, harvest/output, input-to-yield relationships, or plan-versus-actual results. Forecasting estimates inventory needs, not project yield or profitability. | Extend project activity records with field/cycle, planned operation, inputs, labor/equipment references, output, and supervisor review. |
| G10 — Formal report submission | §§3.7(3), 3.12, 4.4(c), pp. 22, 25, 29: recurring reports passed through Directors and VP-RGMO, including accomplishment/status and performance evaluation. | Report generation/export exists. No reporting-period submission, review/return, approval, received acknowledgment, locked version, overdue submission tracking, or consolidated accomplishment/evaluation workflow was found. PMs also lack `generate-reports` in the current matrix. | Add period-based submissions with report owner, reviewer, attachments, revision history, and due dates. Decide whether PM preparation is electronic or an uploaded external report. |
| G11 — Machinery and equipment operations | §§3.2(11), 3.11, 4.2, pp. 18, 24–25, 28: job orders, trip tickets, scheduling, repairs, preventive maintenance, fuel records, outside-use approvals. | Generic inventory/usage records cannot identify the operational state, booking, trip, operator, maintenance, fuel consumption, or return condition of an individual machine. | Treat reusable machinery separately from consumable stock: asset register, job orders, schedule, issue/return, trip tickets, meter/fuel log, maintenance plan, and required approvals. |

Code basis for G05–G11: [role matrix](../../config/rbac.php), [request model](../../app/Models/ResourceRequest.php), [request validation](../../app/Http/Requests/StoreResourceRequestRequest.php), [CurrentProject rule](../../app/Rules/CurrentProject.php), [project controller](../../app/Http/Controllers/ProjectController.php), [project policy](../../app/Policies/ProjectPolicy.php), [usage visibility](../../app/Models/ResourceUsage.php), [inventory model](../../app/Models/InventoryItem.php), [report controller](../../app/Http/Controllers/ReportController.php). Findings of absent workflows are based on the inspected routes, models, migrations, services, and views, not on UI labels alone.

## Broader manual coverage — proposed extensions, subject to project scope

| Area | Manual reference | Missing workflow / why existing features are insufficient |
|---|---|---|
| Production sales and marketing | §§3.5, 4.1, pp. 20–21, 27–28 | No customer/order, approved selling-price history, quotations/bids, invoice, cash/credit collection, employee credit control, remittance, or Daily Production and Sales Report workflow. An inventory `price` and stock-out are not a sale/payment record. |
| Spoilage, condemnation, and disposal | §§3.5(17–20), 4.1(d), 5.3, pp. 21, 27, 35 | Item expiry tracking exists. No recorded spoilage assessment, markdown approval, condemnation/disposal case, waste classification, or disposal authorization chain was found. Stock adjustment alone cannot document the reason and required approvals. |
| Accounting and financial reporting | §§3.1, 3.3–3.4, 3.6, 3.12, pp. 17–22, 25 | No project subsidiary ledger, revenue/expense accounting, trial balance, balance sheet, income statement, bank reconciliation, net-income allocation, or incentive calculation workflow. Inventory value must not be presented as profit. Integration or approved accounting-document uploads may be more suitable than rebuilding the Accounting Office's system. |
| Personnel and labor | §§3.2, 3.9, pp. 17–19, 23–24 | User accounts are not employment records. No job-order contracts, DTR approval, labor deployment, piece-work survey/inspection, overtime approval, payroll/fund attribution, or separation-benefit administration was found. |
| Academic and research use | §§5.1–5.2, pp. 34–35 | No OJT/thesis/research request chain, approved proposal/schedule storage, supervisor assignment, applicable fee record, daily accomplishment submission, or completion certificate workflow. A resource request covers materials, not approval of the academic activity. |
| Specialized support services | Chapter 4, pp. 29–33 | No dedicated grain-processing schedules, enterprise permits/collections, printing jobs, tourism bookings, or irrigation operations workflow. Generic projects can identify these activities but do not implement their operations. |

## Manual ambiguities to resolve before automating rules

1. **Direct-sale threshold conflict:** §3.5(4), p. 20, describes retail direct sales up to ₱50,000; §4.1.1, p. 28, describes a ₱10,000 direct-sale threshold and auctions above it, with seed/seedling exceptions. RGMO must identify the applicable policy and amendments. Do not hard-code either threshold from this review.
2. **Role mapping:** the manual separates Director, VP-RGMO, Project Manager, storekeeper, Accounting, and Marketing responsibilities. The four application roles are a different abstraction. Record which functions the capstone covers and which signatures remain outside it.
3. **Conditional/provisional provisions:** §3.4 includes a provision marked “for Consultation” and allows later incentive revisions. Amounts and approval rules should be confirmed by the office before implementation.
4. **Existing acceptance decisions:** completed-slip access restrictions and PM request-only visibility are deliberate behaviors in current tests. Their conflict with broader operational needs should be resolved as a requirements change, not silently altered during an audit.

## Suggested implementation sequence

1. Fix and regression-test G01–G03: inventory reconciliation, historical values, and API permissions.
2. Resolve the authority mapping, then implement G04–G05: permanent withdrawal evidence and recommendation/approval stages.
3. Add budget/plan references, project responsibility, and custody records (G06–G08). Support external approvals first if the office keeps the full processes elsewhere.
4. Add richer farm activity and report-submission workflows (G09–G10).
5. Agree on separate phases for machinery, production/sales, finance integration, HR, and academic services. Avoid expanding a resource-management capstone into every office function at once.

Candidate farm-input flow, derived from §§3.8 and 3.10 and extended with digital recordkeeping:

**Approved plan/budget reference → request → Project Manager recommendation → Director approval → storekeeper issue and ledger entry → recipient acknowledgment → actual utilization → periodic report and review.**

Recipient acknowledgment and digital status names above are proposed implementation details. The manual explicitly supports the plan, recommendation, approval, slip, ledger, and reporting requirements; it does not prescribe a software state machine.

## Verification evidence and limits

- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/RequestWorkflowTest.php tests/Feature/ManuscriptAccessControlTest.php`: **15 passed, 2 failed; 184 assertions**. Failures were HTTP 200 versus expected 403 at `ManuscriptAccessControlTest.php:47` and `:129`.
- Temporary diagnostic at `/tmp/ManualAuditDiagnosticTest.php`: **2 diagnostic tests, 6 assertions** reproduced the two monthly quantity scenarios and historical price changes. These assertions intentionally verify the observed defects; “passed” does not mean those behaviors are correct.
- No full-suite, physical stocktake, office workflow interview, or deployed-service audit was performed.
- The tracked SQLite file was already modified when this review began; it was not used as the diagnostic database or restored.
- Application implementation remains unchanged. This document records findings and proposed next steps only.
