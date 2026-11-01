# Local setup

Run these commands from the Laravel application directory (`rgmo-irms/`). This guide uses a local SQLite database; the hosted testing service uses PostgreSQL.

## Requirements

- PHP 8.2+ and Composer 2; the Docker image uses PHP 8.3.
- PHP extensions required by Composer, including PDO SQLite, mbstring, XML, ZIP, and GD. Run `composer check-platform-reqs` after installation.
- Node.js 20.19+ within the 20.x line, or 22.12+; npm. These versions match the installed Vite 7 requirements.
- The `sqlite3` command-line tool for SQLite backups. PHP's SQLite extension alone is not enough.
- PostgreSQL client 18 for backing up the current PostgreSQL 18 hosted database.

## Install

```bash
composer install
npm ci
```

For a new checkout only, copy `.env.example` to `.env`. Preserve an existing `.env` and application key.

```bash
cp .env.example .env
php artisan key:generate
```

Use these local values in `.env`:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=sqlite
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
MAIL_MAILER=log
```

Leave `DB_URL` unset. Remove old database overrides before using SQLite. With no `DB_DATABASE` override, the database path is `database/database.sqlite`.

```bash
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan migrate
npm run build
```

Do not run `migrate:fresh` against data you need to keep. The default `DatabaseSeeder` adds demo accounts and records; it is not a production initialization procedure.

## First administrator

For an empty users table, set `BOOTSTRAP_ADMIN_EMAIL`, `BOOTSTRAP_ADMIN_NAME`, and `BOOTSTRAP_ADMIN_PASSWORD` in your private `.env`. Use a password of at least 10 characters with uppercase, lowercase, numbers, and symbols.

```bash
php artisan config:clear
php artisan db:seed --class=BootstrapAdminSeeder
```

This seeder does nothing when users already exist. Remove the bootstrap password after signing in. The `BOOTSTRAP_ADMIN=true` switch is for the Docker startup hook; an explicit seeder command does not need that switch.

## Run

```bash
composer run dev
```

Open **http://127.0.0.1:8000/login**. This command runs the development server, queue listener, logs, and Vite. In a separate terminal, start scheduled tasks only when you intend to run local maintenance:

```bash
php artisan schedule:work
```

Successful login opens the dashboard. Create the other role accounts through administrator user management.

## Troubleshooting

- Missing styles: run `npm run dev` with the app, or `npm run build` for built assets.
- Changed `.env` values not applied: run `php artisan config:clear` and restart long-running processes.
- Backup reports `sqlite3: not found`: install the SQLite CLI for your OS.
- Email is not delivered with `MAIL_MAILER=log`: this local setting writes mail to logs; configure SMTP separately for delivery testing.

Next: [testing](TESTING.md), [roles](ROLES-PERMISSIONS.md), and [deployment](DEPLOYMENT.md).
