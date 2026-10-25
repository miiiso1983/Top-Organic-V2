# Mobile advances — 1.2.7 (57)

Android and iOS use the same server policy and distinct workflow templates.

| Operation | Access |
| --- | --- |
| Monthly request | Linked employee, for their own record; applications open on day 13 |
| Exceptional / long-term request | HR capability plus the template's create permission |
| View a request | Requester, beneficiary, authorized financial/HR users, managers or current delegated approver |
| Approve / return / reject | Current workflow task assignee; future stages have no decision button |
| Authorize payment | `accounting.manage` after workflow approval |
| Deliver payment | `accounting.manage` or `payroll.manage`, after accountant authorization |
| Print receipt | Same cash-desk permissions, only after actual payment |

Monthly and exceptional requests use the smaller of 25% of salary and accrued net
entitlement after all outstanding debt and deductions, less existing reservations.
Long-term advances have their own approval route and at least two installments.
Rejected/cancelled monthly requests do not prevent a replacement. The server is
always authoritative, including when a client has stale permissions or balances.

The cash-desk button is in the approvals screen for authorized accounts. Requests
submitted by HR also appear in the beneficiary's own requests/archive. Request
details distinguish workflow approval, accountant authorization and actual payment.

## Compatibility

Older clients that submit `advance_type=long` or `طويلة الأجل` through the exceptional
template are mapped to the long-term template on the server. They still require
HR authorization. Install version 1.2.7 (57) to get the corrected forms, financial
breakdown, cash desk and Arabic PDF receipt/print controls.

Both `config/routes.php` (production entry point) and the root route mirror include
the new authenticated API endpoints. No database migration is needed for this
mobile patch; the advance-policy migration must already be installed.

## Validation

- `php tests/AdvancePolicyTest.php`
- `php tests/AttendanceOvernightTest.php`
- `php tests/HrReportDepartmentTest.php`
- `flutter test test/advance_policy_test.dart` from `flutter_app`
- `tests/AdvanceWorkflowIntegrationTest.php`, with the application bootstrap and
  a fresh MySQL connection: shadows all application tables with empty TEMPORARY
  tables, seeds synthetic actors and exercises the real controllers/services.
  Do not remove the temporary-table isolation or use real employee fixtures.

The mobile integration cases cover template visibility, legacy client routing,
current/future approval stages, beneficiary visibility, unrelated-user denial,
accountant authorization, duplicate payment prevention and paid-only receipts.
The Flutter tests cover amount/date rules, both platform themes and Arabic PDF
creation. Physical-device printing and store distribution require a device/store
session; successful builds do not update installed phones automatically.

Arabic receipts bundle Amiri under its accompanying SIL Open Font License.
