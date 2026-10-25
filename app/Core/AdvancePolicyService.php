<?php
namespace App\Core;

/** Shared rules for web, mobile, workflow and accounting advance requests. */
final class AdvancePolicyService
{
    public const CODES = ['hr_advance', 'hr_exceptional_advance', 'hr_long_advance'];

    public static function isAdvance(string $code): bool { return in_array($code, self::CODES, true); }

    public static function canManageHr(int $userId): bool
    {
        return (bool) Database::query("SELECT 1 FROM users u JOIN roles r ON r.id=u.role_id
            WHERE u.id=? AND (r.name IN ('مدير النظام','مدير قسم الموارد البشرية') OR EXISTS
            (SELECT 1 FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id
             WHERE rp.role_id=u.role_id AND p.code='hr.manage'))", [$userId])->fetchColumn();
    }

    public static function assertApplicant(string $code, int $employeeId, int $userId): void
    {
        if (!self::isAdvance($code)) return;
        $ownId = (int) Database::query('SELECT id FROM employees WHERE user_id=?', [$userId])->fetchColumn();
        if (($code !== 'hr_advance' || $employeeId !== $ownId) && !self::canManageHr($userId)) {
            throw new \RuntimeException('تقديم الطلب للموارد البشرية فقط؛ السلفة الشهرية متاحة للموظف نفسه');
        }
    }

    public static function canViewFinancial(int $employeeId, int $userId): bool
    {
        if (self::canManageHr($userId)) return true;
        if (Database::query('SELECT id FROM employees WHERE id=? AND user_id=?', [$employeeId,$userId])->fetchColumn()) return true;
        if (Database::query("SELECT 1 FROM users u JOIN role_permissions rp ON rp.role_id=u.role_id JOIN permissions p ON p.id=rp.permission_id WHERE u.id=? AND p.code IN ('accounting.view','accounting.manage','payroll.manage')", [$userId])->fetchColumn()) return true;
        $position = Database::query('SELECT id FROM org_positions WHERE employee_id=? AND is_active=1 ORDER BY id LIMIT 1', [$employeeId])->fetchColumn();
        $request = ['requester_employee_id'=>$employeeId,'requester_position_id'=>$position];
        foreach (['direct_manager','manager_of_manager'] as $type) if (in_array($userId, array_map('intval', \App\Core\Workflow\ApproverResolver::resolve(['type'=>$type], $request)), true)) return true;
        return false;
    }

    /** No rounded payroll net: remove the monthly advance deduction before replacing it with the entire debt. */
    public static function amounts(float $salary, array $payroll, float $debt, float $reserved = 0): array
    {
        $deductions = max(0, (float)($payroll['total_deductions'] ?? 0)
            + (float)($payroll['deficit_amount'] ?? 0) + (float)($payroll['rounding_adjustment'] ?? 0)
            - (float)($payroll['advances'] ?? 0));
        $earnings = (float)($payroll['total_earnings'] ?? 0);
        $net = round($earnings - $deductions - $debt, 2);
        $max = round(min($salary * .25, max(0, $net)), 2);
        return ['salary' => $salary, 'salary_cap' => round($salary * .25, 2), 'accrued_earnings' => $earnings,
            'deductions' => round($deductions, 2), 'outstanding_debt' => round($debt, 2), 'net_entitlement' => $net,
            'max' => $max, 'used' => round($reserved, 2), 'remaining' => max(0.0, round($max - $reserved, 2))];
    }

    public static function snapshot(int $employeeId, ?int $excludeRequest = null, ?string $date = null): array
    {
        $date = $date ?: date('Y-m-d');
        $period = substr($date, 0, 7);
        $emp = Database::query('SELECT base_salary FROM employees WHERE id=?', [$employeeId])->fetch();
        $payroll = HrService::calculatePayrollComponents($employeeId, $period, null, $date, true);
        // remaining_amount is the canonical outstanding balance, maintained as installments are repaid.
        $debt = (float) Database::query("SELECT COALESCE(SUM(GREATEST(0,remaining_amount)),0)
            FROM salary_advances WHERE employee_id=? AND status IN ('paid','settled')", [$employeeId])->fetchColumn();
        // Reserve approved/pending requests too, including requests made on behalf of the employee by HR.
        $reserved = (float) Database::query("SELECT COALESCE(SUM(r.amount),0) FROM requests r
            JOIN approval_templates t ON t.id=r.template_id
            WHERE t.code IN ('hr_advance','hr_exceptional_advance') AND r.requester_employee_id=?
            AND r.status IN ('pending','approved','returned_for_edit') AND r.id<>?
            AND NOT EXISTS (SELECT 1 FROM hr_workflow_links l JOIN salary_advances a ON a.id=l.entity_id
                WHERE l.request_id=r.id AND l.entity_type='salary_advance' AND a.status IN ('paid','settled','rejected'))",
            [$employeeId, $excludeRequest ?: 0])->fetchColumn();
        $out = self::amounts((float)($emp['base_salary'] ?? 0), $payroll, $debt, $reserved);
        $out['previous_advances'] = Database::query("SELECT a.id,a.amount,a.advance_type,a.installments,a.installment_amount,a.paid_amount,a.remaining_amount,a.status,
            (SELECT COUNT(*) FROM advance_repayment_schedule s WHERE s.advance_id=a.id AND s.status='pending') AS remaining_installments,
            (SELECT COALESCE(SUM(s.amount),0) FROM advance_repayment_schedule s WHERE s.advance_id=a.id AND s.status='pending') AS scheduled_remaining
            FROM salary_advances a WHERE a.employee_id=? AND a.status IN ('paid','settled') ORDER BY a.id DESC", [$employeeId])->fetchAll();
        $out['working_days'] = (int)($payroll['working_days'] ?? 0);
        $out['leave_days'] = (int)($payroll['leave_days'] ?? 0);
        $out['as_of_date'] = $date;
        $out['monthly_open'] = (int)substr($date, 8, 2) >= 13;
        if (!$payroll || $out['salary'] <= 0) $out['error'] = 'ملف الموظف غير مفتوح أو الراتب الأساسي غير محدد';
        return $out;
    }

    public static function assertRules(string $code, float $amount, array $snapshot, string $date, bool $duplicate, string $reason, int $installments): void
    {
        if (!is_finite($amount) || $amount < .01 || $amount !== round($amount, 2) || $amount > 9999999999.99) throw new \RuntimeException('أدخل مبلغاً موجباً صالحاً بمنزلتين عشريتين كحد أقصى');
        if (!empty($snapshot['error'])) throw new \RuntimeException($snapshot['error']);
        if ($code === 'hr_advance' && (int)substr($date, 8, 2) < 13) throw new \RuntimeException('يبدأ تقديم السلفة الشهرية من يوم 13');
        if ($code !== 'hr_long_advance' && $duplicate) throw new \RuntimeException('يوجد طلب سلفة شهرية أو استثنائية قائم لهذا الشهر؛ المرفوض والملغى لا يمنعان طلباً بديلاً');
        if ($code !== 'hr_long_advance' && $amount > $snapshot['remaining']) throw new \RuntimeException('المبلغ يتجاوز الأقل من 25% من الراتب وصافي الاستحقاق بعد المديونية والاستقطاعات');
        if ($code !== 'hr_advance' && trim($reason) === '') throw new \RuntimeException('سبب طلب السلفة مطلوب');
        if ($code === 'hr_long_advance' && $installments < 2) throw new \RuntimeException('السلفة طويلة الأجل تتطلب قسطين على الأقل');
        if ($code !== 'hr_long_advance' && $installments !== 1) throw new \RuntimeException('السلفة الشهرية والاستثنائية تُسدد بقسط واحد');
    }

    public static function validate(string $code, array $payload, int $employeeId, ?int $requestId = null): array
    {
        if (!$employeeId || !HrService::isEmployeeFileOpen($employeeId)) throw new \RuntimeException('ملف الموظف غير مفتوح');
        $date = date('Y-m-d');
        $start = date('Y-m-01'); $end = date('Y-m-01', strtotime('+1 month'));
        $duplicate = (bool) Database::query("SELECT 1 FROM requests r JOIN approval_templates t ON t.id=r.template_id
            WHERE t.code IN ('hr_advance','hr_exceptional_advance') AND r.requester_employee_id=? AND r.id<>?
            AND r.status IN ('pending','approved','returned_for_edit') AND COALESCE(r.advance_policy_period,DATE_FORMAT(r.created_at,'%Y-%m'))=? LIMIT 1",
            [$employeeId, $requestId ?: 0, substr($date,0,7)])->fetchColumn();
        // Legacy advances without a workflow link also consume the month's entitlement.
        $duplicate = $duplicate || (bool) Database::query("SELECT 1 FROM salary_advances a
            WHERE employee_id=? AND advance_type='short' AND source_payroll_id IS NULL
            AND status IN ('pending','approved','paid','settled') AND created_at>=? AND created_at<?
            AND NOT EXISTS (SELECT 1 FROM hr_workflow_links l WHERE l.entity_type='salary_advance' AND l.entity_id=a.id)
            AND id<>? LIMIT 1", [$employeeId, $start, $end, (int)($payload['entity_id'] ?? 0)])->fetchColumn();
        $count = (float)($payload['installments'] ?? 1);
        if ($count !== floor($count) || $count < 1 || $count > (float)($payload['amount'] ?? 0) * 100) throw new \RuntimeException('عدد الأقساط يجب أن يكون صحيحاً وقيمة كل قسط لا تقل عن 0.01');
        $snapshot = self::snapshot($employeeId, $requestId);
        self::assertRules($code, (float)($payload['amount'] ?? 0), $snapshot, $date, $duplicate,
            (string)($payload['reason'] ?? ''), (int)($payload['installments'] ?? 1));
        return $snapshot;
    }

    /** Serialize all advance mutations for an employee, also when called inside an existing transaction. */
    public static function locked(int $employeeId, callable $action): mixed
    {
        $db = Database::connect(); $own = !$db->inTransaction();
        if ($own) $db->beginTransaction();
        try {
            Database::query('SELECT id FROM employees WHERE id=? FOR UPDATE', [$employeeId])->fetch();
            $result = $action();
            if ($own) $db->commit();
            return $result;
        } catch (\Throwable $e) { if ($own && $db->inTransaction()) $db->rollBack(); throw $e; }
    }

    /** Link generic workflow requests to the same ledger used by accounting and payroll. */
    public static function syncRequest(array $request, ?array $snapshot = null): void
    {
        $payload = $request['data']; $employeeId = (int)$request['requester_employee_id'];
        $long = $request['template_code'] === 'hr_long_advance';
        $link = Database::query("SELECT entity_id FROM hr_workflow_links WHERE entity_type='salary_advance' AND request_id=?", [$request['id']])->fetchColumn();
        $id = (int)($link ?: ($payload['entity_id'] ?? 0));
        if ($id) {
            $a = Database::query('SELECT * FROM salary_advances WHERE id=? FOR UPDATE', [$id])->fetch();
            if (!$a || (int)$a['employee_id'] !== $employeeId || in_array($a['status'], ['paid','settled'], true)) throw new \RuntimeException('لا يمكن تعديل سلفة مصروفة أو غير مرتبطة بالموظف');
        }
        $installments = $long ? (int)($payload['installments'] ?? 2) : 1;
        if (!$id) {
            Database::query("INSERT INTO salary_advances (employee_id,amount,advance_type,installments,reason,remaining_amount,request_source,is_long_term)
                VALUES (?,?,?,?,?,?,'workflow',?)", [$employeeId, $request['amount'], $long ? 'long' : 'short', $installments, $payload['reason'] ?? '', $request['amount'], $long ? 1 : 0]);
            $id = (int)Database::lastInsertId();
        } else {
            Database::query("UPDATE salary_advances SET amount=?,remaining_amount=?,advance_type=?,installments=?,reason=?,is_long_term=?,status='pending',accountant_approved_at=NULL,accountant_approved_by=NULL WHERE id=?",
                [$request['amount'], $request['amount'], $long ? 'long' : 'short', $installments, $payload['reason'] ?? '', $long ? 1 : 0, $id]);
        }
        if ($long && (!isset($a) || (float)$a['amount'] !== (float)$request['amount'] || (int)$a['installments'] !== $installments
            || ((float)($a['installment_amount'] ?? 0) > 0 && (float)$a['installment_amount'] * ($installments - 1) >= (float)$request['amount']))) {
            Database::query("UPDATE salary_advances SET installment_method='fixed',installment_percentage=NULL,installment_amount=? WHERE id=?", [floor((float)$request['amount'] / $installments * 100) / 100, $id]);
        }
        Database::query("INSERT INTO hr_workflow_links (entity_type,entity_id,request_id) VALUES ('salary_advance',?,?) ON DUPLICATE KEY UPDATE request_id=VALUES(request_id)", [$id, $request['id']]);
        $payload['entity_type'] = 'salary_advance'; $payload['entity_id'] = $id;
        if ($snapshot !== null) $payload['advance_snapshot'] = $snapshot;
        Database::query('UPDATE requests SET data_json=? WHERE id=?', [json_encode($payload, JSON_UNESCAPED_UNICODE), $request['id']]);
    }
}
