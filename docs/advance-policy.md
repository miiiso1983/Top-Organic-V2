# Employee advance policy (2026-10-26)

- Monthly requests open on the 13th, in Asia/Baghdad. The cap is the smaller of 25% of base salary and accrued entitlement minus non-advance deductions and all outstanding advance debt. Pending requests reserve available funds.
- Accrual uses biometric workdays and the existing paid-leave entitlement, including overnight workday pairing. Payroll rounding and deficit normalization are reversed before calculating eligibility. The current month's installment is removed from payroll deductions before replacing it with the entire remaining debt, preventing double subtraction.
- Monthly and exceptional requests share one active request per employee per submission month. Rejected and cancelled requests do not consume that allowance. Exceptional requests waive the opening date only, and require HR submission and a reason.
- Long-term requests require HR submission, a reason, and at least two installments. Their approval path is distinct; the monthly 25% cap is not applied to the long-term principal.
- Monthly: direct manager, HR. Exceptional: direct manager, manager's manager, Finance. Long-term: direct manager, manager's manager, HR. Missing required approvers block progression. Existing requests retain their original approval history; new submissions use the new versioned routes.
- After final approval, accounting explicitly authorizes disbursement. This does not post a journal. Cash/bank delivery posts one balanced journal, records the payer/date, and exposes the printable receipt. A failed posting rolls back payment. Employee/request/advance row locks serialize submissions, edits, decisions and disbursement.
- API and web workflows use the same server rules. Existing mobile clients receive the updated cap and server validation; native application binaries are not changed by this deployment.

## Deployment

Back up changed files and advance template/graph configuration. Add the nullable `accountant_approved_at`, `accountant_approved_by` and `advance_policy_period` columns before deploying the PHP files. Run `scripts/migrate_advance_policy.php` via the application's CLI bootstrap afterward. It creates the three versioned approval graphs and HR creation permissions idempotently. It does not approve requests, pay advances, alter previous cash movements, or regenerate existing payrolls. Outstanding approved requests require accounting authorization before payment.

## Validation

- `php tests/AdvancePolicyTest.php`
- `tests/AdvanceWorkflowIntegrationTest.php`, through a CLI bootstrap with the candidate classes taking autoload priority. It shadows **all** application tables with empty connection-scoped temporary tables, seeds synthetic records and disables notifications. It tests real payroll accrual, all three workflows, replacements, returned edits, authorization, accounting rollback, duplicate delivery, balanced journals, positive installment totals and receipts. No real employee rows are copied or modified.
- Existing attendance, department-report and synthetic Excel-export regressions.
