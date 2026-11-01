# Testing

Run commands from `rgmo-irms/`. Install development Composer dependencies and frontend dependencies first; see [SETUP.md](SETUP.md).

## Automated checks

Use an isolated test database. `phpunit.xml` configures SQLite `:memory:`, array sessions/cache/mail, synchronous queues, and separate test cache paths. Do not run tests with shell overrides that point database connections at a real database.

```bash
php artisan test
npm run build
php artisan view:cache
php artisan view:clear
git diff --check
```

`view:cache` checks template compilation; it does not prove browser rendering. Clear compiled views after this local check. Run Pint on changed PHP files when checking code style, for example:

```bash
vendor/bin/pint --test app/Http/Controllers/Auth/AuthenticatedSessionController.php
```

Focused regression suites:

```bash
php artisan test tests/Feature/Auth/AuthenticationTest.php
php artisan test tests/Feature/ManuscriptAccessControlTest.php tests/Feature/RbacConfigurationTest.php
php artisan test tests/Feature/RequestWorkflowTest.php tests/Feature/ResourceRequestProjectTest.php
php artisan test tests/Feature/MaintenanceCommandTest.php tests/Feature/SchedulerConfigurationTest.php
php artisan test tests/Feature/InputValidationTest.php
```

Inspect actual results rather than copying old pass counts. SQLite tests do not establish PostgreSQL-specific behavior. Backup tests that mock a command do not create or restore a real archive.

## Manual acceptance checks

Use dedicated test accounts and clearly named test records. Check desktop and phone widths.

| Area | Expected result |
|---|---|
| Authentication | Each role can log in; logout then login opens the dashboard even after visiting another page. Test two-factor login where enabled. |
| Administrator | Can manage users, inventory, projects, settings, backups, and audit records. Account safeguards still apply. |
| RGMO Head | Can review/approve/reject and use reports/forecasts; cannot access account administration or audit exports. |
| Project Manager | Can submit/view own requests; cannot edit pending requests, approve, release, record utilization, or access others' requests. |
| Staff | Can edit own pending requests, release approved requests, and record eligible utilization; cannot see others' pending requests or admin/report pages. |
| Fulfillment | Stock decreases once on release; repeated or invalid fulfillment is rejected. |
| Utilization | Usage links to a released item, respects remaining quantity and valid dates, and does not deduct stock again. |
| Validation | Invalid values produce visible errors; valid Unicode and permitted uploads still work. |
| Reports | Filters and exports match visible data and the role's scope. |
| Notifications | Personal read/unread actions work and do not modify another user's notifications. |
| Layout | Forms, account menu, sidebar, tables, and dialogs remain usable at phone and desktop sizes. |
| Backup | A successful run produces an accessible ZIP; perform a separate restore rehearsal. |

Try unauthorized URLs/API operations directly. Record both allowed and denied results.

## Hosted verification

After deployment, verify `/up`, login, a database write, queues, scheduled tasks, mail delivery, exports, and backup creation. Check persistence across a planned restart. Keep local results separate from hosted results and physical-device checks.

For backup restoration, follow [BACKUP-RESTORE.md](BACKUP-RESTORE.md). For deployment checks, see [DEPLOYMENT.md](DEPLOYMENT.md).

## Record evidence

Use the existing [alpha-testing materials](alpha-testing/README.md) for test logs and defects. Record date, commit, environment, role, steps, expected result, actual result, and evidence. Mark unexecuted checks as **NOT RUN**. Do not count a screenshot of a success notification as proof that a backup can be restored.
