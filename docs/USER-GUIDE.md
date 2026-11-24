# Using the Phase 1 workspace

## First administrator

Run the local launcher and complete the console prompts. Sign in with that account. Administrators must enroll a TOTP authenticator and save recovery codes before accessing administration. No vendor emails are sent during setup.

## Employees

Open **Employees**, choose **Add employee**, enter name, company email, role, a temporary password, and an onboarding reason. Share that password using your company's approved secure channel. The employee must replace it at first sign-in.

Use **Manage** to change a person's name, role, or active status, with a required reason. Deactivation and role changes invalidate existing database sessions. You cannot deactivate yourself or change your own role. Accounts are retained for audit history; there is no delete action.

## Management

COO accounts can view the organization overview and audit history. They cannot administer employees or alter operational records. They can manage their own account security. Fee approval authority will be a separate permission when the approval module is built.

## Account security

Select your account in the upper-right corner to change your password or manage MFA. A recent password confirmation is required before viewing security settings or recovery codes. Recovery codes work once. Replacing them invalidates the previous set.

The local **Forgot password** flow saves reset messages in the private application log; actual email delivery is not configured. Have the administrator assist with local testing rather than sharing logs.

## Audit history

Administrators and management can filter by action and UTC date range. Expand **View details** for the reason and permitted before/after values. Passwords and MFA secrets are not recorded. There are no edit/delete actions.

## Development scope

Locked sidebar items are future modules, not working features. Vendor records, requests, email, attachments, calls, approvals, SLA automation, and operational analytics will be implemented in later approved phases. Current employee counts and recent activity come from the database.

## Phase 2: vendor management

Open **Vendors** from the navigation. Search by company, contact, email, or phone; combine state, county, document, availability, and activation filters. Changing the state refreshes the county choices. CSV/Excel export includes the current filters.

Choose **Add vendor**, complete the required primary contact information, add county/document entries, and save. New vendors start inactive. VMT can edit profiles and availability; only administrators can activate/deactivate them. Administrators use the profile's controls and provide a reason. Editing a profile also requires a reason. If VMAS reports a newer revision, reload and review the latest profile before trying again.

Alternate contact fields plus **Add another contact** support ten total contacts. Availability describes capacity reported by the vendor; it is separate from activation. Blank charges mean unknown, while zero means zero. Hours use the selected time zone; an earlier end time represents an overnight shift.

**Import vendors** accepts CSV or XLSX. Download a template, replace its example, and keep phone numbers/hours as text. Separate coverage entries with |, for example CA:Orange County | TX:Travis County. Upload, review the preview, and map columns. Correct or reject invalid/duplicate rows. Commit is unavailable until all remaining rows validate. After commit, review imported/rejected counts and activate approved vendors separately.

Imports never update existing vendors. Repeated submission cannot import the same batch twice. Import rows expire after seven days; the file is removed after parsing. You can cancel an uncommitted import to discard its staged rows. Administrators can access all batches; employees can access their own.

Profiles contain readable change history. The current local records labelled DUMMY DATA are fictional. Requests, workload metrics, Gmail, and retrieved-document attachments are not available in this phase.
