# RGMO-IRMS

RGMO-IRMS is a web-based Inventory and Resource Management System for the Research Grants Management Office. It helps manage inventory items, resource requests, project records, notifications, reports, user access, and administrative settings in one Laravel-based application.

## Features

- Dashboard summaries for inventory, requests, projects, and recent activity
- Inventory management with stock in/out, adjustments, low-stock thresholds, expiry tracking, CSV/Excel export, and import support
- Resource request workflow with submission, pending review, approval, rejection, and requester notifications
- Project management with resource usage tracking
- Reports for inventory, resource usage, audit trail, requests, biological assets, supplies issuance, and monthly inventory
- PDF and CSV report exports
- Role-based access control for admin, RGMO head, field personnel, and staff users
- Notification center with unread counts and bulk actions
- Two-factor authentication support
- Admin tools for user management, login history, system settings, and backups
- AI forecasting page for inventory prediction insights

## Tech Stack

- PHP 8.2+
- Laravel 12
- Laravel Breeze authentication
- Blade templates
- Vite
- Tailwind CSS
- Bootstrap 5
- Alpine.js
- Chart.js
- DomPDF for PDF exports
- Maatwebsite Excel for spreadsheet exports
- Spatie Laravel Backup

## Repository Structure

```text
RGMO-IRMS/
├── README.md
└── rgmo-irms/
    ├── app/
    ├── config/
    ├── database/
    ├── public/
    ├── resources/
    ├── routes/
    ├── tests/
    ├── composer.json
    └── package.json
```

The Laravel application lives inside the `rgmo-irms` directory.

## Requirements

- PHP 8.2 or newer
- Composer
- Node.js and npm
- SQLite, MySQL, PostgreSQL, or another Laravel-supported database

## Installation

```bash
cd rgmo-irms
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
```

Update `.env` with the correct database, mail, queue, and app settings before running the application in a shared or production environment.

## Running Locally

Start the Laravel server:

```bash
php artisan serve
```

Start the Vite development server in another terminal:

```bash
npm run dev
```

You can also use the Composer development script:

```bash
composer run dev
```

## Testing

Run the automated test suite:

```bash
php artisan test
```

Or use the Composer script:

```bash
composer test
```

Build frontend assets:

```bash
npm run build
```

## Main Modules

- **Authentication:** login, registration, password reset, email verification, and two-factor authentication
- **Dashboard:** role-aware summaries and charts
- **Inventory:** item records, stock movement, thresholds, import/export, and expiry controls
- **Requests:** resource request creation, review, approval, rejection, and tracking
- **Projects:** project records and linked resource usage
- **Reports:** inventory, requests, usage, audit trail, biological assets, supplies issuance, and monthly inventory
- **Notifications:** user notifications, unread counts, read/delete actions
- **Admin:** users, roles, login logs, backup tools, and system settings
- **API:** JSON endpoints for authentication, inventory, users, notifications, operations, reporting, audit, and settings

## Useful Commands

```bash
php artisan route:list
php artisan migrate
php artisan migrate:fresh --seed
php artisan config:clear
php artisan cache:clear
php artisan view:cache
npm run build
```

## License

This project is maintained for RGMO-IRMS. Check with the project owner before redistributing or deploying outside the intended environment.
