# Manuscript alignment text

Use these revisions in the editable manuscript. The original PDF has not been modified.

## Functional requirements: roles

The RGMO Inventory and Resource Management System supports four user roles: Administrator, Staff / RGMO Personnel, Project Manager / Requester, and RGMO Head / Authorized Personnel. Access is enforced through predefined role permissions and record-level authorization on web and API endpoints.

The Administrator manages user accounts, assigns predefined roles, maintains inventory and project records, accesses reports and audit logs, and manages system settings. The Administrator has full operational access for administration and support.

Staff / RGMO Personnel view inventory, submit requests, update or cancel their own pending requests, monitor personal request history, release approved requests for any requester, and record actual utilization of released resources. Staff may inspect approved and completed requests for release duties but cannot approve requests or review other users' pending requests.

Project Managers / Requesters view inventory and the project catalog, submit requests, monitor their own request status and history, and receive personal notifications. They cannot review another user's request, approve or release requests, record utilization, generate reports, access forecasts, or view the audit trail. Project usage visible to them is limited to their own requests and historical usage attributed to them.

The RGMO Head / Authorized Personnel reviews submitted requests, approves or rejects them with remarks, monitors inventory and usage, generates reports, views forecasting results, and receives personal notifications. Inventory/project maintenance, stock release, utilization entry, user administration and audit access remain assigned to their designated roles.

## Assign roles and permissions

The Administrator selects one of the four predefined roles. The system applies that role's configured permissions automatically. Individual permissions are not edited independently through the account form.

## Withdrawal and utilization

Approval authorizes release but does not deduct stock. Staff records the full approved withdrawal, after which the system deducts stock once, stores the release timestamp, and marks the request Completed. Completed denotes a finished release transaction; it does not mean every released resource has been consumed. Released is not a separate request status in the current implementation.

Staff subsequently records utilization against a released request item, including quantity used, date used, activity and remarks. The system prevents cumulative usage from exceeding the released amount and rejects a usage date before release or after the current date. The authenticated user is recorded as the operator, and the action is included in the audit trail. Recording utilization does not deduct warehouse stock again.

Existing historical usage records retain their previous interpretation. New linked utilization records measure actual usage separately from withdrawal quantities.

## UAT adjustments

- Include all four roles consistently in the functional requirements, context/use-case narrative and participant tables.
- UAT-A03: selecting a predefined role applies its permissions; do not describe a custom permission editor.
- UAT-S07: Staff records the full approved release, including releases requested by other users. Stock decreases once.
- UAT-S08: Staff records actual usage against a newly released item; test remaining quantity, date validation and report visibility.
- UAT-S05: describe Pending, Approved, Rejected, Cancelled and Completed; explain that Completed means released.
- Add negative-access checks for each role using the implementation ACL matrix.
