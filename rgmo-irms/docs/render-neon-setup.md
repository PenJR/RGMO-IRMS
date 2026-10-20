# Render Free + Neon PostgreSQL

Keep the website on Render Free and store accounts, inventory, projects, sessions,
requests, and other database records in Neon PostgreSQL.

## Before switching

Export any inventory still accessible and retain original import spreadsheets.
Changing the database connection does not copy existing SQLite records. If existing
records need to be preserved, back up the live SQLite database and migrate it before
switching; do not initialize an empty replacement and expect old data to appear.
Keep the existing Render APP_KEY unchanged.

## Create the database

1. Create an account at https://console.neon.tech and choose the Free plan.
2. Create a project named `rgmo-irms`. Choose a region close to the Render service
   (Singapore in the current dashboard), if available.
3. Click **Connect** and copy the PostgreSQL connection URI. For this small app,
   use the direct connection initially, since startup also runs schema migrations.
   Copy only the URI, not a `psql` command or surrounding quotes.
4. Keep the URI private: it contains the database password.

## Configure Render

In the existing RGMO_IRMS web service, open **Environment** and set:

```dotenv
DB_CONNECTION=pgsql
DB_URL=postgresql://USER:PASSWORD@HOST/DATABASE?sslmode=require
DB_SSLMODE=require
SESSION_DRIVER=database
CACHE_STORE=database
ALLOW_EMPTY_SQLITE=false
```

Replace the whole DB_URL value with the URI from Neon, retaining its SSL parameters.
Do not manually substitute unescaped passwords into a URI; use the provider's URI.
Remove obsolete DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD,
SESSION_CONNECTION and DB_CACHE_CONNECTION overrides if they point to SQLite or an
old database. Keep APP_KEY and the correct HTTPS APP_URL unchanged.

For a brand-new database, also set:

```dotenv
BOOTSTRAP_ADMIN=true
BOOTSTRAP_ADMIN_EMAIL=YOUR_EMAIL
BOOTSTRAP_ADMIN_PASSWORD=YOUR_STRONG_PASSWORD
BOOTSTRAP_ADMIN_NAME=RGMO Administrator
```

Use at least 10 characters including uppercase, lowercase, a number and a symbol.
Ensure the BootstrapAdminSeeder and its startup hook are committed and deployed.
Do not run DatabaseSeeder for recovery: it inserts demo accounts and demo records.

Save the environment and deploy the latest commit. The existing startup script runs
pending migrations, optionally creates the first admin if there are no users, and
rebuilds Laravel caches. A connection failure stops startup instead of falling back
to SQLite.

## Verify

1. Check Render logs for successful migrations and PHP-FPM/Nginx startup.
2. Open a fresh `/login` page and sign in using the bootstrap credentials.
3. Set BOOTSTRAP_ADMIN=false and delete BOOTSTRAP_ADMIN_PASSWORD in Render.
4. Create a clearly named test project and import the sample inventory spreadsheet.
5. After a subsequent Render restart/deploy, verify both records still exist.
6. Keep regular database exports/backups independently of the running web service.

This repository's local development database stays SQLite. Do not replace the local
.env unless you intentionally want local commands to access the hosted database.

## Boundaries

- This configuration has not connected to Neon until real credentials are entered
  and the deployment verification above succeeds.
- Render Free still sleeps; PostgreSQL persistence does not remove that wake-up delay.
- Imported inventory rows are database records. Uploaded attachments, images, and
  files under Laravel storage remain separate and need external object storage.
- A new PostgreSQL database does not recover previously deleted SQLite data.
- Keep within Neon's current Free-plan limits and maintain your own backups.

Sources:
- https://neon.com/docs/guides/laravel
- https://neon.com/docs/get-started/connect-neon
- https://render.com/docs/free
