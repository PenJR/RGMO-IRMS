# Roles and permissions

The request-permission matrix below covers the four primary request roles. The system also has Storekeeper, Unit Head, and Auditor roles. Permission definitions live in [`config/rbac.php`](../config/rbac.php); routes, controllers, and services also enforce record ownership and workflow state. Assigning a role selects its predefined permissions, not a custom permission set.

| Capability | Administrator | RGMO Head | Project Manager | Staff |
|---|---|---|---|---|
| View inventory and project catalog | Yes | Yes | Yes | Yes |
| Manage inventory and projects | Yes | No | No | No |
| Submit resource requests | Yes | No | Yes | Yes |
| Edit/cancel own pending requests | Yes | No | No | Yes |
| View requests | All | All | Own | Own plus approved/completed releases |
| Review and approve/reject requests | Yes | Yes | No | No |
| Release approved requests | Yes | No | No | No |
| Record actual utilization | Yes | No | No | Yes |
| View project usage | All | All | Own requests and historical usage | Operational usage |
| Reports and inventory exports | Yes | Yes | No | No |
| Forecasts | Yes | Yes | No | No |
| Audit trail, audit exports, module health | Yes | No | No | No |
| Accounts, role assignment, settings, backups | Yes | No | No | No |
| Personal notifications | Yes | Yes | Yes | Yes |

Administrator is the system superuser, subject to account safeguards and workflow validation. Storekeepers release approved requests. Project visibility does not grant access to another requester's requests.

## Delivery corrections

| Capability | Administrator | RGMO Head | Auditor | Storekeeper | Unit Head | Project Manager | Staff |
|---|---|---|---|---|---|---|---|
| Correct receipts or void unused deliveries | Yes | No | No | No | No | No | No |
| Read original receipts and correction history | Yes | Yes | Yes | No | No | No | No |

The correction policy requires an active, unlocked Administrator and explicit `correct-deliveries` and `view-delivery-corrections` grants. Generic Administrator permission bypass, inventory management, and PO approval do not substitute for these grants. History requires the separate read permission. Routes, controller actions, and the transactional service enforce authorization; hiding controls is only a UI convenience. See [Suppliers and deliveries](SUPPLIERS-DELIVERIES.md#correct-a-recorded-delivery) for correction limits and the audit process.

## Incident reports

Incident reports are a separate operational workflow and do not revise resource requests or other approved documents. Staff and Project Managers can report incidents and view their own reports. Storekeepers can view reports and record investigations; they cannot approve stock changes. Administrators review investigations and resolve reports by approving a stock adjustment, resolving without an adjustment, or closing/rejecting with a reason. The reporter and investigator cannot resolve their own report. Auditors have read-only access to all incident reports. Uploaded evidence is stored privately and downloaded only through an authorized report view.

## Resource workflow

1. Staff or Project Manager submits a request for a project.
2. Storekeeper verifies stock availability.
3. Unit Head endorses the verified request.
4. RGMO Head or Administrator approves the endorsed request and reserves stock.
5. Storekeeper or Administrator records a partial or complete release. Only released quantities are deducted from warehouse stock; unreleased approved quantities remain reserved.
6. Authorized users record actual usage against eligible released request items. Recording usage does not deduct warehouse stock again.

Completed means released, not necessarily fully consumed. Utilization must not exceed the released balance. The server derives the item/project relationship from the request item and the actor from authentication.

Older usage records are preserved. Historical releases are not automatically eligible for the new linked utilization workflow because their old usage may already represent released quantities.

## Edit pending request quantities

An active, unlocked Staff member with `update-pending-request` can edit their own request while it awaits Storekeeper verification. An Administrator with that explicit permission can edit any request in that stage. Project Manager, Storekeeper, Unit Head, RGMO Head, and Auditor roles do not receive ordinary editing access. Account-management permission alone does not grant editing another request.

Open **Requests → View → Edit**, adjust each existing item's requested quantity, and choose **Save changes**. Item identity and line IDs are retained. Quantities must be positive whole numbers; the editor cannot add, remove, or replace items. Archived item quantities stay read-only until an Administrator restores the item. Other existing lines can still be edited.

Increases are checked against usable stock after excluding reservations, expired batches, and undated perishable stock. Existing shortages can be reduced without replenishment, even if more reduction or replenishment is still needed. Storekeeper verification checks the entire request again. Pending edits do not reserve, release, add, or deduct inventory stock.

The server saves headers, quantities, and their before/after audit record together. The request timeline shows the changed item names, units, quantities, and recorded actor. A signed edit token prevents one open form from overwriting a later change, including changes made during the same second. A stale form keeps its entered values and offers **Reload current request** so the user can review the latest values explicitly.

Ordinary edits stop when verification begins. Requests with verification, endorsement, approval, withdrawal, or utilization history cannot bypass this restriction by changing their displayed status. Use the existing controlled correction workflow for eligible verified requests. No database migration is needed for pending quantity editing.

## Verification

Check direct URLs and API calls as well as visible menus. A hidden button alone is not access control. Follow the manual checks in [TESTING.md](TESTING.md).

For detailed implementation decisions, historical migration behavior, and acceptance checks, see [access-control implementation](reviews/access-control-implementation.md). Keep this guide and that record aligned when permissions change.
