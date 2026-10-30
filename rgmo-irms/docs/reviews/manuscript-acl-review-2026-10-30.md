# Manuscript and ACL alignment review

This is the pre-implementation review. See [the implemented ACL and verification results](access-control-implementation.md) for the current local behavior.

Reviewed 2026-10-30 against `Capstone - RGMO (3).pdf`. Page numbers below are PDF page positions, not printed manuscript numbers. This is a local code and automated behavior review, not a deployed-site or browser certification. No application permissions were changed during this review.

## Conclusion

The system is partially aligned. The four roles exist, but Staff withdrawal access is missing, Project Managers have substantially broader privileges than the documented requester role, and audit-trail routes do not enforce their dedicated permission. The utilization workflow is incomplete relative to the manuscript.

## Role comparison

| Role | Documented responsibilities | Current implementation | Assessment |
|---|---|---|---|
| Administrator | Accounts, role assignment, inventory administration, reports, audit trail, settings | These capabilities exist; Administrator bypasses all named permission checks and can also approve, submit, release, and record usage | Core capabilities align; explicitly document whether Administrator is an unrestricted superuser |
| Staff / RGMO Personnel | Inventory viewing; own requests, pending edits/cancellation, status/history; approved withdrawals; utilization entry; notifications | Own-request controls exist; withdrawal permission is absent; utilization API exists but no dedicated entry page was found | Missing withdrawal access and complete utilization workflow |
| Project Manager / Requester | Inventory viewing; submit requests; monitor own requests/history; notifications | Also receives review, release, utilization, reports, forecasts, and pending edit/cancel permissions; review permission allows access to others' requests | Broader than documented requester responsibilities |
| RGMO Head / Authorized Personnel | Request review, decisions and remarks, monitoring, reports, forecasting, notifications | These exist, plus inventory and project management, withdrawals, and audit-trail permission | Core duties align; extra rights require documented justification or removal |

Basis: use-case narrative on PDF p.43, Staff withdrawal UC-10 on pp.52–53, utilization UC-11 on pp.53–54, and role-specific UAT on pp.67–70. Configuration: `config/rbac.php`; enforcement: policies, route middleware, controllers, and services.

## Findings

### 1. High: Staff cannot perform the documented withdrawal task

`config/rbac.php:29` omits `record-withdrawal` for Staff. `app/Policies/ResourceRequestPolicy.php` requires this permission and approved status for fulfillment. An authenticated Staff POST to its own approved request's fulfillment endpoint returned 403. This conflicts with UC-10 and UAT-S07 (PDF p.68).

Remedy: grant Staff withdrawal permission, then define whether Staff may release only their own requests or all approved requests. If Staff handles other users' releases, provide a specifically scoped release queue and record-view authorization; adding a permission alone does not expose those requests through existing ownership checks.

### 2. High: Project Manager is configured as an operational reviewer, not just a requester

`config/rbac.php:42` grants review, withdrawal, utilization, reporting and forecasting. `ResourceRequestPolicy::view()` lets reviewers view other users' requests without project-assignment restrictions. Local checks confirmed the manager can open another user's request, the pending-review page and inventory reports; the policy also permits release of an approved request. The manager does not have approval permission.

Remedy: align the role with PDF p.43 and UAT-P01–P06, or revise the manuscript if these broader responsibilities are intentional. Pending edit/cancel is another extra ability to document. Do not use project assignment as an assumed boundary: current request review is not restricted by it.

### 3. High: Audit access is allowed through general report permission

Both web report routes in `routes/web.php` and API report/audit routes in `routes/api.php` group `generate-reports` and `view-audit-trail` together. `PermissionMiddleware` accepts any listed permission. `ReportController::auditTrail()` does not add a dedicated audit permission check.

A Project Manager with no `view-audit-trail` permission successfully opened `/reports/audit-trail` and `/api/ops/audit/logs`. This is an actual enforcement mismatch, independent of whether the manuscript should allow manager reporting. The navigation also exposes Audit Trail within the shared Reports section.

Remedy: protect audit pages, exports and API endpoints with `view-audit-trail` specifically, and use the same check for navigation visibility.

### 4. High: Utilization entry does not match UC-11 and permits arbitrary user attribution

UC-11 expects Staff to choose a released transaction and enter quantity used, date used, activity and remarks. No dedicated utilization entry view or web route was found. `ResourceRequestService::fulfillRequest()` automatically records the entire released quantity as usage. This does not establish actual subsequent consumption.

`OperationsController::logResourceUsage()` accepts an optional user ID supplied by the caller and delegates directly to `RmsService::logResourceUsage()`, which creates a usage row. A Staff request successfully stored usage under the Administrator's user ID without an associated withdrawal. There is no released-transaction link or cumulative usage-versus-release check in this path. The model also lacks separate usage-date and activity fields.

The route uses `record-utilization OR record-withdrawal`, meaning the RGMO Head can call it despite lacking `record-utilization` in the role configuration.

Remedy: implement the documented Staff form and dedicated utilization authorization; derive the recording actor from authentication, model any beneficiary separately, link usage to a released transaction, and validate remaining usable quantity. Separate release tracking from actual usage to avoid counting both as consumption. Include utilization audit logging.

### 5. Medium: RGMO Head has additional operational and audit rights

`config/rbac.php:59` grants inventory management, project management, release and audit access. Inventory policy allows creation, editing, deletion and import based on `manage-inventory`. Local checks confirmed the Head can open inventory creation and audit pages, while user management remains forbidden.

The documented Head role emphasizes review, approvals and oversight. Decide whether these operational rights are intended and align the manuscript or configuration accordingly; absence from a use-case list is not by itself proof of an organizational prohibition.

### 6. Clarification: Role assignment exists; arbitrary permission editing does not

Administrator forms assign one of four roles. Permissions are inherited from `config/rbac.php`; the API named `manageRolesPermissions` returns that configuration and does not edit it. If “assign roles and permissions” means selecting predefined roles, this is aligned. If it means editing individual grants in the application, that feature is missing. Clarify UAT-A03 and the role-assignment use case.

### 7. Clarification: Other read scopes are wider than role-specific duties describe

All four roles can view projects, and project listing/detail authorization does not enforce manager assignments. Inventory exports are allowed to inventory viewers, including Staff and Project Managers. These may be intended, but should be represented explicitly in the final ACL matrix rather than assuming “view inventory” excludes downloads or that project assignment limits visibility.

## Manuscript inconsistencies to resolve

- PDF p.35 says the system has three roles and omits Project Manager. PDF p.43 and the UAT tables describe four.
- UAT-S05 includes released/completed status, while fulfillment currently moves a request directly from approved to completed with a fulfillment timestamp. Clarify whether released and completed must be separate workflow stages.
- State whether Administrator has all operational powers or only its listed administrative duties.
- State the allowed ownership scope for each action: own records, assigned project records, or all records.

## Verification

- Existing focused suites: 27 tests, 144 assertions passed (`RbacConfigurationTest`, `RequestWorkflowTest`, `SecurityHardeningTest`, `ProjectManagementTest`).
- Temporary diagnostic test: 1 test, 16 assertions passed against current behavior. It reproduced Staff fulfillment denial, manager cross-user request/review/report/audit access, Head inventory/audit access, forbidden user management for Staff/Head, and Staff creation of usage attributed to another user.
- Diagnostics ran using the PHPUnit testing configuration with in-memory SQLite. Production data was not used.
- Passing existing tests does not prove manuscript alignment: some tests explicitly expect the broader Project Manager reports/forecasts and Head project-management behavior.
- Sidebar visibility and routes were inspected in source; no interactive browser or deployed-site verification was performed.

## Recommended implementation order

1. Establish the four-role ACL from the use cases and UAT, including record ownership and the Administrator exception.
2. Correct audit endpoint authorization and utilization user attribution.
3. Enable the intended Staff release workflow and narrow Project Manager privileges.
4. Complete utilization entry and released-quantity validation.
5. Resolve extra Head privileges and update navigation, automated tests and UAT expectations together.
