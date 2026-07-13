# RGMO-IRMS Deployment Readiness Report

**Assessment date:** 2026-07-12  
**Scope:** Laravel application deployment and migration from SQLite to PostgreSQL  
**Decision:** **NO-GO** until all P0 exit criteria below are met.

## Executive summary

The application has a healthy SQLite baseline: all 60 automated tests pass (264 assertions), all 14 migrations rebuild successfully, the current export imports into SQLite with no foreign-key violations, and the frontend production build succeeds. The Laravel schema and the raw queries inspected are generally portable to PostgreSQL.

The release is not ready for production because PostgreSQL has not been installed or exercised in the current deployment environment, the checked-in data export is SQLite-specific, and there is no tested conversion, reconciliation, cutover, or rollback procedure. The backup drill also fails. A credential that appears real is committed in `.env.example` and has existed since the repository's initial commit. These are release blockers.

## Current deployment plan (inferred from the repository)

No project-specific deployment plan or runbook is present. The default Laravel README remains unchanged. The effective plan currently encoded by the repository is:

1. Install PHP/Composer and Node dependencies.
2. Copy `.env.example`, generate `APP_KEY`, and run `php artisan migrate --force`.
3. Build frontend assets with `npm run build`.
4. Use database-backed sessions, cache, and queues.
5. Run a queue worker and Laravel scheduler; the scheduler invokes daily backup and low-stock commands.
6. Change `DB_CONNECTION` from its SQLite default to `pgsql` and supply PostgreSQL connection values.

This describes application setup but does not describe data conversion, validation, traffic cutover, rollback, infrastructure ownership, monitoring, or recovery objectives.

## Evidence collected

| Check | Result | Meaning |
|---|---:|---|
| Automated tests on SQLite | 60 passed / 264 assertions | Good functional baseline, but not evidence of PostgreSQL compatibility |
| Fresh SQLite migration | 14/14 migrations passed | Migration chain is internally consistent on SQLite |
| SQLite export load | 20 application tables loaded | Export is structurally readable by SQLite |
| Export FK check | 0 violations | No detected orphaned foreign keys in the supplied snapshot |
| Export sample counts | 5 users, 37 inventory items, 219 transactions, 18 requests | Useful reconciliation baseline for this snapshot |
| Frontend production build | Passed | Vite assets compile successfully |
| PHP PostgreSQL driver | Missing (`pdo_pgsql`) | Application cannot connect to PostgreSQL here |
| PostgreSQL client/dump tools | Not demonstrated | Migration and recovery cannot be rehearsed here |
| Database backup drill | Failed (exit 127; `sqlite3` missing) | Current recovery control is not operational |
| PostgreSQL test suite | Not configured | CI only runs SQLite `:memory:` |

## Prioritized risks and exit criteria

### P0 — launch blockers

1. **Committed mail credential**
   - `.env.example` contains a Gmail username and app-password-like value, and Git history shows it from the initial commit.
   - Impact: account compromise and unauthorized email access/use.
   - Exit: revoke/rotate the credential immediately; replace example values with blanks; remove the secret from Git history where policy permits; scan the full repository/history and deployment environment; document the incident and verify the old credential no longer works.

2. **No executable SQLite-to-PostgreSQL data migration**
   - The supplied `.sql` file uses `PRAGMA`, `AUTOINCREMENT`, SQLite types, quoted string IDs, and SQLite transaction syntax. It is not a `psql` restore artifact.
   - Exit: select and version a conversion method (for example pgloader or a purpose-built ETL); load into a clean PostgreSQL staging database; preserve primary keys; reset every PostgreSQL sequence; validate timestamps, booleans, JSON, numeric precision, nulls, unique constraints, and foreign keys.

3. **PostgreSQL compatibility is untested**
   - The runtime lacks `pdo_pgsql`; `phpunit.xml` forces SQLite in-memory; no PostgreSQL CI job exists.
   - Exit: install `pdo_pgsql`; run `migrate:fresh` and the full test suite against the target PostgreSQL major version; add this as a required CI check. Exercise concurrent request approval/stock deduction because SQLite tests do not model PostgreSQL locking and concurrency behavior.

4. **Backup and restore are not proven**
   - `backup:run --only-db` failed because a required dump binary was absent. PostgreSQL will require compatible `pg_dump`/`pg_restore` tools and access rights.
   - Exit: configure encrypted, off-host backups; complete a PostgreSQL backup **and restore** drill; record restore duration and data checks; set approved RPO/RTO; alert when scheduled backup or restore verification fails.

5. **No cutover or rollback runbook**
   - No write-freeze, final delta handling, DNS/proxy action, acceptance test, decision owner, or rollback threshold is defined.
   - Exit: rehearse a timestamped runbook twice using a production-like snapshot; define maximum downtime, owners, communications, go/no-go authority, rollback trigger, and how writes made after cutover are handled. Never roll the app back to an older SQLite snapshot after accepting PostgreSQL writes without an explicit reverse-sync plan.

### P1 — resolve before production approval

6. **Production configuration is not hardened or documented**
   - Defaults/examples use `APP_ENV=local`, `APP_DEBUG=true`, `APP_URL=http://localhost`, SQLite, and PostgreSQL `sslmode=prefer`.
   - Exit: use environment/secret-manager values with `APP_ENV=production`, `APP_DEBUG=false`, the canonical HTTPS URL, `DB_CONNECTION=pgsql`, least-privilege DB roles, and TLS verification appropriate to the hosting provider (normally `verify-full` with a trusted CA). Run `config:cache`, `route:cache`, and `view:cache` during release.

7. **Queue, scheduler, and database-backed infrastructure lack an operations plan**
   - Sessions, cache, jobs, and application records share the primary database. Six queued jobs and two sessions exist in the supplied snapshot.
   - Exit: decide whether ephemeral cache/session/job records migrate; usually exclude or drain them. Supervise queue workers, run `queue:restart` after deployment, run exactly one scheduler trigger, configure failed-job alerting, and verify scheduled backups/alerts.

8. **Health endpoint is authenticated and application-focused**
   - Existing module-health routes require authentication/permissions and do not constitute an infrastructure readiness probe.
   - Exit: add or configure a non-sensitive liveness/readiness check that verifies the deployed build and database connectivity; connect it to load balancer and alerting. Monitor HTTP errors, latency, DB connections/locks, queue depth/failures, disk, and backup age.

9. **Data governance for the checked-in export is unresolved**
   - The export includes password hashes, email addresses, IP addresses, sessions, audit/login history, and operational data.
   - Exit: classify whether this is real or synthetic data; remove production data from source control; restrict and encrypt migration artifacts; define retention and secure deletion; force credential/session invalidation if exposure warrants it.

10. **Schema constraints need production review**
    - Several foreign keys intentionally omit delete behavior; PostgreSQL will enforce them consistently. JSON columns become native PostgreSQL JSON, while imported dump values currently appear as SQLite text.
    - Exit: inspect generated PostgreSQL DDL, validate every FK/delete rule and index, confirm accepted role/status values, and run `ANALYZE` after load. Add indexes based on measured staging queries rather than assumptions.

### P2 — early post-launch improvements

- Replace the generic README with environment setup, release, recovery, and ownership documentation.
- Add dependency/security scanning and secret scanning as required CI gates.
- Establish performance baselines and a slow-query review using production-like volume.
- Define retention/archival for audit logs, login histories, notifications, jobs, cache, and sessions.
- Test failover and disaster recovery on a recurring schedule.

## Recommended migration and launch sequence

1. **Contain security exposure:** rotate the mail credential, sanitize configuration/history, and complete a secret scan.
2. **Build the target:** provision the approved PostgreSQL version with TLS, backups, monitoring, and separate owner/runtime roles. Install `pdo_pgsql` and PostgreSQL client tools in application/worker images as appropriate.
3. **Prove schema compatibility:** run Laravel migrations from empty against PostgreSQL; run the full suite in PostgreSQL CI.
4. **Build a repeatable data loader:** take a consistent SQLite snapshot, exclude or explicitly handle ephemeral tables, convert/load data, and reset identity sequences.
5. **Reconcile:** compare table counts, PK min/max, null/unique/FK checks, financial/inventory aggregates, request statuses, and sampled records. At minimum reconcile `SUM(stock)`, transaction quantities by type, request/item totals, and inventory value totals.
6. **Rehearse operations:** test backup/restore, queue/scheduler, email, file storage, authentication/2FA, reports/PDF/Excel, and key role workflows. Measure migration and restore duration.
7. **Execute cutover:** announce maintenance, drain queues, enable maintenance/write freeze, take final SQLite snapshot, run the versioned loader, reconcile, deploy cached production config, start workers/scheduler, run smoke tests, then reopen traffic.
8. **Observe:** hold the launch bridge through the agreed stabilization window; monitor errors, latency, locks, connection saturation, queue failures, and business reconciliation totals.

## Hosting deployment runbook

This runbook is provider-neutral and assumes a Linux VPS or managed PHP host. Replace example paths, users, domain names, and service names with values approved for the selected hosting provider. Complete it first in staging, then repeat the tested procedure in production.

### 1. Minimum hosting requirements

- Linux hosting with SSH or an equivalent controlled deployment mechanism
- Nginx or Apache with the document root set to the Laravel `public/` directory
- PHP 8.2 or newer with CLI and FPM plus PDO PostgreSQL, OpenSSL, Mbstring, XML, Ctype, JSON, Fileinfo, Tokenizer, GD, ZIP, and required Composer extensions
- Composer 2 and Node.js/npm available during build, or CI-built `vendor/` and `public/build/` artifacts deployed without development dependencies
- PostgreSQL on a supported managed service or protected private network
- A process supervisor for `queue:work` and cron access for Laravel's scheduler
- HTTPS certificate, DNS control, outbound SMTP access, persistent storage, centralized logs, monitoring, and off-host backups

Shared hosting is acceptable only if it provides PHP 8.2+, `pdo_pgsql`, PostgreSQL access, cron, the ability to point the domain to `public/`, and a reliable way to run queue jobs. If persistent workers are prohibited, `queue:work --stop-when-empty` may be scheduled frequently as a compromise, but a supervised worker on a VPS or managed Laravel platform is preferred.

### 2. Suggested directory layout

Use atomic releases where the host permits symbolic links:

```text
/var/www/rgmo-irms/
├── current -> releases/20260712-120000
├── releases/
│   └── 20260712-120000/
├── shared/
│   ├── .env
│   └── storage/
└── backups/
```

The web-server document root must be `/var/www/rgmo-irms/current/public`. Never expose the repository root, `.env`, `database/`, `storage/`, or migration artifacts through the web server.

### 3. Provision the hosting environment

1. Create a dedicated non-root deployment/application account and disable password-based SSH where possible.
2. Restrict inbound traffic to HTTPS/HTTP and administration sources. Keep PostgreSQL private and allow only the application and operations networks.
3. Create separate PostgreSQL roles for schema migration and runtime access. Do not use the database owner or a superuser as the application account.
4. Configure DNS with a reduced TTL before cutover. Obtain the TLS certificate before opening production traffic.
5. Create persistent `storage/` directories and ensure only the application user and web-server group can write to `storage/` and `bootstrap/cache/`.
6. Install compatible PostgreSQL client tools so database backup and restore commands can run.

### 4. Production environment values

Store these in the host's secret manager or the shared `.env`; never commit the production file:

```dotenv
APP_NAME="RGMO-IRMS"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://irms.example.gov.ph

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=<private-postgresql-host>
DB_PORT=5432
DB_DATABASE=rgmo_irms
DB_USERNAME=<least-privilege-runtime-user>
DB_PASSWORD=<secret-manager-value>
DB_SSLMODE=verify-full

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database

FILESYSTEM_DISK=local
MAIL_MAILER=smtp
MAIL_HOST=<approved-smtp-host>
MAIL_PORT=587
MAIL_USERNAME=<secret-manager-value>
MAIL_PASSWORD=<secret-manager-value>
MAIL_SCHEME=tls
MAIL_FROM_ADDRESS=<approved-sender>
MAIL_FROM_NAME="${APP_NAME}"
```

Generate `APP_KEY` once with `php artisan key:generate --show`, store it securely, and keep the same key across releases. Rotating or losing it can invalidate encrypted application data. Confirm the provider-specific PostgreSQL CA configuration required by `verify-full`; do not weaken TLS merely to make the first connection succeed.

### 5. Build and deploy a release

Run the following from the new release directory. The data-conversion step described earlier must be completed separately during the approved migration window.

```bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan optimize:clear
php artisan storage:link
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

Before `migrate --force`, take a verified database backup and inspect `php artisan migrate:status`. If CI produces the release artifact, build dependencies should remain in CI and only the resulting application files, production Composer dependencies, and `public/build/` assets should reach the host.

After the release is ready, switch the `current` symlink atomically, reload PHP-FPM if required, and run `php artisan queue:restart`. Keep at least one previously known-good application release for code rollback. A code rollback does not automatically reverse database migrations or PostgreSQL writes.

### 6. Web server, queue, and scheduler

- Configure Nginx/Apache to serve `public/index.php`, deny hidden/sensitive files, enforce HTTPS, and pass only PHP requests to PHP-FPM.
- Run a supervised worker similar to `php artisan queue:work --sleep=3 --tries=3 --timeout=120 --max-time=3600`; adjust timeout and memory from measured workloads. Configure automatic restart and failure alerts.
- Add exactly one cron entry on one host:

```cron
* * * * * cd /var/www/rgmo-irms/current && php artisan schedule:run >> /dev/null 2>&1
```

- Ensure scheduler and worker commands run as the application user and use the same production environment as the web process.
- If user-uploaded/public files exist, keep `storage/app/public` persistent and run `storage:link`. For multiple web nodes, use shared object storage instead of node-local files.

### 7. Hosting cutover

1. Confirm the staging sign-off, hosting checklist, backup/restore evidence, and rollback decision window.
2. Enable maintenance mode with a secret bypass for the launch team: `php artisan down --secret=<random-one-time-token>`.
3. Stop or drain queue workers, take the final SQLite snapshot, run the tested conversion, reset PostgreSQL sequences, and complete reconciliation.
4. Deploy the production release and cached configuration, restart workers, verify the single scheduler, and run smoke tests through the public HTTPS domain.
5. Update DNS or load-balancer routing only after technical and data-owner approval.
6. Reopen the application with `php artisan up` and maintain heightened monitoring through the stabilization window.

### 8. Hosting rollback boundaries

- Before PostgreSQL accepts production writes, traffic may return to the previous application/SQLite environment using the documented DNS or load-balancer rollback.
- After PostgreSQL accepts writes, do not restore the old SQLite database because new records would be lost. Prefer fixing forward or switching application code back while continuing to use PostgreSQL, provided the schema remains compatible.
- If a migration changes schema incompatibly, use the specifically rehearsed database rollback/restore plan and obtain the data owner's authorization before discarding any writes.

### 9. Hosting acceptance checks

- Public domain resolves correctly and redirects HTTP to HTTPS
- TLS certificate, chain, hostname, and renewal work
- Home/login pages return successfully with debug output disabled
- Application can connect to PostgreSQL using the runtime role and cannot perform owner-only operations
- Login, logout, session persistence, password reset/email, 2FA, role permissions, inventory, requests, reports, exports, and uploads work
- Queue jobs are processed and scheduled commands run once
- Logs are collected without exposed secrets; alerting receives a controlled test event
- Database and application-file backups complete, and the documented restore has already been proven
- Rebooting or replacing the host automatically restores PHP-FPM, web server, worker, scheduler, mounts, and monitoring

## Domain and DNS deployment plan

**Proposed production hostname:** `irms.example.gov.ph` (**placeholder—replace with the officially approved RGMO-IRMS domain before deployment**)  
**Canonical application URL:** `https://irms.example.gov.ph`

Do not publish DNS until ownership of the final domain, hosting destination, TLS certificate, and production readiness have been confirmed. Record the domain registrar/DNS provider, account owner, technical contact, renewal owner, and emergency access procedure.

### Required DNS records

Use the hosting provider's exact destination values. A typical configuration is:

| Name | Type | Destination | Purpose |
|---|---|---|---|
| `irms` | `A` | `<production IPv4 address>` | Sends the application hostname to the web server |
| `irms` | `AAAA` | `<production IPv6 address>` | Optional; publish only when IPv6 is configured and tested |
| `www.irms` | `CNAME` | `irms.example.gov.ph` | Optional alias; redirect it to the canonical hostname |
| `@` or provider-specified name | `TXT` | `<domain verification value>` | Hosting or certificate ownership verification when required |
| provider-specified name | `CAA` | `<approved certificate authority>` | Optional restriction on certificate issuance |

For a managed platform, use its provided `CNAME` target instead of an `A` record when instructed. Do not publish both conflicting targets. The database hostname must remain private and must not be exposed as a public application DNS record.

### Domain cutover procedure

1. Confirm the official hostname and verify that the organization controls its DNS account.
2. At least 24–48 hours before launch, reduce the existing record TTL to approximately 300 seconds. This helps only after prior cached TTLs expire.
3. Add the domain to the hosting platform and configure the virtual host with the document root set to `current/public`.
4. Issue and install a TLS certificate covering the canonical hostname and any supported alias. Verify the full certificate chain, hostname, expiration, and automatic renewal.
5. Set `APP_URL=https://irms.example.gov.ph`, set `SESSION_SECURE_COOKIE=true`, and ensure proxy/forwarded-header settings preserve HTTPS when a load balancer or CDN terminates TLS.
6. Test the server before public DNS cutover using a staging hostname or a controlled local hosts-file entry. Do not bypass certificate validation in production testing.
7. After application, database, and reconciliation approval, change the DNS record to the production hosting destination.
8. Verify resolution using at least two independent resolvers, then run the public-domain acceptance checks below.
9. Keep the old destination available and unchanged during the agreed rollback window. Increase the TTL after the stabilization period.

### Domain behavior and security requirements

- Redirect all `http://` traffic to HTTPS using a permanent redirect after testing.
- Select exactly one canonical hostname and redirect all aliases to it; avoid duplicate sessions and inconsistent generated URLs.
- Never serve the Laravel repository root. Requests for `.env`, `.git`, `database`, `storage/logs`, Composer files, and SQL exports must be denied or return `404`.
- Enable HSTS only after HTTPS works correctly on all intended subdomains. Start with a conservative duration before considering `includeSubDomains` or preload.
- Use secure, HTTP-only cookies with an appropriate SameSite setting. Set `SESSION_DOMAIN` only if sessions genuinely need to be shared across subdomains.
- Configure application-generated links, password resets, email verification, assets, public storage URLs, and callback URLs to use the canonical HTTPS hostname.
- Protect DNS and registrar accounts with MFA, least privilege, change alerts, registrar lock where applicable, and documented recovery contacts.

### Email-domain records

If RGMO-IRMS sends mail using the official domain, configure and validate SPF, DKIM, and DMARC with the approved mail provider. Do not guess these values or publish multiple conflicting SPF records. Use a dedicated application sender such as `no-reply@example.gov.ph`, monitor delivery failures, and keep SMTP credentials in the hosting secret manager.

### Public-domain acceptance checks

- [ ] Official production hostname and canonical URL approved and substituted for every placeholder
- [ ] Registrar, DNS provider, domain owner, renewal date, and emergency contacts documented
- [ ] `A`, `AAAA`, or `CNAME` resolves only to the approved production destination
- [ ] IPv4 and IPv6 both tested when `AAAA` is published
- [ ] HTTPS certificate chain, hostname, expiration, and automatic renewal pass
- [ ] HTTP and alternate hostnames redirect once to the canonical HTTPS URL without loops
- [ ] Login/session cookies remain secure and authentication survives normal navigation
- [ ] Password-reset, email-verification, notification, asset, export, and public-storage links use the canonical domain
- [ ] Sensitive paths and files are inaccessible from the internet
- [ ] SPF, DKIM, and DMARC pass when using an official-domain sender
- [ ] DNS change monitoring, MFA, registrar lock, and renewal notifications are active
- [ ] DNS rollback destination and decision deadline are recorded

## Final launch checklist

### Security and approvals

- [ ] Exposed mail credential revoked; replacement stored only in an approved secret manager
- [ ] Repository and Git history secret scan clean or exceptions formally accepted
- [ ] Migration data classified, encrypted, access-controlled, and given a deletion date
- [ ] Release, security, data-owner, and operations approvals recorded
- [ ] Go/no-go owner and rollback decision owner named

### PostgreSQL and application

- [ ] Target PostgreSQL version, extensions, timezone, locale, and connection limits recorded
- [ ] TLS certificate verification and least-privilege application/migration roles verified
- [ ] `pdo_pgsql`, `pg_dump`, `pg_restore`, and required runtime extensions present
- [ ] Fresh PostgreSQL migrations pass
- [ ] Full PostgreSQL test suite passes as a required CI check
- [ ] Production asset build and Laravel config/route/view caches succeed
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, canonical HTTPS URL, and trusted proxy settings verified

### Data migration

- [ ] Versioned conversion command/script reviewed and checksum recorded
- [ ] Final SQLite source snapshot is consistent, encrypted, and restorable
- [ ] Ephemeral table policy decided (sessions, cache, jobs, password-reset tokens)
- [ ] All tables loaded; sequences reset above current maximum IDs
- [ ] Row counts, PK ranges, FK checks, unique constraints, nulls, JSON, booleans, timestamps, and numeric precision reconcile
- [ ] Inventory, transaction, request, and reporting aggregates signed off by data owner
- [ ] Migration duration fits the approved outage window

### Operations and recovery

- [ ] PostgreSQL backup succeeds and a clean restore is verified
- [ ] RPO/RTO and backup retention approved; backup-age/failure alerts active
- [ ] Queue drained before cutover; supervised workers restarted and healthy afterward
- [ ] Exactly one scheduler trigger active; scheduled commands verified
- [ ] Liveness/readiness probes, logs, metrics, and alerts verified
- [ ] Capacity, connection pool/limits, disk, and log rotation checked

### Hosting

- [ ] Hosting provider, region, support contacts, maintenance windows, and service owners recorded
- [ ] Production domain points only to the Laravel `public/` directory
- [ ] PHP 8.2+, required extensions, Composer runtime dependencies, and PostgreSQL tools verified
- [ ] Production `.env`/secrets installed with secure permissions; no secrets included in release artifacts
- [ ] DNS, HTTPS redirect, TLS chain, hostname verification, and automatic renewal tested
- [ ] Firewall/private-network rules and least-privilege OS/database accounts verified
- [ ] `storage/` persistence, permissions, public storage link, and multi-node storage strategy verified
- [ ] Web server, PHP-FPM, queue supervisor, and exactly one scheduler survive reboot
- [ ] Atomic release and known-good code rollback tested
- [ ] Public-domain smoke tests and controlled monitoring alert pass

### Domain and DNS

- [ ] Official RGMO-IRMS domain replaces `irms.example.gov.ph` throughout configuration and documentation
- [ ] DNS/registrar ownership, MFA, renewal responsibility, and emergency access verified
- [ ] Production DNS destination, TTL, and rollback records reviewed by a second operator
- [ ] TLS issuance, renewal, HTTPS enforcement, and canonical-host redirects verified
- [ ] Laravel `APP_URL`, secure-cookie, trusted-proxy, email-link, asset, and storage URL behavior verified
- [ ] Public access to `.env`, repository metadata, logs, database exports, and non-`public/` directories is blocked
- [ ] Official sender-domain SPF, DKIM, and DMARC verified when applicable

### Cutover and validation

- [ ] Timestamped runbook rehearsed twice with named operators
- [ ] Maintenance/write freeze and stakeholder communications ready
- [ ] Rollback trigger, deadline, and post-cutover-write policy documented
- [ ] Authentication, authorization by role, 2FA, inventory, request approval/fulfillment, projects, notifications, reports/exports, email, and file storage smoke tests pass
- [ ] HTTP error rate, latency, DB locks/connections, and queue depth stable through the observation window
- [ ] Business owner signs final reconciliation and authorizes traffic reopening
- [ ] Temporary migration artifacts securely deleted after the approved retention period

## Go decision rule

Launch only when every P0 item and every unchecked item in the Security, PostgreSQL, Data Migration, and Recovery sections has objective evidence and an accountable sign-off. P1 exceptions require a written risk acceptance, owner, and due date. At the time of this review, the correct decision is **NO-GO**.
