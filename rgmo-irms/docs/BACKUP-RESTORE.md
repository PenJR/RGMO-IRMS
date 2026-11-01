# Backup and restore

## Current testing behavior

An administrator can open **System Backup** and click **Run Backup Now**. The controller runs Spatie's `backup:run`, records success or failure in the audit log, and returns to the page. The page lists successful audit records; it does not currently offer archive downloads or restoration.

This is a full backup of the configured sources, not an incremental backup or a selected-date export. A PostgreSQL dump captures a consistent database snapshot; application files are collected separately, so the ZIP is not an atomic snapshot of both changing files and database records.

The repository currently uses the backup package's default configuration:

- Database: the configured default database connection.
- Files: the application directory, excluding `vendor`, `node_modules`, and paths excluded by the package during backup creation.
- Destination: the `local` filesystem disk, rooted at `storage/app/private`.
- Archive folder: the configured `APP_NAME`.

For `APP_NAME=RGMO-IRMS`, the Render container location is:

```text
/var/www/html/storage/app/private/RGMO-IRMS/<timestamp>.zip
```

The archive contains a database dump and application files. It may contain private configuration and personal data; keep it private and out of Git.

## Testing limitation

The current local-container storage is intentionally retained for testing. Without a persistent disk covering this directory, files can disappear when the hosting instance is replaced or restarted. A successful audit record in Neon can remain after the ZIP is gone. Permanent storage and retention are still part of the deployment migration.

A user-provided hosted screenshot on 2026-11-01 showed a completed backup after the PostgreSQL client fix. This is evidence of a successful run, not a verified restore.

## Commands

Run from the application directory on the environment being checked:

```bash
php artisan backup:list
php artisan schedule:list
```

To create a backup and record the activity through the application's command:

```bash
php artisan app:backup
```

The scheduler registers this command daily at 02:00 in the application timezone. The scheduler must actually be running at that time. Neither a daily schedule nor a success message proves that archives remain available.

## Known failures

| Error | Action |
|---|---|
| `sqlite3: not found` | Install the SQLite command-line tool on the machine running SQLite backups. |
| `aborting because of server version mismatch` | Use `pg_dump` with a major version at least as new as the server. The Docker image installs client 18 for the current PostgreSQL 18 database. |
| Permission denied under `/root/.postgresql` | Check the deployed startup script and PostgreSQL certificate environment paths. |
| Generic backup failure | Inspect the first error and dump stderr in the hosting logs, above the stack trace. |

Do not treat notification delivery or the backup history list as a substitute for checking the ZIP itself.

## Restore rehearsal: PostgreSQL

There is no restore button. An operator should first restore into a separate empty test database, never overwrite the active database as an initial test.

1. Retrieve the ZIP from the server's private storage and inspect its contents.
2. Extract it into a private working directory. Locate the PostgreSQL `.sql` dump; do not assume a filename or flatten the archived directory layout.
3. Provision an empty PostgreSQL test database compatible with the dump. Use client tools compatible with PostgreSQL 18.
4. Configure `PGHOST`, `PGPORT`, `PGDATABASE`, `PGUSER`, and the required TLS settings for the **test target**. Supply credentials through a private password file or prompt.
5. Restore the plain SQL dump, replacing the sample filename:

```bash
psql --set=ON_ERROR_STOP=1 --file=/private/restore/database.sql
```

6. Stop if any error occurs. Ownership or role errors may require deliberate role mapping; do not accept a partial restore as successful.
7. Point an isolated application copy at the restored database. Review archived files and restore required uploads separately. Preserve the appropriate application key; do not blindly overwrite the destination's environment configuration.
8. Verify users, projects, inventory, requests, release quantities, utilization, and attachments. Compare record counts and exercise the role workflows.

A SQLite `.sql` dump must be restored with SQLite into a new database instead; it is not directly interchangeable with PostgreSQL SQL. A database-engine migration needs a separate data migration procedure.

## Before final deployment

Choose durable storage, define retention and archive access, rehearse restoration, and document recovery ownership. See [DEPLOYMENT.md](DEPLOYMENT.md). These steps are planned work, not features already implemented.
