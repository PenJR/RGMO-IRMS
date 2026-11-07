# RGMO-IRMS

RGMO-IRMS is a Laravel-based Inventory and Resource Management System for the Research Grants Management Office. It manages inventory, project resource requests, approvals, releases, and actual utilization with access controls for Administrator, RGMO Head, Project Manager, and Staff.

## Main features

- Inventory records, stock movements, low-stock thresholds, expiry tracking, and spreadsheet import/export.
- Project records and a request → review → approval → release → utilization workflow.
- Reports and CSV/PDF exports for authorized roles.
- Forecasting, personal notifications, and dashboard summaries.
- Authentication, optional two-factor verification, user administration, and audit history.
- Administrator-triggered backups and scheduled maintenance.

Stock is deducted when resources are released. Actual utilization is recorded separately against released request items and does not deduct stock again.

## Documentation

| Guide | Purpose |
|---|---|
| [Local setup](docs/SETUP.md) | Requirements, installation, first administrator, and development commands. |
| [Deployment](docs/DEPLOYMENT.md) | Environment configuration, container processes, release checks, and migration. |
| [Backup and restore](docs/BACKUP-RESTORE.md) | Archive location, troubleshooting, restore rehearsal, and current limitations. |
| [Roles and permissions](docs/ROLES-PERMISSIONS.md) | Role capabilities, record scope, and request workflow. |
| [Suppliers and deliveries](docs/SUPPLIERS-DELIVERIES.md) | Supplier profiles, multi-item receiving, receipt history, and price comparisons. |
| [Controlled stock adjustments](docs/CONTROLLED-STOCK-ADJUSTMENTS.md) | Administrator discrepancy corrections, incident references, and adjustment history. |
| [Batches, expiry, and critical stock](docs/BATCH-EXPIRY-MONITORING.md) | Batch receiving, FEFO/FIFO releases, expiry monitoring, and industry references. |
| [Testing](docs/TESTING.md) | Automated commands, manual role checks, and evidence recording. |

## Technology

Laravel 12, PHP 8.2+ (PHP 8.3 in Docker), Blade, Vite 7, Bootstrap, Tailwind CSS, Alpine.js, and Chart.js. PDF exports use DomPDF, spreadsheet handling uses Maatwebsite Excel, and backups use Spatie Laravel Backup.

Local development defaults to SQLite. The hosted testing environment uses Render and Neon PostgreSQL. The Docker image includes PostgreSQL client 18 for database dumps.

## Quick start

The application is in the repository's `rgmo-irms/` directory. From that directory:

```bash
composer install
npm ci
```

Follow [Local setup](docs/SETUP.md) to configure a new `.env`, initialize the database, and create the first administrator, then run:

```bash
composer run dev
```

Open **http://127.0.0.1:8000/login**. Preserve existing environment settings and data when setting up an existing checkout.

## Repository layout

```text
RGMO-IRMS/
├── README.md
├── render.yaml
└── rgmo-irms/
    ├── app/          # Controllers, services, models, and commands
    ├── config/       # Application configuration and role permissions
    ├── database/     # Migrations, factories, and seeders
    ├── docker/       # Container startup and process configuration
    ├── docs/         # Setup, operations, testing, and review notes
    ├── resources/    # Blade views, CSS, and JavaScript
    ├── routes/       # Web, API, and console routes
    ├── tests/        # Automated tests
    └── Dockerfile
```

## Current deployment status

The hosted environment is being used for testing. Backups currently use private local storage inside the application container, and the UI lists successful backup records without a download/restore button. Durable storage and restore validation are part of the planned deployment migration.

A passing automated test suite, a successful backup notification, and a verified restore are different checks. See the guides above for the validation steps before final deployment.
