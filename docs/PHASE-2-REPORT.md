# Phase 2 acceptance report

Date: 24 November 2026
Status: implemented and verified locally with fictional data; awaiting user review before Phase 3.

## Delivered

- Vendor directory with create/edit/profile screens, multiple contacts (up to 10), county coverage, document capabilities, working days/hours/time zone, availability, commercial terms, currency, internal priority, remarks, and timestamps.
- Search by company/contact/email/phone; combined state, county, document, availability, and activation filters; pagination; filtered CSV and XLSX exports.
- Case/whitespace-normalized company-name and case-insensitive primary-email duplicate prevention, backed by unique database constraints.
- Revision checks prevent stale edits or stale import actions from overwriting newer work.
- All vendor changes have append-only audit records. Profiles show readable before/after changes, author, time, and reason.
- Reviewed import: private upload, preview, column mapping, required-field validation, duplicate detection, per-row correction/rejection/restoration, transactional commit, and completion summary.
- Downloads include CSV/XLSX templates. XLSX exports preserve literal text; CSV exports neutralize spreadsheet formula prefixes.
- A local demo mode marks test records visibly. Six seeded fictional vendors plus two browser-imported fictional vendors are currently present.

## Approved access rules

| Role | View/search/history | Create/edit/import | Export | Activate/deactivate |
| --- | --- | --- | --- | --- |
| Administrator | Yes | Yes | Yes | Yes, with reason |
| VMT employee | Yes | Yes | Yes | No |
| COO / management | Yes | No | Yes | No |

The user explicitly confirmed: VMT employees maintain/import vendors; administrators control activation. All newly created/imported vendors start inactive, including admin-created records, until the separate activation action is taken. Availability and activation are independent. Changing availability never changes activation.

Administrator MFA remains enforced. The browser demonstration used the employee account. Administrator and management boundaries were exercised in automated tests; no MFA bypass was added.

## Database and private file handling

New tables: counties, document_types, vendors, vendor_contacts, county_vendor, document_type_vendor, vendor_imports, vendor_import_rows. Foreign keys and unique constraints protect relationships and identity. The schema-only export is database/schema/phase-2.mysql.sql; Laravel migrations remain authoritative.

Files are parsed in private server storage using random names, never linked into public/. The uploaded source is deleted after parsing, including handled failures. Staged rows, validation errors, and mapping live in MySQL for seven days. Expired batches cannot be changed or committed. The daily vmas:prune-imports command removes expired row data and abandoned upload files older than one day; summary counts and audit history remain.

The local launcher starts one background web server and one cleanup scheduler, reuses them on subsequent launches, validates the actual login page content, and does not stop unrelated processes. Cleanup also runs at launch. A production scheduler/service supervisor is still required when deployed.

## Spreadsheet contract and limits

- CSV: UTF-8, comma separated, header row. A UTF-8 BOM is accepted.
- Excel: XLSX, one worksheet, values only. Save legacy XLS as XLSX first.
- Maximum upload: 5 MB, 1,000 data rows, 40 columns. XLSX expanded archive maximum: 20 MB / 1,000 entries.
- Formulas, VBA payloads, external workbook links, malformed workbooks, duplicate/empty headers, and oversized inputs are rejected.
- Required mapped fields: company_name, primary_contact, primary_email, phone, coverage, document_types.
- Coverage format: CA:Los Angeles County | TX:Travis County. A missing County suffix is accepted where it resolves to a known county. Other county-equivalent names must match the reference.
- Document types use | separators. Working days use Mon|Tue|Wed etc.; hours must be text in HH:MM format. Keep phone numbers as text in Excel to preserve leading zeros.
- Defaults are disclosed in the mapping screen: United States, America/New_York, email, available, normal priority, USD. Missing working days/hours/charges stay unspecified.
- Exports are limited to 5,000 filtered vendors per file. Exports include additional contacts as JSON, activation, demo label, and vendor ID. Those supplemental export columns are not mapped back into imports; add additional contacts through the profile editor.
- Import creates new vendors only. Existing profiles are never silently updated or merged. Similar-but-different names are not fuzzy-merged; primary-email/name matches require manual review.
- Staged rows retain their source row numbers, including blank records that need correction or rejection.

## Reference data

Bundled 2026 U.S. Census Gazetteer: 3,222 counties/county equivalents covering 50 states, DC, and Puerto Rico. Connecticut uses current planning regions. Legacy county names and other territories require deliberate reference/mapping work; VMAS does not guess geographic equivalents.

Source: https://www.census.gov/geographies/reference-files/time-series/geo/gazetteer-files.html
Download: https://www2.census.gov/geo/docs/maps-data/data/gazetteer/2026_Gazetteer/2026_Gaz_counties_national.zip
Text file SHA-256: 0C9BB040B1CDED73B77541CAB8ACAE7B5851FF9C257AD97A1094C0D8ADFAB9C1

The reference is pinned in the repository and requires no live API. Future geographic changes should use reviewed migrations that preserve/audit historical coverage, not an unreviewed replacement of this file.

Document types are an initial seeded catalogue: Deed, Mortgage, Deed of Trust, Lien, Release, Assignment, Judgment, Plat, Tax Record, Court Record, Other. Use Other plus remarks for an unlisted type; confirm the company's preferred vocabulary before importing real data.

Spreadsheet parsing uses pinned PhpSpreadsheet 5.12.0. Official reader documentation: https://phpspreadsheet.readthedocs.io/en/latest/topics/reading-files/

## Verification results

Final full suite: **49 tests passed, 358 assertions** on the isolated vmas_test database and vmas_test user. The application database was not used by tests.

Coverage includes:
- Existing login, sessions, MFA, employee permissions, security headers, CSRF, and immutable audit protections.
- Vendor role boundaries, required fields, duplicate identity, contacts/coverage/document relations, and escaping.
- Activation reasons, revision checks, and preserving stale revisions after a validation redirect.
- State/county correlation, combined filters, CSV formula protection, and XLSX literal cell types.
- Import ownership, required/unique mappings, correction, rejection/restoration, stale requests, cross-batch row protection, expiry, and cancellation.
- Database duplicates introduced after preview prevent the whole commit.
- Repeated commits cannot create duplicate vendors.
- An injected failure on the second vendor rolls back vendors, contacts, row links, and audit entries from the entire commit.
- Malformed/oversized/formula files are rejected and private source files cleaned up.
- XLSX values, CSV template round-trip, blank-row numbering, reference seeder repeatability, and stale-upload cleanup.

Browser acceptance:
- Employee session opened the new directory and filtered CA + Orange County + Mortgage correctly.
- Added an extra contact to Pacific Records Demo and verified the saved profile/history.
- Uploaded docs/samples/vendor-import-review.csv, observed one valid row, one invalid email, and one duplicate.
- Corrected the invalid email, rejected the duplicate, committed two vendors, and observed the summary/inactive profiles.
- Downloaded Excel through the browser and reopened the file: Vendors worksheet, nine rows (header + eight vendors), 27 columns.
- Desktop layout checked at 1440 px; mobile navigation opened/closed at 390 px, with no document-wide horizontal overflow.
- Build, PHP formatting, Blade compilation, schema export, and repeated-startup reuse checks passed.

Screenshots are in docs/screenshots/phase-2-*.jpg.

## Run and review

Open http://127.0.0.1:8000/vendors. The existing employee dummy credentials supplied in chat remain unchanged. Test credentials are not committed to source.

From the VMAS workspace:
    .\application\scripts\start-local.ps1

The server, MySQL, and local cleanup scheduler are currently running. On reboot, rerun the launcher. Use the supplied templates for additional tests. The sample review CSV has already been imported once, so reuploading it now intentionally shows existing-directory duplicates.

## Boundaries before real company use

Phase 2 is ready for local acceptance testing, not certified for production deployment. Workload, vendor recommendations, requests, assignments, communications, retrieved-document attachments, and SLA automation are later phases. No fabricated performance metrics appear.

Before production: replace dummy accounts/data in a clean production environment, turn VMAS_DEMO_MODE off, configure production mail/auth recovery, HTTPS, least-privilege runtime credentials, encrypted disks/backups, backup restoration drills, monitored scheduler/workers, and load/security assessment. Seven-day import retention, document catalogue, supported coverage, required primary email/phone, and duplicate rules should be confirmed against real company files.

Phase 3 starts only after the user's Phase 2 acceptance.
