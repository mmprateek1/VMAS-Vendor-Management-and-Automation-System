# VMAS

Independent Vendor Management & Automation System for Invicto Labs.

Phases 1–2 implement authentication, administrator MFA, role-based access, employee administration, audit history, and core vendor management with reviewed CSV/XLSX import/export. Request management and Gmail remain in later approved phases. TraceQ is not connected.

See [the Phase 2 acceptance report](docs/PHASE-2-REPORT.md) for functionality, verification, file handling, and limitations. The current local environment uses explicitly authorized dummy accounts and clearly labelled fictional vendor records; production credentials and data are not configured.

## Start on this computer

From the application folder in PowerShell:

```powershell
./scripts/start-local.ps1
```

The launcher uses project-local PHP/MySQL runtimes, starts the web server and import-cleanup scheduler in the background, and reuses them when run again. It prompts for the first administrator on a fresh database; no default account is seeded by the application. The existing local dummy credentials were supplied separately in chat. Sign in at http://127.0.0.1:8000; administrators must enroll an authenticator. This server is for local development only.

## Documentation

- [Installation and local development](docs/INSTALLATION.md)
- [Architecture and permissions](docs/ARCHITECTURE.md)
- [User guide](docs/USER-GUIDE.md)
- [Deployment and recovery preparation](docs/OPERATIONS.md)
- [Phase 1 report and limitations](docs/PHASE-1-REPORT.md)
- [Phase 2 report and verification](docs/PHASE-2-REPORT.md)
- [Source file inventory](docs/FILE-INVENTORY.md)

## Verification

```powershell
./scripts/test-local.ps1
npm.cmd run build
```

The tests require the separate vmas_test database and user. Never use production data for testing. .env files, private files, dependencies, and local credentials are excluded from Git.

## Technology

Laravel 13, PHP 8.4, MySQL 8.4 LTS, Blade, Bootstrap 5, Fortify, Vite, and PHPUnit. Dependencies are pinned by Composer and npm lockfiles.

Do not send real vendor emails or connect TraceQ during this phase. See the acceptance report before requesting Phase 2 approval.

## GitHub code backup and restore

Repository: https://github.com/mmprateek1/VMAS-Vendor-Management-and-Automation-System

The repository root contains the Laravel application (the local `application` folder). To retrieve the code on another computer:

```powershell
git clone https://github.com/mmprateek1/VMAS-Vendor-Management-and-Automation-System.git application
cd application
```

Follow [the fresh-environment installation instructions](docs/INSTALLATION.md#a-fresh-environment) to install dependencies, create a separate MySQL database, configure a new `.env`, run migrations/seeds, and build the frontend. The Windows launcher additionally requires the project-local runtimes described in that guide; they are not bundled in Git.

This backup includes application code, migrations, schema-only snapshots, dependency lockfiles, tests, reference data, fictional sample data, and documentation. It excludes credentials, `.env` files, private uploads, live database contents, logs, installed dependencies, and local runtimes. Recovering an existing company's records requires separate encrypted database/file backups and the original application encryption key stored securely outside Git.

This is a versioned snapshot, not automatic synchronization: future changes must also be committed and pushed.
