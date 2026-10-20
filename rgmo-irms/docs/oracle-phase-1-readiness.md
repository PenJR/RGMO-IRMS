# Oracle migration: Phase 1 assessment

Checked October 17, 2026 (Asia/Manila). Local workspace only. No Oracle or Render
resources were accessed or changed. No application behavior changed.

## Result

The Laravel application is a candidate for an Oracle Linux VM running PHP 8.3,
Nginx, PHP-FPM and SQLite. Server provisioning and Oracle Free-tier availability
are still unverified. Do not switch traffic yet.

## Verified locally

- Laravel 12; project declares PHP ^8.2. PHP 8.3 is the existing Docker baseline.
- Composer production platform requirements pass on local PHP 8.3.6.
- SQLite is the configured database; an application encryption key is present.
- Database-backed sessions and local filesystem storage are configured.
- Database size is approximately 260 KB; vendor approximately 119 MB; built assets
  approximately 640 KB. These are app sizes, not total VM disk requirements.
- No uploaded payload files were found under storage/app/private or storage/app/public.
- Vite production build passes.
- Automated suite passes: 123 tests, 606 assertions, isolated in-memory SQLite.
- Local database backup and archived-database restore check pass integrity and row counts.

## Local backup

Location: storage/app/private/migration-backups/20261017-235431/

The archive contains a consistent SQLite snapshot, local .env, and files present
under the private/public application storage folders. Its manifest records SHA-256,
row counts and scope. Directory is private and ignored by Git. The .env contains
secrets; keep the archive private and copy it to a separate safe location.

Snapshot counts (including any soft-deleted rows):

| Table | Rows |
| --- | ---: |
| users | 5 |
| inventory_items | 37 |
| projects | 4 |
| resource_requests | 18 |

This is local data, not confirmation of the current Render database. Render-only
records/uploads still need an export or backup before cutover. Missing data cannot
be reconstructed from this local snapshot unless it was already present locally.
Archive restore validation reopened the archived database and checked integrity and
counts; a complete server/application restore has not been exercised.

## Requirements for the server phase

- PHP 8.3 CLI/FPM with pdo_sqlite, sqlite3, mbstring, XML/DOM, GD, ZIP, intl, bcmath,
  fileinfo, curl/OpenSSL support, and OPcache; pcntl for queue process handling.
  Re-run Composer platform requirements on the actual server.
- Nginx, Composer 2, Git, SQLite CLI, zip/unzip; Node/npm only if building assets
  on the VM (prebuilt deployment assets are another option).
- The current Nginx configuration assumes /var/www/html/public and PHP-FPM TCP
  port 9000. Adapt these if using native services and a different release folder.
- Keep SQLite and uploads in shared persistent directories outside release code;
  grant the PHP process write access to the database directory as well as its file.
- Persist the application key. Use production HTTPS settings and an appropriate
  APP_URL; AppServiceProvider forces HTTPS in production.
- Link public/storage to the persistent public upload directory.
- Run the queue worker and scheduler as managed processes (the Docker deployment
  currently uses Supervisor for both).
- Backup commands exist via spatie/laravel-backup, but no published config/backup.php
  was found. Configure and restore-test backup storage before relying on that UI.
- Establish HTTPS, inbound firewall rules, independent backups, and persistence
  verification in their planned phases.

## Outstanding before cutover

Oracle account/Always Free capacity, real server package compatibility, Render-only
data export, actual browser/network checks, full restore, and restart/update
persistence checks. Phase 2 is resource creation only, after the user selects it.
