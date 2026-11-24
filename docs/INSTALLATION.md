# Install and run VMAS

## This Windows workspace

Project-local PHP 8.4.28, Composer 2.11.2, and MySQL 8.4.13 are under `../.tools/`. They are development tools, excluded from source control. MySQL listens only on `127.0.0.1:3307`; its data directory is `../.tools/mysql-data`. No existing MySQL service or TraceQ database is used.

1. Open PowerShell in `application`.
2. Run `./scripts/start-local.ps1`.
3. On first use, enter your name, company email, and a strong password at the console prompt. Password input is hidden.
4. Open `http://127.0.0.1:8000`, sign in, confirm your password, and set up an authenticator app.
5. Save recovery codes in a password manager, then select **Continue to workspace**.

The launcher starts the isolated MySQL process if needed and runs PHP's local development server and cleanup scheduler in the background. Closing the launching terminal does not stop these services; running the launcher again reuses them. This is not a production server. If local execution policy prevents running a script, use an approved PowerShell policy or execute the documented PHP commands manually; the project does not change system execution policy.

Manual commands from `application` when MySQL is already running:

```powershell
& '../.tools/php/php.exe' artisan vmas:create-admin
& '../.tools/php/php.exe' artisan serve --host=127.0.0.1 --port=8000
```

Only run the first command if no accounts exist. There is no default password. Losing all administrator access requires an audited administrator recovery procedure; do not add a public setup route.

## A fresh environment

Install PHP 8.4 with cURL, OpenSSL, PDO MySQL, Mbstring, Fileinfo, XML/DOM, ZIP, Intl, and Sodium; Composer; Node compatible with the locked Vite release; and MySQL 8.4. Install from official distributors and verify checksums.

1. Create a dedicated `vmas` database using `utf8mb4`, with its own credentials.
2. Copy `.env.example` to `.env`; set the database connection and application URL. Do not copy another environment's APP_KEY.
3. Run `composer install` and `php artisan key:generate`.
4. Run `php artisan migrate --seed` using migration credentials.
5. Run `npm ci --ignore-scripts` and `npm run build`.
6. Create the first account with `php artisan vmas:create-admin`.
7. Start the local server with `php artisan serve --host=127.0.0.1`.

MySQL audit triggers require sufficient migration privileges. With binary logging enabled, MySQL can require a privileged database administrator to create them. Have the DBA run that migration under approved credentials; do not grant SUPER to the application runtime. The isolated Windows development instance disables binary logging; production backup and replication settings must be designed separately.

## Test database

Create a separate `vmas_test` database and a `vmas_test` user with access only to that database. Copy the required configuration into an ignored `.env.testing`, with `APP_ENV=testing`, a separate APP_KEY, `DB_CONNECTION=mysql`, the test database credentials, and array/sync session/cache/mail/queue drivers. Tests abort if the database or username differs from `vmas_test`.

```powershell
./scripts/test-local.ps1
```

Tests rebuild the dedicated test schema. Never point them at real data. They do not send emails. Local password-reset messages use the log driver until an authorized transport is configured. Logs can contain reset links and must remain private.

## Daily development

Use `npm run build` after CSS/JS changes. The current CSP is configured for built assets, not Vite hot reload. Use `php artisan view:clear` after Blade changes if views were cached. Run `vendor/bin/pint`, `php artisan test`, `composer audit`, and `npm audit` when relevant to a change.

## Phase 2 upgrade

Install the locked Composer dependencies (PhpSpreadsheet is now required). Enable PHP gd along with the extensions already listed. Configure upload_max_filesize=5M and post_max_size=6M (or a larger request envelope); align the reverse proxy's request limit. Restart PHP after changing ini settings.

Run php artisan migrate --force and php artisan db:seed --class=DatabaseSeeder --force, then npm run build. This seeds roles/permissions, the pinned Census county catalogue, and document types; it does not create vendor records or accounts.

For an isolated local demonstration only, set APP_ENV=local and VMAS_DEMO_MODE=true, then run php artisan db:seed --class=DemoVendorSeeder. The seeder refuses other environments. Production must use VMAS_DEMO_MODE=false and a clean database containing no demonstration credentials or records.

Run the local start script to reuse/start web and cleanup services. In production, configure a supervised scheduler to invoke php artisan schedule:run each minute. Never run concurrent test suites against the same vmas_test database. Schema-only snapshots are documentation, not a replacement for versioned migrations.
