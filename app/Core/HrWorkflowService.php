<?php
namespace App\Core;

use App\Core\Workflow\EntityRegistry;

/**
 * ربط عمليات الموارد البشرية بمحرك Workflow الديناميكي
 */
class HrWorkflowService
{
    private const TEMPLATE_MAP = [
        'leave_request' => 'hr_leave',
        'salary_advance' => 'hr_advance',
        'hr_adjustment' => 'hr_adjustment',
        'overtime_request' => 'hr_overtime',
        'missing_attendance_request' => 'hr_missing_attendance',
    ];

    public static function register(): void
    {
        WorkflowEngine::registerOutcomeHandler('hr_leave', [self::class, 'onLeaveOutcome']);
        WorkflowEngine::registerOutcomeHandler('hr_advance', [self::class, 'onAdvanceOutcome']);
        WorkflowEngine::registerOutcomeHandler('hr_adjustment', [self::class, 'onAdjustmentOutcome']);
        WorkflowEngine::registerOutcomeHandler('hr_overtime', [self::class, 'onOvertimeOutcome']);
        WorkflowEngine::registerStepHandler('hr_overtime', [self::class, 'onOvertimeStep']);
        WorkflowEngine::registerOutcomeHandler('hr_missing_attendance', [self::class, 'onMissingAttendanceOutcome']);
        WorkflowEngine::registerStepHandler('hr_missing_attendance', [self::class, 'onMissingAttendanceStep']);

        WorkflowEngine::registerOutcomeHandler('hiring_request', [self::class, 'onHiringOutcome']);
        WorkflowEngine::registerStepHandler('hiring_request', [self::class, 'onHiringStep']);
        WorkflowEngine::registerOutcomeHandler('suspension_request', [self::class, 'onSuspensionOutcome']);
        WorkflowEngine::registerStepHandler('suspension_request', [self::class, 'onSuspensionStep']);
        WorkflowEngine::registerOutcomeHandler('deduction_request', [self::class, 'onDeductionRequestOutcome']);
    }

    public static function onDeductionRequestOutcome(array $request, string $status, int $actorId, string $notes): void
    {
        $requestId = (int)($request['id'] ?? 0);
        $link = EntityRegistry::requestLink($requestId);
        $entityId = (int)($link['entity_id'] ?? 0);
        if (!$entityId) {
            $payload = (array)($request['data'] ?? []);
            $entityId = (int)($payload['entity_id'] ?? 0);
        }
        error_log("[HR Workflow] Deduction request outcome: request_id={$requestId} status={$status} entity_id={$entityId}");
        if (!$entityId) {
            return;
        }
        $decision = $status === 'approved' ? 'approved' : 'rejected';
        HrService::decideAdjustment($entityId, $decision, $actorId);
    }

    public static function request(string $entityType, int $entityId, int $userId, array $context = []): ?int
    {
        $code = self::TEMPLATE_MAP[$entityType] ?? null;
        if (!$code) return null;
        $template = WorkflowTemplateService::getTemplateByCode($code);
        if (!$template) return null;

        $data = array_merge($context, [
            'title' => $context['title'] ?? ($template['name'] . ' - #' . $entityId),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ]);
        if (isset($context['amount'])) {
            $data[$template['amount_field'] ?: 'amount'] = (float)$context['amount'];
        }

        $requestId = WorkflowEngine::createRequest((int)$template['id'], $userId, $data, true);

        if (!empty($context['employee_id'])) {
            $emp = Database::query(
                "SELECT e.id, op.id AS position_id FROM employees e
                 LEFT JOIN org_positions op ON op.employee_id = e.id
                 WHERE e.id = ?",
                [(int)$context['employee_id']]
            )->fetch();
            if ($emp) {
                Database::query(
                    "UPDATE requests SET requester_employee_id = ?, requester_position_id = ? WHERE id = ?",
                    [(int)$emp['id'], $emp['position_id'] ?: null, $requestId]
                );
            }
        }

        $req = Database::query("SELECT data_json FROM requests WHERE id = ?", [$requestId])->fetch();
        $payload = json_decode((string)($req['data_json'] ?? '{}'), true) ?: [];
        $payload['entity_type'] = $entityType;
        $payload['entity_id'] = $entityId;
        foreach ($context as $k => $v) {
            if (!isset($payload[$k])) $payload[$k] = $v;
        }
        Database::query(
            "UPDATE requests SET data_json = ? WHERE id = ?",
            [json_encode($payload, JSON_UNESCAPED_UNICODE), $requestId]
        );

        WorkflowEngine::submitRequest($requestId, $userId);

        Database::query(
            "INSERT INTO hr_workflow_links (entity_type, entity_id, request_id) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE request_id = VALUES(request_id)",
            [$entityType, $entityId, $requestId]
        );

        return $requestId;
    }

    public static function workflowRequestId(string $entityType, int $entityId): ?int
    {
        $row = Database::query(
            "SELECT request_id FROM hr_workflow_links WHERE entity_type = ? AND entity_id = ?",
            [$entityType, $entityId]
        )->fetch();
        return $row ? (int)$row['request_id'] : null;
    }

    public static function onLeaveOutcome(array $request, string $status, int $actorId, string $notes): void
    {
        $entityId = (int)($request['data']['entity_id'] ?? 0);
        if (!$entityId) return;
        Database::query(
            "UPDATE leave_requests SET status = ?, approved_by = ?, approved_at = NOW() WHERE id = ?",
            [$status, $actorId, $entityId]
        );
        if ($status === 'approved') {
            HrService::applyLeaveApproval($entityId);
        } elseif ($status === 'rejected') {
            HrService::revertLeaveApproval($entityId);
        }
        self::notifyEmployee($request, $status);
    }

    public static function onAdvanceOutcome(array $request, string $status, int $actorId, string $notes): void
    {
        $entityId = (int)($request['data']['entity_id'] ?? 0);
        if (!$entityId) return;
        Database::query(
            "UPDATE salary_advances SET status = ?, approved_by = ?, approved_at = NOW() WHERE id = ?",
            [$status, $actorId, $entityId]
        );
        if ($status === 'approved') {
            HrService::generateRepaymentSchedule($entityId);
        }
        self::notifyEmployee($request, $status);
    }

    public static function onAdjustmentOutcome(array $request, string $status, int $actorId, string $notes): void
    {
        $entityId = (int)($request['data']['entity_id'] ?? 0);
        if (!$entityId) return;
        HrService::decideAdjustment($entityId, $status, $actorId);
    }

    private static function notifyEmployee(array $request, string $status): void
    {
        $employeeId = (int)($request['data']['employee_id'] ?? 0);
        if (!$employeeId) return;
        $emp = Database::query("SELECT user_id, full_name FROM employees WHERE id = ?", [$employeeId])->fetch();
        if (!$emp || !$emp['user_id']) return;
        $labels = ['approved' => 'موافقة', 'rejected' => 'رفض', 'returned_for_edit' => 'إعادة للتعديل'];
        Helpers::notify(
            (int)$emp['user_id'],
            'نتيجة ' . ($request['template_name'] ?? 'الطلب'),
            'تم ' . ($labels[$status] ?? $status) . ' على طلبك',
            '/workflows/requests/' . (int)$request['id']
        );
    }

    public static function onHiringStep(array $request, array $step, int $actorId, string $notes): void
    {
        $requestId = (int)$request['id'];
        $order = (int)($step['step_order'] ?? 0);
        $row = self::ensureHiringRequest($request);
        $field = $order === 1 ? 'manager' : ($order === 2 ? 'hr' : 'finance');
        Database::query(
            "UPDATE hiring_requests
             SET {$field}_status = 'approved', {$field}_approved_by = ?, {$field}_notes = ?, {$field}_at = NOW()
             WHERE id = ?",
            [$actorId, $notes, $row['id']]
        );
    }

    public static function onHiringOutcome(array $request, string $status, int $actorId, string $notes): void
    {
        $row = self::ensureHiringRequest($request);
        Database::query(
            "UPDATE hiring_requests SET overall_status = ?, updated_at = NOW() WHERE id = ?",
            [$status, $row['id']]
        );
        if ($status === 'approved') {
            self::notifyHrAboutHiring($request, $row['id']);
        }
    }

    public static function onSuspensionStep(array $request, array $step, int $actorId, string $notes): void
    {
        $requestId = (int)$request['id'];
        $order = (int)($step['step_order'] ?? 0);
        $row = self::ensureSuspensionRequest($request);
        $field = $order === 1 ? 'manager' : ($order === 2 ? 'hr' : 'finance');
        Database::query(
            "UPDATE suspension_requests
             SET {$field}_status = 'approved', {$field}_approved_by = ?, {$field}_notes = ?, {$field}_at = NOW()
             WHERE id = ?",
            [$actorId, $notes, $row['id']]
        );
    }

    public static function onSuspensionOutcome(array $request, string $status, int $actorId, string $notes): void
    {
        $row = self::ensureSuspensionRequest($request);
        Database::query(
            "UPDATE suspension_requests SET overall_status = ?, updated_at = NOW() WHERE id = ?",
            [$status, $row['id']]
        );
        $employeeId = (int)($request['data']['employee_id'] ?? 0);
        if ($status === 'approved' && $employeeId) {
            Database::query(
                "UPDATE employees SET status = 'suspended' WHERE id = ?",
                [$employeeId]
            );
            self::notifyEmployee($request, $status);
            self::notifyHrAboutSuspension($request, $row['id']);
        } elseif ($status === 'rejected' && $employeeId) {
            self::notifyEmployee($request, $status);
        }
    }

    private static function ensureHiringRequest(array $request): array
    {
        $requestId = (int)$request['id'];
        $row = Database::query(
            "SELECT * FROM hiring_requests WHERE workflow_request_id = ? LIMIT 1",
            [$requestId]
        )->fetch();
        if ($row) return $row;
        $data = $request['data'] ?? [];
        $requesterEmployeeId = (int)($request['requester_employee_id'] ?? 0);
        $requesterId = $requesterEmployeeId ?: (int)($request['requester_id'] ?? 0);
        $managerId = (int)($request['requester_employee_id'] ?? 0);
        $createdBy = (int)($request['requester_id'] ?? 0);
        $departmentId = (int)($data['department_id'] ?? 0);
        $departmentName = '';
        if ($departmentId) {
            $d = Database::query("SELECT name FROM departments WHERE id = ?", [$departmentId])->fetch();
            $departmentName = $d['name'] ?? '';
        }
        Database::query(
            "INSERT INTO hiring_requests
             (workflow_request_id, request_no, requester_id, manager_id, department_id, job_title, department_name,
              expected_salary, reason, notes, manager_status, hr_status, finance_status, overall_status, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?, 'pending','pending','pending','pending',?)",
            [
                $requestId, $request['request_no'] ?? ('H-' . $requestId), $requesterId, $managerId ?: null,
                $departmentId ?: null, $data['job_title'] ?? '', $departmentName,
                (float)($data['expected_salary'] ?? 0), $data['reason'] ?? '', $data['notes'] ?? '', $createdBy ?: null
            ]
        );
        return Database::query("SELECT * FROM hiring_requests WHERE id = ?", [Database::lastInsertId()])->fetch();
    }

    private static function ensureSuspensionRequest(array $request): array
    {
        $requestId = (int)$request['id'];
        $row = Database::query(
            "SELECT * FROM suspension_requests WHERE workflow_request_id = ? LIMIT 1",
            [$requestId]
        )->fetch();
        if ($row) return $row;
        $data = $request['data'] ?? [];
        $requesterEmployeeId = (int)($request['requester_employee_id'] ?? 0);
        $requesterId = $requesterEmployeeId ?: (int)($request['requester_id'] ?? 0);
        $managerId = (int)($request['requester_employee_id'] ?? 0);
        $createdBy = (int)($request['requester_id'] ?? 0);
        $employeeId = (int)($data['employee_id'] ?? 0);
        $typeMap = ['مؤقت' => 'temporary', 'نهائي' => 'permanent'];
        $type = $typeMap[$data['suspension_type'] ?? ''] ?? 'temporary';
        Database::query(
            "INSERT INTO suspension_requests
             (workflow_request_id, request_no, requester_id, manager_id, employee_id, suspension_date,
              suspension_type, reason, notes, manager_status, hr_status, finance_status, overall_status, created_by)
             VALUES (?,?,?,?,?,?,?,?,?, 'pending','pending','pending','pending',?)",
            [
                $requestId, $request['request_no'] ?? ('S-' . $requestId), $requesterId, $managerId ?: null,
                $employeeId, $data['suspension_date'] ?? null, $type, $data['reason'] ?? '', $data['notes'] ?? '',
                $createdBy ?: null
            ]
        );
        return Database::query("SELECT * FROM suspension_requests WHERE id = ?", [Database::lastInsertId()])->fetch();
    }

    private static function notifyHrAboutHiring(array $request, int $hiringRequestId): void
    {
        $data = $request['data'] ?? [];
        $title = 'طلب تعيين جديد معتمد';
        $body = 'تمت الموافقة النهائية على طلب تعيين: ' . ($data['job_title'] ?? '') . ' — يرجى إكمال إجراءات التعيين';
        $link = '/hr/hiring-requests/' . $hiringRequestId;
        $hrRoleId = (int)(Database::query("SELECT id FROM roles WHERE name = 'مدير موارد بشرية' LIMIT 1")->fetchColumn() ?? 0);
        if ($hrRoleId) {
            Helpers::notifyRole($hrRoleId, $title, $body, $link);
        }
        $users = Database::query(
            "SELECT DISTINCT u.id FROM users u
             JOIN role_permissions rp ON rp.role_id = u.role_id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE p.code = 'hr.view' AND u.is_active = 1"
        )->fetchAll();
        foreach ($users as $u) {
            Helpers::notify((int)$u['id'], $title, $body, $link);
        }
    }

    private static function notifyHrAboutSuspension(array $request, int $suspensionRequestId): void
    {
        $data = $request['data'] ?? [];
        $employeeId = (int)($data['employee_id'] ?? 0);
        $emp = $employeeId ? Database::query("SELECT full_name FROM employees WHERE id = ?", [$employeeId])->fetch() : null;
        $title = 'إيقاف دوام معتمد';
        $body = 'تمت الموافقة على إيقاف دوام الموظف: ' . ($emp['full_name'] ?? '') . ' (' . ($data['suspension_type'] ?? '') . ')';
        $link = '/hr/suspension-requests/' . $suspensionRequestId;
        $hrRoleId = (int)(Database::query("SELECT id FROM roles WHERE name = 'مدير موارد بشرية' LIMIT 1")->fetchColumn() ?? 0);
        if ($hrRoleId) {
            Helpers::notifyRole($hrRoleId, $title, $body, $link);
        }
        $users = Database::query(
            "SELECT DISTINCT u.id FROM users u
             JOIN role_permissions rp ON rp.role_id = u.role_id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE p.code = 'hr.view' AND u.is_active = 1"
        )->fetchAll();
        foreach ($users as $u) {
            Helpers::notify((int)$u['id'], $title, $body, $link);
        }
    }

    public static function onOvertimeStep(array $request, array $step, int $actorId, string $notes): void
    {
        $overtimeId = (int)($request['data']['entity_id'] ?? 0);
        if (!$overtimeId) return;
        $stepOrder = (int)($step['step_order'] ?? 0);
        if ($stepOrder === 1) {
            Database::query(
                "UPDATE overtime_requests
                 SET status = 'manager_approved', manager_approved_by = ?, manager_approved_at = NOW(),
                     rejected_by = NULL, rejected_at = NULL, rejected_notes = NULL
                 WHERE id = ?",
                [$actorId, $overtimeId]
            );
        } elseif ($stepOrder === 2) {
            HrService::recomputeOvertimeBiometricMatch($overtimeId);
            Database::query(
                "UPDATE overtime_requests
                 SET status = 'fully_approved', grand_manager_approved_by = ?, grand_manager_approved_at = NOW(),
                     rejected_by = NULL, rejected_at = NULL, rejected_notes = NULL
                 WHERE id = ?",
                [$actorId, $overtimeId]
            );
            $req = Database::query("SELECT * FROM overtime_requests WHERE id = ?", [$overtimeId])->fetch();
            if ($req && $req['biometric_match']) {
                try {
                    HrService::postOvertimeToPayroll($overtimeId);
                } catch (\Throwable $e) {
                    Helpers::audit('overtime_payroll_failed', 'overtime_requests', $overtimeId, $e->getMessage());
                }
            }
            self::notifyHrAboutOvertime($overtimeId, 'تم اعتماد طلب عمل إضافي', 'تمت الموافقة النهائية على طلب عمل إضافي، يرجى مراجعة البيانات المالية');
        }
    }

    public static function onOvertimeOutcome(array $request, string $status, int $actorId, string $notes): void
    {
        $overtimeId = (int)($request['data']['entity_id'] ?? 0);
        if (!$overtimeId) return;
        if ($status === 'rejected') {
            $current = Database::query("SELECT status FROM overtime_requests WHERE id = ?", [$overtimeId])->fetchColumn();
            if ($current === 'expired') {
                return;
            }
            Database::query(
                "UPDATE overtime_requests
                 SET status = 'rejected', rejected_by = ?, rejected_at = NOW(), rejected_notes = ?
                 WHERE id = ?",
                [$actorId, $notes, $overtimeId]
            );
            self::notifyEmployee($request, $status);
            return;
        }
        if ($status === 'approved') {
            $req = Database::query("SELECT * FROM overtime_requests WHERE id = ?", [$overtimeId])->fetch();
            if ($req && $req['status'] !== 'fully_approved' && $req['status'] !== 'rejected') {
                HrService::recomputeOvertimeBiometricMatch($overtimeId);
                Database::query(
                    "UPDATE overtime_requests
                     SET status = 'fully_approved', grand_manager_approved_by = ?, grand_manager_approved_at = NOW(),
                         rejected_by = NULL, rejected_at = NULL, rejected_notes = NULL
                     WHERE id = ?",
                    [$actorId, $overtimeId]
                );
                $req = Database::query("SELECT * FROM overtime_requests WHERE id = ?", [$overtimeId])->fetch();
                if ($req && $req['biometric_match']) {
                    try {
                        HrService::postOvertimeToPayroll($overtimeId);
                    } catch (\Throwable $e) {
                        Helpers::audit('overtime_payroll_failed', 'overtime_requests', $overtimeId, $e->getMessage());
                    }
                }
                self::notifyHrAboutOvertime($overtimeId, 'تم اعتماد طلب عمل إضافي', 'تمت الموافقة النهائية على طلب عمل إضافي، يرجى مراجعة البيانات المالية');
            }
            self::notifyEmployee($request, $status);
        }
    }

    private static function notifyHrAboutOvertime(int $overtimeId, string $title, string $body): void
    {
        $req = Database::query(
            "SELECT o.*, e.full_name FROM overtime_requests o
             JOIN employees e ON e.id = o.employee_id
             WHERE o.id = ?",
            [$overtimeId]
        )->fetch();
        if (!$req) return;
        $link = '/hr/overtime/' . $overtimeId;
        $message = ($req['full_name'] ?? '') . ' — ' . $req['request_date'] . ' — ' . $req['hours'] . ' ساعة';
        $hrRoleId = (int)(Database::query("SELECT id FROM roles WHERE name = 'مدير موارد بشرية' LIMIT 1")->fetchColumn() ?? 0);
        if ($hrRoleId) {
            Helpers::notifyRole($hrRoleId, $title, $message, $link);
        }
        $users = Database::query(
            "SELECT DISTINCT u.id FROM users u
             JOIN role_permissions rp ON rp.role_id = u.role_id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE p.code = 'hr.view' AND u.is_active = 1"
        )->fetchAll();
        foreach ($users as $u) {
            Helpers::notify((int)$u['id'], $title, $message, $link);
        }
    }

    public static function onMissingAttendanceStep(array $request, array $step, int $actorId, string $notes): void
    {
        $requestId = (int)($request['data']['entity_id'] ?? 0);
        if (!$requestId) {
            $wfRequestId = (int)($request['id'] ?? 0);
            if ($wfRequestId) {
                $requestId = (int)(Database::query("SELECT id FROM missing_attendance_requests WHERE workflow_request_id = ?", [$wfRequestId])->fetchColumn() ?? 0);
            }
        }
        if (!$requestId) return;
        $stepOrder = (int)($step['step_order'] ?? 0);
        if ($stepOrder === 1) {
            Database::query(
                "UPDATE missing_attendance_requests
                 SET status = 'manager_approved', manager_approved_by = ?, manager_approved_at = NOW(),
                     rejected_by = NULL, rejected_at = NULL, rejected_notes = NULL
                 WHERE id = ?",
                [$actorId, $requestId]
            );
        } elseif ($stepOrder === 2) {
            try {
                HrService::applyMissingAttendanceRequest($requestId);
            } catch (\Throwable $e) {
                Helpers::audit('missing_attendance_apply_failed', 'missing_attendance_requests', $requestId, $e->getMessage());
            }
            Database::query(
                "UPDATE missing_attendance_requests
                 SET status = 'fully_approved', grand_manager_approved_by = ?, grand_manager_approved_at = NOW(),
                     rejected_by = NULL, rejected_at = NULL, rejected_notes = NULL
                 WHERE id = ?",
                [$actorId, $requestId]
            );
            // الإشعار يتم من معالج النتيجة (Outcome Handler) عند الموافقة النهائية.
        }
    }

    public static function onMissingAttendanceOutcome(array $request, string $status, int $actorId, string $notes): void
    {
        $requestId = (int)($request['data']['entity_id'] ?? 0);
        if (!$requestId) {
            $wfRequestId = (int)($request['id'] ?? 0);
            if ($wfRequestId) {
                $requestId = (int)(Database::query("SELECT id FROM missing_attendance_requests WHERE workflow_request_id = ?", [$wfRequestId])->fetchColumn() ?? 0);
            }
        }
        if (!$requestId) return;

        if ($status === 'rejected') {
            Database::query(
                "UPDATE missing_attendance_requests
                 SET status = 'rejected', rejected_by = ?, rejected_at = NOW(), rejected_notes = ?
                 WHERE id = ?",
                [$actorId, $notes, $requestId]
            );
            self::notifyEmployee($request, $status);
            return;
        }

        if ($status === 'approved') {
            $current = Database::query("SELECT status FROM missing_attendance_requests WHERE id = ?", [$requestId])->fetchColumn();
            if ($current !== 'fully_approved') {
                try {
                    HrService::applyMissingAttendanceRequest($requestId);
                } catch (\Throwable $e) {
                    Helpers::audit('missing_attendance_apply_failed', 'missing_attendance_requests', $requestId, $e->getMessage());
                }
                Database::query(
                    "UPDATE missing_attendance_requests
                     SET status = 'fully_approved', grand_manager_approved_by = ?, grand_manager_approved_at = NOW()
                     WHERE id = ?",
                    [$actorId, $requestId]
                );
            }
            self::notifyEmployee($request, $status);
        }
    }
}
