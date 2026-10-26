# Employee actions and records

Web: `/hr/team`, `/hr/notices`, `/hr/warnings`, `/hr/transfers`, `/hr/photo-loadings`.
Mobile: the “الموظفون وسجلي الوظيفي” menu and employee approvals shortcut, available in Android/iOS 1.3.0 (59).

`HrTeamService` authorizes both `/hr/team-data/*` (session + CSRF on writes) and `/api/hr-team/*` (bearer token). The employee identifier is fixed by the endpoint and rechecked at submission. There is no offline queue for these mutations.

## Policy

- Direct managers operate on their direct reports. `hr.team.all` delegates HR access across employees. New create permissions are separately editable: `hr.team.notice`, `hr.team.notice_request`, `hr.team.warning`, `hr.team.photo_loading`, `hr.team.transfer`.
- Notice requests notify the manager's manager and HR for information. Direct notices notify the employee.
- Warnings require the employee's manager's manager to approve; only then is the employee notified and a legacy `employee_warnings` row created. Employee reading is recorded without requiring acknowledgement. Pending/rejected warnings are hidden from their subject.
- Transfers require acceptance by the new manager, then `hr.team.transfer.approve`. Same-department, active manager, duplicate pending request and cycle checks run again at approval. The employee manager and active organizational parent change atomically. The transfer records its effective timestamp and requests shift review.
- Pending workflow approvers are frozen at transfer time, including future nodes of existing graph requests and legacy steps. Existing tasks, financial rows, payroll periods and employee IDs are retained.
- Photo loadings have up to five validated JPEG/PNG/WebP images (5MB each), stored privately and retrieved through authorized endpoints. They do not create financial deductions.
- Payslips and financial archive entries are limited to their employee and holders of `hr.payslip.view`. Team membership alone does not grant financial access.
- Existing leave, missing punch, deductions, overtime, suspension, exceptional/long advances, shift assignment and evaluation use existing domain services. Advance restrictions remain unchanged.
- Requests use client retry keys. Decisions are serialized, audited, and cannot be applied twice. Lists paginate at 10/25/50/100 rows. Case Excel exports include all matching, authorized results with explicit string cells.

## Migration and deployment

Run `scripts/migrate_hr_team.php` through the normal CLI configuration/autoload bootstrap before exposing routes. It creates additive tables, seeds **new** permissions once (reruns preserve revoked permissions), and includes historical warnings in reports without resending notifications. No financial ledger is rewritten.

Deploy new service classes before routes and existing controllers; retain file backups and verify hashes. The changes in `WorkflowEngine` and `Workflow/ApproverResolver` preserve existing request approvers after a transfer; their lookup tolerates the pre-migration absence of the snapshot table.

## Verification

`tests/HrTeamIntegrationTest.php` runs on a connection with all real application tables shadowed by empty temporary tables; new migration tables are also temporary. It stubs notifications and uses synthetic fixtures. Tests cover authorization/revocation, warning approval and privacy, idempotency, private attachments, transfer acceptance/HR approval, cycles/departments, old approvers, financial history, existing request ledgers, evaluation, shifts, advances, pagination and complete export selection.

Run the original `AdvanceWorkflowIntegrationTest.php` regression suite too. Flutter tests cover narrow Android/iOS layouts, large text, server-filtered actions, pagination and form validation, alongside existing evaluation/advance tests. Real device installation and store publication are separate distribution steps.

## Housing and payroll display follow-up — 1.3.1 (60)

Housing readers in the security department and existing `hr.housing.manage` holders see residents across all departments, including closed employee files with an active housing assignment. External housing is excluded from the resident-only view. Android/iOS default to residents only, with an optional all-employee view and search. A per-row `can_edit` flag matches server enforcement: security can still edit only open files in its own department; existing housing administrators retain their management scope. Security-only viewers do not receive deduction amounts. Web security viewers receive a separate resident list and a sidebar link, while administrators keep the housing management page.

`PayrollPresentation` supplies 19 canonical column labels and display values to web payroll, self payslips and authorized employee payslips. It shares the existing display calculation for monthly base, requested overtime days and remaining value. Withheld deposits map to `security_deposit`, matching web. Stored totals and financial postings are unchanged. Mobile shows the same columns as responsive cards, including zero-valued items, days/amount pairs and status. Older servers retain the previous client display fallback.

`HousingPayrollIntegrationTest.php` extends the temporary-table-only HR suite with cross-department housing, closed resident files, external housing exclusion, unchanged editing scope, financial privacy, and payroll projection checks. `housing_payroll_screen_test.dart` adds six Android/iOS widget cases for large text and both self/authorized payslip endpoints.
