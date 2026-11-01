# Roles and permissions

The system has four predefined roles. Permission definitions live in [`config/rbac.php`](../config/rbac.php); routes, controllers, and services also enforce record ownership and workflow state. Assigning a role selects its predefined permissions, not a custom permission set.

| Capability | Administrator | RGMO Head | Project Manager | Staff |
|---|---|---|---|---|
| View inventory and project catalog | Yes | Yes | Yes | Yes |
| Manage inventory and projects | Yes | No | No | No |
| Submit resource requests | Yes | No | Yes | Yes |
| Edit/cancel own pending requests | Yes | No | No | Yes |
| View requests | All | All | Own | Own plus approved/completed releases |
| Review and approve/reject requests | Yes | Yes | No | No |
| Release approved requests | Yes | No | No | Yes |
| Record actual utilization | Yes | No | No | Yes |
| View project usage | All | All | Own requests and historical usage | Operational usage |
| Reports and inventory exports | Yes | Yes | No | No |
| Forecasts | Yes | Yes | No | No |
| Audit trail, audit exports, module health | Yes | No | No | No |
| Accounts, role assignment, settings, backups | Yes | No | No | No |
| Personal notifications | Yes | Yes | Yes | Yes |

Administrator is the system superuser, subject to account safeguards and workflow validation. Staff can process other users' approved requests, but cannot browse their pending or rejected requests. Project visibility does not grant access to another requester's requests.

## Resource workflow

1. Staff or Project Manager submits a request for a project.
2. RGMO Head or Administrator reviews and approves or rejects it.
3. Staff or Administrator releases an approved request. Fulfillment deducts warehouse stock and marks the request completed.
4. Staff or Administrator records actual usage against eligible released request items.
5. Reports show saved utilization records. Recording usage does not deduct warehouse stock again.

Completed means released, not necessarily fully consumed. Utilization must not exceed the released balance. The server derives the item/project relationship from the request item and the actor from authentication.

Older usage records are preserved. Historical releases are not automatically eligible for the new linked utilization workflow because their old usage may already represent released quantities.

## Verification

Check direct URLs and API calls as well as visible menus. A hidden button alone is not access control. Follow the manual checks in [TESTING.md](TESTING.md).

For detailed implementation decisions, historical migration behavior, and acceptance checks, see [access-control implementation](reviews/access-control-implementation.md). Keep this guide and that record aligned when permissions change.
