# Security monitoring for the hosting administrator

RGMO-IRMS sends safe, server-side security events to the application's audit trail and, when enabled, to a remote syslog collector. It does not record keystrokes, passwords, request bodies, uploaded-file contents, or other typed private text.

## Events collected

- successful and failed login attempts
- denied access to a protected page or API endpoint
- authenticated write operations, including their route, HTTP method, result status, user, and source IP address
- request, approval, withdrawal, inventory, procurement, utilization, and user-management actions already recorded in the audit trail
- server/application errors through the normal Laravel log channel

Each forwarded security event contains its time, event name, authenticated user ID and role when available, source IP address, HTTP method, route/path, and a truncated user agent. It excludes credentials and request input.

## Hosting setup

The hosting administrator must provide a syslog collector reachable from the application host. Add these environment variables in Render, then deploy:

```dotenv
SECURITY_SYSLOG_ENABLED=true
SECURITY_SYSLOG_HOST=collector.example.gov.ph
SECURITY_SYSLOG_PORT=514
SECURITY_SYSLOG_IDENT=rgmo-irms-production
SECURITY_SYSLOG_LEVEL=notice
```

Port `514` uses UDP, matching the collector example supplied by the hosting team. UDP has no delivery or authenticity guarantee. If the collector supports encrypted TCP/TLS syslog, place a trusted relay beside the application or provide a supported secure log-ingestion endpoint before treating forwarded logs as forensic evidence.

If forwarding fails, the application continues running and records the forwarding failure in its normal Laravel logs. The administrator should confirm receipt by attempting one deliberately invalid login and one access-denied request, then checking the collector for `event=login_failed` and `event=access_denied`.

## Access and retention

Only the Administrator role can view the in-application audit trail. The hosting administrator should protect collector access, restrict it to authorized incident responders, set the organization-approved retention period, and synchronize collector time with NTP.
