# VMAS operations and production preparation

Phase 1 is a local foundation. It is not approved for production vendor operations.

## Production configuration

Use a dedicated HTTPS hostname, Nginx and PHP-FPM, MySQL 8.4, and persistent private storage. The web document root must be `application/public`, never the repository root. Deny hidden files and direct access to storage, configuration, vendor dependencies, backups, and logs. Limit filesystem writes to `storage` and `bootstrap/cache` for the web identity.

Set `APP_ENV=production`, `APP_DEBUG=false`, the actual HTTPS `APP_URL`, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax`, `SESSION_ENCRYPT=true`, and a suitable session lifetime. Configure only approved reverse proxies and allowed hostnames. Keep APP_KEY and credentials in the deployment secret store. A lost APP_KEY makes encrypted MFA secrets and sessions unusable.

Use a migration account for schema changes. The runtime account should have only needed table-level privileges; grant audit SELECT/INSERT and no UPDATE/DELETE/DROP/TRIGGER privileges. Use audit triggers as defense in depth. Export audit history to independently protected storage before business rollout. Triggers do not protect against a database administrator or table truncation by an overly privileged account.

Build with locked dependencies. Deploy reviewed code, run migrations with migration credentials, seed fixed roles, build assets, cache configuration/routes/views, then restart PHP and workers. Take a verified backup first. Database rollback may destroy information; use backward-compatible migrations and a reviewed recovery plan.

## Background processes

Database queue tables exist. No Gmail, SLA, or reminder jobs are implemented in this phase. Later deployment will supervise `php artisan queue:work` and run `php artisan schedule:run` once per minute, with monitoring for failed jobs, backlog, and scheduler health. Do not run long-lived production processes using `artisan serve`.

## Backup and recovery

Back up the database, private files, and required secrets. Keep encrypted off-host copies with restricted access and a documented retention policy. Establish business-approved RPO/RTO values before production.

For a MySQL logical backup, use a supported `mysqldump` with `--single-transaction --routines --triggers --events --no-tablespaces --set-gtid-purged=OFF`. Supply credentials through a protected options file, not command-line passwords. Do not dump unrelated databases. Coordinate attachment snapshots with database backups once uploads exist.

Restore into a separate database and private directory first. Restore required keys securely, install the matching application version, verify migration state, account access, audit triggers, and record/file integrity. Revoke sessions and review integrations before reconnecting external services. Never point a restore rehearsal at production.

No backup scheduler, encrypted backup destination, full recovery rehearsal, load test, or production deployment has been completed in Phase 1. These remain release acceptance requirements in Phase 7.

## Gmail and future integrations

Gmail and Google Chat are not connected. Do not configure vendor sending during foundation testing. Phase 4 requires the selected official mailbox, authorized Cloud project, OAuth consent configuration, redirect URL, approved scopes, and optional Pub/Sub permissions. Use `gmail.send` plus `gmail.readonly` initially, subject to Workspace policy. Encrypt refresh tokens and handle revocation explicitly. Never mark queued/uncertain mail as sent.

TraceQ remains disconnected. Future integration must use authenticated APIs and agreed SSO, never direct database sharing.

## Phase 2 import retention and local processes

php artisan vmas:prune-imports removes expired staged row data, clears its mapping/headers, retains summary counts, and records cleanup. It also removes abandoned private vendor-import upload files older than one day. It is scheduled daily at 00:00 UTC. The local launcher runs cleanup once and starts a single background schedule:work process. Cleanup requires the application/scheduler to be running; on restart the launch-time command handles overdue data. Production must supervise and monitor its scheduler independently.

Local PID files: .tools/app-server.pid, .tools/mysql.pid, .tools/scheduler.pid. Local logs: .tools/app-server-stderr.log and .tools/scheduler-stderr.log. The launcher verifies the actual login page content, not just HTTP 200, and refuses to take over an unrecognized listener on port 8000. It does not terminate unrelated processes.

Do not expose storage/app/private or database backups over HTTP. MySQL row storage is not automatically encrypted at rest by this application: production requires appropriately encrypted disks/backups and controlled database access. Retained vendor audit snapshots contain historical business contact data; agree retention policy before real rollout.

Dummy mode is a visible classification, not an authentication shortcut. Administrator MFA and role checks continue to apply. Do not change .env to production while continuing to use the demonstration database/accounts.

Refer to PHASE-2-REPORT.md for spreadsheet limits, sources, tests, and remaining phases.
