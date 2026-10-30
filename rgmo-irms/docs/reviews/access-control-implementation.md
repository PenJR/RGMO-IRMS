# Access control implementation

Implemented against the four-role use cases and UAT in `Capstone - RGMO (3).pdf` on 2026-10-30. The earlier review is a pre-change snapshot.

## Final role matrix

| Capability | Administrator | Staff | Project Manager / Requester | RGMO Head |
|---|---|---|---|---|
| View inventory and project catalog | Yes | Yes | Yes | Yes |
| Manage inventory and projects | Yes | No | No | No |
| Submit requests | Yes | Yes | Yes | No |
| Edit/cancel own pending requests | Yes | Yes | No | No |
| View request details | All | Own, plus approved/completed releases | Own | All |
| Review and approve/reject | Yes | No | No | Yes |
| Release approved requests | Yes | All approved requests | No | No |
| Record actual utilization | Yes | All eligible releases | No | No |
| View project usage | All | All operational usage | Own requests and own historical usage | All |
| Reports and inventory exports | Yes | No | No | Yes |
| Forecasts | Yes | No | No | Yes |
| Audit logs, audit exports and module health | Yes | No | No | No |
| Accounts, roles, settings, backups | Yes | No | No | No |
| Personal notifications | Yes | Yes | Yes | Yes |

Administrator is intentionally a superuser. User management retains its existing self/last-admin safeguards. Role selection assigns predefined permissions; this is not a custom permission editor. All roles may browse the project catalog to identify projects; assignment does not imply access to another requester's requests. Staff are office release/utilization operators, so their withdrawal queue includes all approved and completed requests, but not other users' pending/rejected requests.

## Workflow

1. Staff or Project Manager submits a request.
2. Head reviews, approves or rejects it with remarks.
3. Staff opens Requests → Withdrawals, reviews an approved request, and releases it. Existing full-request fulfillment deducts stock once and marks the request Completed with a release timestamp.
4. Staff opens Requests → Record Utilization, selects a released item and records quantity used, date, activity and remarks.
5. The server validates the release and remaining quantity, sets the actor from authentication, and writes an audit entry. Usage does not deduct warehouse stock a second time.
6. Head reviews Resource Usage reports. The chart and table use saved records instead of placeholder data.

Web and API utilization share the same validation and transactional service. A row lock on the released request serializes quantity checks. API clients must supply `request_item_id`, `quantity`, `used_on`, `activity`, and optional `notes`. Caller-supplied `user_id`, `inventory_item_id`, and `project_id` are rejected; associations come from the released request.

## Historical data and migration

Migration: `2026_10_30_230000_add_linked_resource_utilization.php`.

The migration adds nullable request-item/date/activity fields to usage records and a `tracks_utilization` flag to requests. It does not delete or rewrite existing usage records. Historical requests default to false because old releases could already have generated usage rows. Their old usage remains visible, and they cannot receive new linked utilization entries. New releases set the flag true and no longer create usage automatically.

This deliberately preserves historical reporting interpretation rather than guessing which old records represent actual consumption. Historical releases require a separately reviewed reconciliation before being enabled for the new workflow. Reports state that older records may represent released quantities. New entries are explicitly actual usage. Request completion continues to mean released, not fully consumed.

## Deployment

- Back up the target database.
- Deploy the code and run `php artisan migrate --force` before serving the new utilization UI.
- Refresh configuration with `php artisan config:cache` if the deployment uses cached configuration, and rebuild the frontend with `npm run build`.
- Check all four roles with the UAT checklist below before publishing the result as validated.
- Rolling back after recording linked usage would remove the new linkage/date/activity columns; restore the matched code/database backup instead of blindly rolling back live data.

## Focused UAT checklist

- Administrator: create/edit accounts and roles, manage inventory/project, open audit page/export, settings and module health.
- Staff: submit and edit own pending request; cannot view someone else's pending request; release another user's approved request; submit utilization and verify remaining balance.
- Project Manager: submit and view own request; direct access to others' requests, edits, approvals, withdrawal, utilization, reports, forecasts and audit routes must fail.
- Head: review/approve/reject requests; view reports and forecasts; cannot manage inventory/projects, release stock, enter utilization or access audit data through pages/API/dashboard.
- Negative usage checks: reject caller user attribution, pending/unreleased/historical request, date before release, future date, zero/negative quantity, and cumulative quantity exceeding release.
- Verify notifications remain personal; check sidebar links and direct endpoints independently.
- Verify report date filters use the usage date, and stock is deducted once at release only.

Automated tests cover the role matrix, navigation, direct endpoint denial, ownership, actual release-to-usage flow, cumulative limits, attribution, auditing and reporting. Browser/device UAT and deployed verification remain separate from automated checks.

## Local verification results

- Full suite: **157 tests passed, 1,152 assertions**.
- Dedicated ACL suite: **7 tests passed, 113 assertions**.
- Production Vite build and Blade template compilation passed.
- Local SQLite migration applied after backup to `/tmp/rgmo-before-acl-20261030.sqlite`.
- Compared all pre-existing columns against the backup: 5 users, 37 inventory items, 20 requests and 19 usage records are unchanged. Historical requests remain ineligible for new linked utilization.
- No changes were pushed or deployed. Interactive browser/device UAT has not been performed.
- The local SQLite file is tracked by this repository and now contains the migration. Do not publish the local database as a substitute for running the migration on the deployment database.
