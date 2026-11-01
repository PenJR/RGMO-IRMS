# Deployment and migration

The current hosted environment is for testing on Render with Neon PostgreSQL. Permanent file and backup storage will be addressed during the deployment migration. This guide is an operational checklist, not a statement that every production check has passed.

## Existing detailed guides

- [Render and Neon setup](render-neon-setup.md)
- [Render to AWS migration](render-to-aws-migration-guide.md)
- [Oracle readiness](oracle-phase-1-readiness.md) and [server setup](oracle-phase-2-server.md)
- [Deployment readiness report](deployment-readiness-report.md)

Use the guide for the chosen destination. Older reports describe their own snapshots; current code and verified environment settings take precedence.

## Application configuration

The Laravel app and Dockerfile are in `rgmo-irms/`. Supply secrets through the hosting environment, not committed files.

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_NAME=RGMO-IRMS
APP_TIMEZONE=Asia/Manila
APP_URL=https://YOUR_HOST
ASSET_URL=https://YOUR_HOST
DB_CONNECTION=pgsql
DB_SSLMODE=require
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database
ALLOW_EMPTY_SQLITE=false
BOOTSTRAP_ADMIN=false
```

Set the existing `APP_KEY` and the provider-issued `DB_URL` privately. Preserve the application key during migration. Configure SMTP credentials and optional forecasting credentials separately. Do not point local test commands at the production database.

For a genuinely empty installation, follow the bootstrap administrator steps in the Render guide. Disable bootstrap and remove its password after first login. Changing `DB_URL` does not transfer existing records.

## Container behavior

The Docker build installs PHP extensions, Composer dependencies, frontend assets, SQLite tools, and PostgreSQL client 18 from the PostgreSQL package repository. It checks that `pg_dump` reports major version 18.

`docker/start.sh` validates required configuration, runs migrations with retries, optionally bootstraps an administrator, builds Laravel caches, and starts Supervisor. Supervisor runs Nginx, PHP-FPM, the queue worker, and the scheduler.

The startup script supplies explicit PostgreSQL client-certificate lookup paths to avoid inherited `/root/.postgresql` permission failures. Custom `PGSSLCERT` and `PGSSLKEY` values are preserved. Do not disable TLS to work around certificate errors.

## Scheduled tasks

Times use `APP_TIMEZONE` (the example configuration uses Asia/Manila):

| Task | Schedule |
|---|---|
| `app:backup` | Daily at 02:00 |
| `app:low-stock-alert` | Daily at 00:00 |
| `model:prune --no-interaction` | Daily at 03:00 |

Confirm registrations with `php artisan schedule:list`. Registration alone does not prove execution. Run one scheduler per deployment; avoid adding a duplicate cron scheduler beside the container's scheduler.

## Release checks

1. Run the checks in [TESTING.md](TESTING.md) before publishing changes.
2. Take an accessible backup and rehearse restoration in a separate database.
3. Push the reviewed commit and deploy that commit on the chosen host.
4. Check build logs for PostgreSQL client 18 and successful asset compilation.
5. Check startup logs for successful migrations and healthy web, queue, and scheduler processes.
6. Open `/up`, then `/login`; verify each role's dashboard and permitted workflow.
7. Verify database writes, exports, mail delivery, and a new backup on the hosted service.
8. Record the commit, environment, date, and observed results.

A health response alone does not prove the whole application works. A successful local build does not prove the deployed release works.

## Final migration

Transfer database records and uploaded files separately, preserve required secrets, and compare important record counts after migration. Configure durable backup storage, retention, and restore procedures before relying on the final deployment. Keep the old system available until the new system has been verified, and prevent simultaneous writes during cutover.

If a release fails, identify whether code, configuration, or schema changed before choosing rollback. Rolling back code does not automatically reverse a database migration.

See [backup and restore](BACKUP-RESTORE.md) for the current storage limitation and restore rehearsal.
