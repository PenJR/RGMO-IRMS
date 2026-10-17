# Render to AWS migration guide for RGMO-IRMS

**Application:** Laravel 12, PHP 8.3, Nginx, PostgreSQL/SQLite, Vite  
**Recommended AWS target:** Amazon ECR + Amazon ECS on AWS Fargate + Application Load Balancer + Amazon RDS for PostgreSQL + AWS Secrets Manager + CloudWatch  
**Prepared:** 2026-08-16

This is an operator runbook. Perform it in a staging AWS environment first. Do not delete the Render service or its database until the AWS production deployment has completed its agreed stabilization period and a restore has been tested.

## 1. Target architecture

| Render component | AWS replacement |
|---|---|
| Render web service (Docker) | ECS Fargate service |
| Render container registry/build | ECR image repository and CI/local Docker build |
| Render PostgreSQL | RDS for PostgreSQL |
| Render environment variables | Secrets Manager for secrets; ECS task variables for non-secrets |
| Render public URL/TLS | Application Load Balancer, ACM certificate, and Route 53/DNS |
| Render logs/metrics | CloudWatch Logs, metrics, alarms, and ECS Container Insights |
| Render disk/local backup files | Private S3 bucket; optionally EFS only if shared POSIX storage is required |

Recommended network layout:

```text
Internet
   |
Route 53/DNS + ACM certificate
   |
Application Load Balancer (public subnets in two Availability Zones)
   |
ECS Fargate task (private subnets, port 10000)
   |
RDS PostgreSQL (private database subnets, port 5432)
```

Use one AWS Region for the initial migration. `ap-southeast-1` (Singapore) is a reasonable example for a Philippines-based deployment, but the organization must choose the Region based on data-residency, latency, availability, and cost requirements.

## 2. Important constraints in this repository

Read these before provisioning anything:

1. The existing `Dockerfile` is reusable on ECS. It exposes port `10000` and starts Nginx, PHP-FPM, a queue worker, and `php artisan schedule:work` through Supervisor.
2. Keep the initial ECS service at **exactly one desired task**. More than one task would start duplicate Laravel schedulers. Queue workers may be replicated, but the scheduler must be separated or protected with distributed locks before scaling the web service.
3. The container startup script automatically executes `php artisan migrate --force`. Take a database snapshot before each deployment that contains schema changes.
4. Do not use SQLite on Fargate. Fargate container storage is disposable. Production must use RDS PostgreSQL.
5. The Spatie backup package currently uses its vendor default destination, the `local` disk. Those backup files would disappear with the task. Publish/configure the package to use a private S3 disk before production.
6. `FILESYSTEM_DISK=local` is acceptable only if the application never needs files to survive a task replacement. Use S3 for durable application files and backups.
7. The load balancer can use the existing unauthenticated Laravel `/up` route for liveness. Add a separate database-readiness check later if required; do not expose sensitive diagnostics.
8. Preserve the existing `APP_KEY`. Changing it can invalidate encrypted application values and sessions.

## 3. Migration variables and prerequisites

Install and configure these tools on the operator workstation:

- Docker
- AWS CLI v2
- Git
- `psql`, `pg_dump`, and `pg_restore` matching or newer than the source PostgreSQL major version when the Render source uses PostgreSQL
- `sqlite3` and a rehearsed SQLite-to-PostgreSQL conversion tool or ETL when the Render source uses SQLite

Authenticate to the intended AWS account, enable MFA, and verify the identity before making resources:

```bash
aws sts get-caller-identity
```

Use task-specific shell variables. Replace the examples before running commands:

```bash
export RGMO_AWS_REGION=ap-southeast-1
export RGMO_ECR_REPOSITORY=rgmo-irms
export RGMO_AWS_ACCOUNT_ID="$(aws sts get-caller-identity --query Account --output text)"
export RGMO_IMAGE_TAG="$(git rev-parse --short=12 HEAD)"
export RGMO_ECR_REGISTRY="${RGMO_AWS_ACCOUNT_ID}.dkr.ecr.${RGMO_AWS_REGION}.amazonaws.com"
```

Never put passwords, `APP_KEY`, API keys, SMTP credentials, or production database contents into the command history, Git repository, task-definition plaintext, screenshots, or this document.

## 4. Inventory and secure the Render deployment

Record the following from Render without changing it:

- Service name, Region, plan, Docker branch/commit, custom domains, health check, and current image digest
- All environment variable **names** and where their values are owned; copy secret values only into an approved password/secret transfer process
- Database engine and version
- Whether production is using Render PostgreSQL or a SQLite file on a persistent disk
- Persistent disk mount paths and all durable uploaded/generated files
- Scheduled jobs, background workers, queue depth, failed jobs, and current sessions
- DNS provider, record names, current TTLs, and certificate ownership
- Current traffic/error baseline and business reconciliation totals

Before copying secrets, rotate any credential that has been committed to Git or shared insecurely. Scan the repository and Git history. Use the rotated values in AWS.

Take and verify a source backup before the rehearsal and again during the final cutover. Do not treat a successful backup command as proof; restore it into an isolated environment and validate it.

## 5. Create the AWS network and security groups

Use the VPC console or approved infrastructure-as-code:

1. Create or select a VPC spanning at least two Availability Zones.
2. Create two public subnets for the Application Load Balancer.
3. Create two private application subnets for ECS tasks.
4. Create two private database subnets and an RDS DB subnet group.
5. Give private ECS tasks outbound access through a NAT gateway, or provide the necessary VPC endpoints and other outbound path for ECR, S3, CloudWatch Logs, Secrets Manager, SMTP, and the Gemini API. The latter is more advanced.
6. Create these security groups:

| Security group | Inbound | Outbound |
|---|---|---|
| `rgmo-irms-alb-sg` | TCP 443 from approved internet sources; TCP 80 only for redirect | TCP 10000 to `rgmo-irms-ecs-sg` |
| `rgmo-irms-ecs-sg` | TCP 10000 from `rgmo-irms-alb-sg` only | TCP 5432 to RDS plus required HTTPS/SMTP egress |
| `rgmo-irms-rds-sg` | TCP 5432 from `rgmo-irms-ecs-sg` and the temporary migration host only | Default response traffic |

Do not make RDS publicly accessible. Remove temporary migration-host access after the data move.

## 6. Create RDS for PostgreSQL

In Amazon RDS, create a PostgreSQL instance with these minimum production choices:

- Engine: a currently AWS-supported PostgreSQL major version that has passed this application's full test suite
- Public access: **No**
- DB subnet group: the private database subnets
- VPC security group: `rgmo-irms-rds-sg`
- Storage encryption: enabled with the approved KMS key
- Deletion protection: enabled for production
- Automated backups: enabled; choose an approved retention period, such as 7–35 days
- Point-in-time recovery: verify it is active
- Multi-AZ: recommended for production; a Single-AZ staging instance is acceptable if the risk is understood
- Performance Insights/Enhanced Monitoring: enable as required by the operations policy
- PostgreSQL SSL: require TLS (`rds.force_ssl=1`) in the parameter group

Create separate roles where practical:

- A migration/schema role for controlled releases
- A least-privilege runtime role for the application

Record the RDS endpoint, port, database name, CA requirements, maintenance window, backup window, recovery point objective (RPO), and recovery time objective (RTO).

For hostname and CA verification, update the `pgsql` connection in `config/database.php` to expose Laravel's supported `sslrootcert` option:

```php
'sslmode' => env('DB_SSLMODE', 'verify-full'),
'sslrootcert' => env('DB_SSLROOTCERT'),
```

Bake the current RDS CA bundle into the image, set `DB_SSLROOTCERT` to its container path, and test `DB_SSLMODE=verify-full`. Until that is implemented and tested, `DB_SSLMODE=require` encrypts the connection but does not provide the same server identity verification; accept that exception only through the organization's security process.

## 7. Build and push the Docker image to ECR

Create an immutable ECR repository once:

```bash
aws ecr create-repository \
  --repository-name "${RGMO_ECR_REPOSITORY}" \
  --region "${RGMO_AWS_REGION}" \
  --image-tag-mutability IMMUTABLE \
  --image-scanning-configuration scanOnPush=true \
  --encryption-configuration encryptionType=AES256
```

Authenticate Docker, build for the ECS task architecture, and push a commit-addressed image:

```bash
aws ecr get-login-password --region "${RGMO_AWS_REGION}" | \
  docker login --username AWS --password-stdin "${RGMO_ECR_REGISTRY}"

docker build \
  --platform linux/amd64 \
  --tag "${RGMO_ECR_REGISTRY}/${RGMO_ECR_REPOSITORY}:${RGMO_IMAGE_TAG}" \
  .

docker push "${RGMO_ECR_REGISTRY}/${RGMO_ECR_REPOSITORY}:${RGMO_IMAGE_TAG}"
```

Record the pushed image digest and deploy by immutable tag or digest. Do not use a mutable `latest` tag as the production release identifier. Review the image scan before deployment.

Run a local image smoke test with non-production values before creating the ECS service. The image must become healthy on container port `10000` and return HTTP 200 from `/up`.

## 8. Configure durable S3 storage and backups

Create a private, encrypted, versioned S3 bucket dedicated to RGMO-IRMS. Keep all four S3 Block Public Access settings enabled. Add lifecycle/retention rules approved by the data owner.

Before setting `FILESYSTEM_DISK=s3`, install Laravel's S3 adapter:

```bash
composer require league/flysystem-aws-s3-v3:^3.0 --with-all-dependencies
```

Publish the backup configuration if it is not present:

```bash
php artisan vendor:publish --provider="Spatie\Backup\BackupServiceProvider" --tag=backup-config
```

Change the backup destination disks in `config/backup.php` from `local` to an environment-controlled disk, for example:

```php
'disks' => [
    env('BACKUP_DISK', 's3'),
],
```

Use these production variables:

```dotenv
FILESYSTEM_DISK=s3
BACKUP_DISK=s3
AWS_DEFAULT_REGION=ap-southeast-1
AWS_BUCKET=replace-with-private-bucket-name
AWS_USE_PATH_STYLE_ENDPOINT=false
```

Do **not** set `AWS_ACCESS_KEY_ID` or `AWS_SECRET_ACCESS_KEY` in ECS. Attach a least-privilege ECS **task role** that grants only the required S3 bucket/prefix actions. The task execution role and task role are different: the execution role lets ECS pull ECR images, write logs, and retrieve startup secrets; the task role is used by Laravel while it runs.

If some files must be publicly downloadable, keep the bucket private and serve them through authenticated application routes or a carefully designed CloudFront distribution. Do not make the bucket public.

## 9. Store configuration and secrets

Create a Secrets Manager secret such as `rgmo-irms/production`. Store at least:

- `APP_KEY` — copy the current production key exactly
- `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD`, and sender values as appropriate
- `GEMINI_API_KEY`

Use plaintext ECS environment variables only for non-sensitive configuration:

```dotenv
APP_NAME=RGMO-IRMS
APP_ENV=production
APP_DEBUG=false
APP_URL=https://irms.example.gov.ph
ASSET_URL=https://irms.example.gov.ph
APP_TIMEZONE=Asia/Manila
LOG_CHANNEL=stderr
LOG_LEVEL=warning
PORT=10000

DB_CONNECTION=pgsql
DB_PORT=5432
DB_SSLMODE=verify-full
DB_SSLROOTCERT=/etc/ssl/certs/rds-global-bundle.pem

SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
CACHE_STORE=database
QUEUE_CONNECTION=database

FILESYSTEM_DISK=s3
BACKUP_DISK=s3
AWS_DEFAULT_REGION=ap-southeast-1
AWS_BUCKET=replace-with-private-bucket-name
AWS_USE_PATH_STYLE_ENDPOINT=false
ALLOW_EMPTY_SQLITE=false
```

Use the real canonical HTTPS domain. Do not carry `RENDER_EXTERNAL_HOSTNAME` into ECS. Do not set `AWS_ACCESS_KEY_ID`/`AWS_SECRET_ACCESS_KEY` when the task role is used.

When a secret changes, register a new task-definition revision and force a new ECS deployment; already-running containers do not automatically receive updated secret values.

## 10. Create ECS and the Application Load Balancer

1. Create an ECS cluster and enable Container Insights if approved.
2. Create a CloudWatch log group such as `/ecs/rgmo-irms-production`; set an explicit retention period.
3. Create an ECS task execution role with ECR pull, CloudWatch Logs, and scoped Secrets Manager/KMS permissions.
4. Create the application task role with scoped access to the RGMO S3 bucket/prefix only, plus any other explicit runtime AWS permissions.
5. Register a Fargate task definition:
   - OS/architecture: Linux/X86_64 to match the build command
   - Task CPU: start with 0.5 vCPU
   - Task memory: start with 1 GB, then size from measured staging use
   - Container image: the immutable ECR URI/digest
   - Container port: `10000` TCP, app protocol HTTP
   - Non-secret and secret variables: from the previous section
   - Logging: `awslogs` to the production log group
   - Health check/start period: allow enough time for the startup migration and Laravel caches, but keep migrations small
6. Create an IP target group on HTTP port `10000`:
   - Health path: `/up`
   - Success code: `200`
   - Start with interval 30 seconds, timeout 5 seconds, healthy threshold 2, unhealthy threshold 3
7. Create an internet-facing Application Load Balancer in both public subnets using `rgmo-irms-alb-sg`.
8. Add an HTTPS 443 listener using an ACM certificate and forward to the target group.
9. Add an HTTP 80 listener that redirects to HTTPS 443.
10. Create an ECS Fargate service in the private application subnets. Use desired count **1** in staging. Create the production service at desired count **0**, or do not create it until the production database migration is complete:
    - Production desired tasks after data migration: **1**
    - Public IP: disabled
    - Security group: `rgmo-irms-ecs-sg`
    - Load balancer target: container `rgmo-irms`, port `10000`
    - Deployment circuit breaker: enabled with automatic rollback
    - Health-check grace period: begin around 120 seconds, then reduce based on measurements
    - Until web/worker/scheduler are separated: minimum healthy percent `0` and maximum percent `100`, which avoids overlapping schedulers but causes brief deployment downtime

For staging, wait until the target is healthy. Inspect CloudWatch logs and ECS stopped-task reasons if it fails. Keep the production service at zero tasks until its database migration is complete, and do not point production DNS to the load balancer yet.

### Safer scaling design after migration

Before increasing the web desired count above one:

1. Remove the queue worker and scheduler from the web container's Supervisor configuration.
2. Run queue workers as a separate ECS service using the same image and an explicit `php artisan queue:work ...` command.
3. Replace the continuously running scheduler with one EventBridge Scheduler ECS task every minute running `php artisan schedule:run --no-interaction`, or use a single dedicated scheduler service.
4. Use a distributed cache/lock backend and Laravel `onOneServer()`/`withoutOverlapping()` where appropriate.
5. Re-test backups, low-stock alerts, pruning, failed jobs, deployments, and graceful queue shutdown.

## 11. Migrate the database

First identify the actual Render production database. Follow exactly one branch below.

### Branch A — Render PostgreSQL to RDS PostgreSQL

Rehearse using a production-like snapshot. The source database should remain online for rehearsal and become read-only or maintenance-frozen for the final dump.

Create a custom-format dump with no ownership/ACL metadata:

```bash
pg_dump \
  --format=custom \
  --no-owner \
  --no-acl \
  --file=rgmo-render-final.dump \
  "${RGMO_RENDER_DATABASE_URL}"
```

Transfer the dump through the approved encrypted channel to a temporary migration host that can reach private RDS. Restore to a clean target database using the migration role:

```bash
pg_restore \
  --exit-on-error \
  --no-owner \
  --no-acl \
  --dbname="${RGMO_RDS_DATABASE_URL}" \
  rgmo-render-final.dump
```

Start a one-off task/new service revision so the application's startup command applies only pending Laravel migrations. Review `php artisan migrate:status` and the application logs.

### Branch B — Render SQLite to RDS PostgreSQL

Do not copy `database.sqlite` into the Fargate image and do not feed an SQLite `.dump` directly to `psql`. SQLite SQL syntax, types, sequences, booleans, and constraints are not a PostgreSQL restore format.

Required process:

1. Put Render into maintenance/read-only mode for the final export.
2. Stop/drain queue workers so no job writes after the snapshot.
3. Create a transactionally consistent SQLite backup with SQLite's `.backup` command, not a filesystem copy of an actively written database:

   ```bash
   sqlite3 "${DB_DATABASE}" ".backup '/tmp/rgmo-render-final.sqlite'"
   sqlite3 /tmp/rgmo-render-final.sqlite "PRAGMA integrity_check;"
   ```

4. Encrypt and transfer the snapshot to the approved temporary migration host or a locked migration prefix in S3. Restrict access and set an expiry/deletion date.
5. Run Laravel migrations against an empty staging RDS database to create the canonical PostgreSQL schema. One option is a one-off ECS task based on the application task definition with its command overridden to `php artisan migrate --force --no-interaction`; it must use the staging RDS secrets and exit successfully.
6. Use a **versioned and rehearsed ETL** to copy business tables in foreign-key order while preserving primary keys. Do not blindly convert ephemeral `cache`, `cache_locks`, `jobs`, `failed_jobs`, `sessions`, or password-reset records; drain/exclude them according to the cutover decision.
7. Convert/validate SQLite integer booleans, timestamps/time zones, JSON text, decimals, nullability, unique values, soft deletes, and foreign-key references.
8. Reset every PostgreSQL identity/sequence to at least the maximum imported primary key. A common per-table pattern is:

   ```sql
   SELECT setval(
       pg_get_serial_sequence('inventory_items', 'id'),
       COALESCE((SELECT MAX(id) FROM inventory_items), 1),
       (SELECT COUNT(*) > 0 FROM inventory_items)
   );
   ```

9. Run `ANALYZE`, then reconcile counts, keys, constraints, aggregates, and sampled records.
10. Repeat the full procedure in staging at least twice and record duration before the production cutover.

`pgloader` can help with a rehearsal, but the output must be reconciled against the Laravel-created schema. Do not make an unrehearsed one-command `pgloader` conversion the production cutover plan.

### Required reconciliation for either branch

At minimum, compare source and target for:

- Row counts for every business table
- Minimum/maximum primary keys and duplicate/unique-key checks
- Foreign-key violations and unexpected nulls
- Users, roles, projects, inventory items, resource requests, request items, inventory transactions, audit logs, login histories, notifications, and resource usage
- Total stock, inventory value, transaction quantities by type, requests by status, and request-item totals
- JSON fields, timestamps, soft-deleted records, 2FA/encrypted fields, and a sample of large text values

Record the queries, expected totals, actual totals, timestamp, operator, and approval. A count-only check is not enough.

## 12. Configure TLS and DNS without cutting over

1. Request an ACM public certificate in the same Region as the Application Load Balancer.
2. Validate certificate ownership using DNS.
3. Attach the certificate to the ALB HTTPS listener.
4. Test the application through the ALB DNS name or a temporary staging hostname.
5. Lower the existing production DNS TTL, ideally at least one TTL period before cutover.
6. Prepare a Route 53 alias record to the ALB, or the equivalent ALB CNAME/ALIAS at the current DNS provider.

Test forwarded HTTPS behavior, cookies, redirects, asset URLs, login/logout, and generated links. Keep `APP_URL`, `ASSET_URL`, and `SESSION_SECURE_COOKIE=true` aligned with the final HTTPS hostname.

## 13. Staging acceptance test

Do not approve production cutover until all of these pass:

- The exact production image digest runs in staging
- Full Laravel automated tests pass against the selected PostgreSQL major version
- `/up` is healthy through the ALB and debug output is disabled
- Login, logout, session persistence, password reset/email, 2FA, and role permissions work
- Inventory, projects, requests/approval/fulfillment, reports, PDF, Excel, notifications, and AI forecasting work
- Concurrent approval/stock-deduction behavior is tested on PostgreSQL
- Queue jobs process once and failed-job handling/alerting is verified
- Each scheduled command executes once, in `Asia/Manila`, at the expected time
- S3 file read/write and `app:backup` work using the task role
- An RDS snapshot/backup is restored into an isolated instance and reconciled
- CloudWatch receives stdout/stderr without secrets; alarms reach the on-call contact
- Replacing the ECS task does not lose database records or required files
- The cutover and rollback procedure completes within the approved downtime/RTO

## 14. Production cutover runbook

Assign a person and timestamp to every item.

1. Confirm change approval, go/no-go owner, communication channel, rollback threshold, source backup, AWS capacity, image digest, and migration artifact checksum.
2. Confirm the new RDS target is empty/at the expected rehearsal state and take a pre-cutover snapshot.
3. Put Render into maintenance mode with an approved operator bypass.
4. Stop/drain the Render queue worker. Confirm queue state and ensure no application writers remain.
5. Take the final Render PostgreSQL dump or consistent SQLite backup.
6. Transfer, restore/ETL, reset sequences, and execute the signed-off reconciliation.
7. Start/deploy the ECS service with the final secret/config revision and exact image digest.
8. Confirm only one scheduler is running and the queue worker is healthy.
9. Run technical and business smoke tests through the final HTTPS hostname using a temporary host override or weighted/test record when available.
10. Obtain the technical and data-owner go decision.
11. Change production DNS/alias to the ALB.
12. Confirm DNS resolution, certificate, HTTP-to-HTTPS redirect, `/up`, login, write workflows, queue processing, email, reports, and CloudWatch alarms from an external network.
13. Reopen the application and begin the stabilization window.
14. Keep Render intact but read-only. Do not allow production writes in both environments.

Monitor at least ALB 4xx/5xx, target response time, healthy host count, ECS CPU/memory/task restarts, stopped-task reasons, RDS CPU/storage/connections/locks/latency, failed jobs/queue depth, backup age, email failures, and key business totals.

## 15. Rollback rules

Define numerical rollback triggers before cutover, such as sustained 5xx rate, inability to log in, failed reconciliation, database errors, failed critical workflows, or data corruption.

- **Before AWS accepts production writes:** point DNS/traffic back to Render, restart its worker, remove maintenance mode, document the failure, and preserve all evidence.
- **After AWS accepts production writes:** do not simply point users back to the old Render database; that would lose or fork new records. Prefer fixing forward or rolling back only the application image while continuing to use RDS if the schema is compatible.
- If database rollback is unavoidable after writes, freeze traffic, preserve the AWS database, quantify the new writes, and follow the separately rehearsed reverse-migration/restore procedure with data-owner authorization.
- Never run destructive schema or database cleanup commands during an incident unless the exact target and recovery path have been approved.

## 16. After cutover

During the stabilization period:

1. Reconcile key business totals again after 1 hour, 24 hours, and the agreed later interval.
2. Verify the first scheduled backup, low-stock alert, pruning run, and restore test.
3. Right-size Fargate and RDS only after collecting representative metrics.
4. Add alarms, dashboards, runbooks, cost budgets, tagging, dependency/image scanning, and secret-rotation ownership.
5. Separate the web, worker, and scheduler before horizontal scaling.
6. Keep source backups and migration artifacts only for the approved retention period, encrypted and access-logged.

After formal sign-off and the retention window:

1. Take the approved final Render backup and record its checksum/location/expiry.
2. Remove Render DNS/custom domains.
3. Suspend the Render service first if the plan permits a recoverable cooling-off period.
4. Delete Render resources only with explicit approval and after confirming billing, retention, and recovery obligations.
5. Revoke Render-only credentials and access, and update the system inventory and disaster-recovery documentation.

## 17. AWS reference documentation

- [ECS on Fargate getting started](https://docs.aws.amazon.com/AmazonECS/latest/developerguide/getting-started-fargate.html)
- [ECR image lifecycle and Docker push](https://docs.aws.amazon.com/AmazonECR/latest/userguide/getting-started-cli.html)
- [ECS task execution and task IAM roles](https://docs.aws.amazon.com/AmazonECS/latest/developerguide/security-iam-roles.html)
- [Pass Secrets Manager secrets to ECS](https://docs.aws.amazon.com/AmazonECS/latest/developerguide/secrets-envvar-secrets-manager.html)
- [Send ECS logs to CloudWatch](https://docs.aws.amazon.com/AmazonECS/latest/developerguide/using_awslogs.html)
- [Application Load Balancer health checks](https://docs.aws.amazon.com/elasticloadbalancing/latest/application/target-group-health-checks.html)
- [ECS deployment circuit breaker](https://docs.aws.amazon.com/AmazonECS/latest/developerguide/deployment-circuit-breaker.html)
- [Schedule ECS tasks with EventBridge Scheduler](https://docs.aws.amazon.com/AmazonECS/latest/developerguide/tasks-scheduled-eventbridge-scheduler.html)
- [RDS for PostgreSQL](https://docs.aws.amazon.com/AmazonRDS/latest/UserGuide/CHAP_PostgreSQL.html)
- [RDS PostgreSQL SSL/TLS](https://docs.aws.amazon.com/AmazonRDS/latest/UserGuide/PostgreSQL.Concepts.General.SSL.html)
- [RDS automated backups and point-in-time recovery](https://docs.aws.amazon.com/AmazonRDS/latest/UserGuide/USER_WorkingWithAutomatedBackups.html)
- [S3 Block Public Access](https://docs.aws.amazon.com/AmazonS3/latest/userguide/access-control-block-public-access.html)
- [Route 53 alias to an Application Load Balancer](https://docs.aws.amazon.com/Route53/latest/DeveloperGuide/routing-to-elb-load-balancer.html)
