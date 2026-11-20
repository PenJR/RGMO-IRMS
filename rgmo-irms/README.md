# RGMO-IRMS

RGMO-IRMS is a Laravel-based Inventory and Resource Management System for the Research Grants Management Office. It manages inventory, project resource requests, approvals, releases, and actual utilization with access controls for Administrator, RGMO Head, Project Manager, and Staff.

## Main features

- Inventory records, stock movements, low-stock thresholds, expiry tracking, and spreadsheet import/export.
- Project records and a request → review → approval → release → utilization workflow.
- Pending request quantity editing before Storekeeper verification, with ownership checks, stock validation, protection against stale forms, and before/after history.
- Operational incident reports with private evidence, Storekeeper investigation, and administrator-controlled resolution and stock adjustments.
- Reports and CSV/PDF exports for authorized roles.
- Saved report and forecast history with original figures, filters, generator details, and PDF/CSV/JSON downloads.
- Administrator-controlled delivery corrections and unused-receipt voids, with original receipt preservation and read-only oversight for RGMO Heads and Auditors.
- Forecasting, personal notifications, and dashboard summaries.
- Authentication, optional two-factor verification, user administration, and audit history.
- Administrator-triggered backups and scheduled maintenance.

Stock is deducted when resources are released. Actual utilization is recorded separately against released request items and does not deduct stock again.

## Saved report and forecast history

Run `php artisan migrate` when updating an existing installation to create the persistent report snapshot and saved forecast explanation tables.

Open **Reports → Saved Report History** to search by report type, generation date, generator name, title, or snapshot ID. Report pages provide **Save to history**, which generates and saves a complete copy using the displayed filters. Generating a forecast saves it automatically and redirects to its saved dashboard. Refreshing a saved result or downloading it reads its stored values without recalculating inventory or forecasts. Submitting the same generation form again reopens the same saved result.

History preserves item and funding labels, quantities, calculated values, currency, reporting period, and generator name. Changes to source records or settings do not change a saved result. Audit reports capture all matching entries across pages. Blank print-ready forms do not create history. Optional AI forecast summaries are generated on request from the saved numerical forecast; the first successful summary is saved separately with its own generation details.

Access follows current permissions for each report type: `generate-reports` for normal reports, `view-audit-trail` for audit reports, and `view-forecasts` for forecasts. Authorized users share office history for their permitted types. Saved results have no edit or delete action. Existing results generated before this feature cannot be recovered retrospectively. Older bookmarked GET forecast generation URLs remain live previews; use **Generate Forecast** to save a new result.

## Documentation

| Guide | Purpose |
|---|---|
| [Local setup](docs/SETUP.md) | Requirements, installation, first administrator, and development commands. |
| [Deployment](docs/DEPLOYMENT.md) | Environment configuration, container processes, release checks, and migration. |
| [Backup and restore](docs/BACKUP-RESTORE.md) | Archive location, troubleshooting, restore rehearsal, and current limitations. |
| [Suppliers and deliveries](docs/SUPPLIERS-DELIVERIES.md) | Supplier profiles, multi-item receiving, receipt history, and price comparisons. |
| [Purchase autosave](docs/PURCHASE-AUTOSAVE.md) | Private unfinished entries, reload recovery, saved receipts, conflicts, and explicit PO creation. |
| [Funding sources](docs/FUNDING-SOURCES.md) | Administrator-managed funding choices, purchase selection, preserved history, and monthly reporting. |
| [Controlled stock adjustments](docs/CONTROLLED-STOCK-ADJUSTMENTS.md) | Administrator discrepancy corrections, incident references, and adjustment history. |
| [Roles and permissions](docs/ROLES-PERMISSIONS.md) | Role capabilities, record scope, and request and incident workflows. |
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
