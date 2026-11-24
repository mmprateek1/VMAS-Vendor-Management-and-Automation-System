# VMAS architecture

VMAS is an independent Laravel 13 application for Invicto Labs. Phase 1 provides identity, access administration, audit history, a responsive interface, and private storage configuration. It has no dependency on TraceQ and contains no vendor communication integration yet.

## Boundaries

Blade and Bootstrap render the interface. HTTP controllers validate inputs and call business services. Laravel policies/gates enforce permissions on the server. Eloquent models and migrations define MySQL persistence. Fortify supplies authentication, password recovery, and TOTP MFA. Database queues and Laravel's scheduler are configured for later automation modules.

Use a modular monolith. Add vendor, request, allocation, communication, document, approval, automation, and reporting services incrementally. Do not implement domain rules separately in controllers, UI code, and future APIs.

## Current schema

- `users`: hashed password, one role, active state, first-login password-change requirement, last login, encrypted MFA secret and recovery codes.
- `roles`, `permissions`, `permission_role`: three fixed foundation roles and explicit capabilities. Users without a role receive no workspace access.
- `audit_logs`: actor, action, entity, before/after JSON, reason, IP, UTC timestamp. Secrets are excluded. Model guards and MySQL triggers reject update/delete operations. No audit editing route exists.
- Laravel `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs` support identity and future workers.
- `migrations` records schema history. No operational vendor or retrieval tables exist in Phase 1.

## Permissions

| Role | Workspace | Employees | Organization audit | Settings |
| --- | --- | --- | --- | --- |
| Administrator | Read | Create/update/deactivate | Read | Read |
| VMT Employee | Own account activity | Denied | Denied | Denied |
| COO / Management | Organization overview | Denied | Read | Denied |

Management can update its own password and MFA; this does not grant operational write access. Every operational route is inside `auth` and `operational` middleware. Administrators require confirmed MFA. User creation does not accept arbitrary model fields. Account changes invalidate affected database sessions. Users cannot deactivate themselves or change their own role. User email changes, deletion, customizable roles, approval permissions, and team scoping are deferred until their relevant modules are implemented.

Role permissions are maintained in `AccessSeeder` and are safe to seed repeatedly. No shared or default login is created. `vmas:create-admin` interactively creates the first administrator only, under a database lock.

## Private files

The default disk is `storage/app/private`; automatic serving is disabled. Never link private storage into `public/`. Uploads, scanning, authorization-checked downloads, attachment metadata, and Gmail attachment retrieval are future work. Configuring a directory is not an implemented document module. Local disk encryption is a host responsibility and has not been configured by this build.

## Research reconciliation

The earlier research report proposed automatic allocation and early TraceQ investigation. The user's master development prompt supersedes these: allocation requires employee confirmation and TraceQ integration is deferred. The source research files remain unchanged.

## Coding conventions

Use Laravel conventions, PSR-12/Laravel Pint, explicit validation, named routes, escaped Blade output, and transactions around business writes plus audit records. Store timestamps in UTC and fixed-precision monetary amounts with currency in later modules. User-facing date/time labels must identify their timezone. Write regression tests for permission boundaries, concurrency, transitions, and failed external operations.

Production must use HTTPS and database accounts separate from development. Keep migration credentials separate from runtime credentials. Database administrators can remove audit triggers; off-host append-only audit retention is required before describing the system as tamper-proof.

## Future integration contracts

Reserve `/api/v1` for authorized service endpoints in a later phase. No API token or TraceQ endpoint is currently exposed. Reuse domain services and add caller scopes, idempotency, input validation, audit events, and versioned contracts. Select SSO only after TraceQ's actual identity capabilities are known.

## Phase 2 extension (24 November 2026)

VendorDirectory owns validation, normalized duplicate identity, transactional saves, revision checks, relationship synchronization, and audit snapshots. VendorSpreadsheet handles bounded CSV/XLSX parsing and safe exports. VendorImports serializes batch actions under a database row lock, revalidates all accepted rows, and commits them together. Controllers enforce permission gates, import ownership, and input contracts; Blade screens expose review and correction.

County and document capability relationships are many-to-many. Contacts are subordinate to a vendor. Vendor primary email and normalized company name have database unique constraints. New vendors are always inactive. Activation uses a separate admin-only endpoint with a reason and revision check. All writes remain under the existing auth/operational middleware.

Raw upload files are private and short-lived. Import staging data is held in MySQL for seven days and cleaned by the scheduler; audit and summary retention are separate. VMAS_DEMO_MODE labels local test records and is off by default in source configuration. No new external API credentials are needed for Phase 2.

Current workload and performance measures will derive from actual request data in Phase 3. See PHASE-2-REPORT.md for exact limits and verification.
