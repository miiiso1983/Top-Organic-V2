<?php
namespace App\Core;

/**
 * محرك سير العمل والموافقات
 */
class WorkflowEngine
{
    public const STATUSES = ['draft', 'pending', 'approved', 'rejected', 'returned_for_edit', 'cancelled'];
    public const ACTIONS = ['created', 'submitted', 'approved', 'rejected', 'returned_for_edit', 'resubmitted', 'comment', 'cancelled', 'viewed'];

    private static array $outcomeHandlers = [];
    private static array $stepHandlers = [];

    public static function registerOutcomeHandler(string $templateCode, callable $handler): void
    {
        self::$outcomeHandlers[$templateCode][] = $handler;
    }

    public static function registerStepHandler(string $templateCode, callable $handler): void
    {
        self::$stepHandlers[$templateCode][] = $handler;
    }

    public static function triggerOutcomeHandlers(array $request, string $status, int $actorId, string $notes): void
    {
        foreach (self::$outcomeHandlers[$request['template_code'] ?? ''] ?? [] as $handler) {
            $handler($request, $status, $actorId, $notes);
        }
    }

    private static function triggerStepHandlers(array $request, array $step, int $actorId, string $notes): void
    {
        foreach (self::$stepHandlers[$request['template_code'] ?? ''] ?? [] as $handler) {
            $handler($request, $step, $actorId, $notes);
        }
    }

    /**
     * هل يملك هذا الدور صلاحية إنشاء طلب من هذا القالب تحديداً؟
     * مرتبط حصرياً بـ template_allowed_roles.can_create (يُدار من شاشة "تعديل الدور")،
     * باستثناء: مدير النظام (صلاحية كاملة بلا شرط كبقية صلاحيات النظام)، وطلب
     * الإجازة/السلفة (حق ذاتي لكل موظف بصرف النظر عن إعدادات القالب).
     * لا يوجد أي تجاوز عام عبر مستوى الدور أو اسمه أو صلاحية عامة؛ إن لم يُمنح
     * الدور صلاحية هذا القالب تحديداً فلن يستطيع إنشاء طلب منه.
     */
    public static function canCreate(int $templateId, int $roleId): bool
    {
        return (bool) Database::query(
            "SELECT 1
             FROM approval_templates t
             LEFT JOIN template_allowed_roles tar
               ON tar.template_id = t.id AND tar.role_id = ?
             LEFT JOIN roles r ON r.id = ?
             WHERE t.id = ?
               AND t.is_active = 1
               AND (
                 tar.can_create = 1
                 OR r.name = 'مدير النظام'
                 OR t.code IN ('hr_leave', 'hr_advance')
               )
             LIMIT 1",
            [$roleId, $roleId, $templateId]
        )->fetch();
    }

    /** يُرجع قائمة الموظفين المرؤوسين (مباشرين وغير مباشرين) للمستخدم المحدد */
    public static function subordinateEmployees(int $userId): array
    {
        $user = self::getUserWithPosition($userId);
        if (!$user) return [];
        // للمدير العام/مدير النظام: عرض كل الموظفين كاحتياطي
        if ((string)($user['role_name'] ?? '') === 'مدير النظام') {
            return Database::query(
                "SELECT id, full_name, emp_code FROM employees WHERE status = 'active' ORDER BY full_name"
            )->fetchAll();
        }
        $employee = Database::query("SELECT id FROM employees WHERE user_id = ? LIMIT 1", [$userId])->fetch();
        if (!$employee) return [];
        $managerId = (int)$employee['id'];
        return Database::query(
            "WITH RECURSIVE mgr_chain AS (
                SELECT id FROM employees WHERE manager_id = ? AND status = 'active'
                UNION ALL
                SELECT e.id FROM employees e JOIN mgr_chain s ON e.manager_id = s.id WHERE e.status = 'active'
            ),
            pos_tree AS (
                SELECT op.id, op.employee_id FROM org_positions op WHERE op.employee_id = ? AND op.is_active = 1
                UNION ALL
                SELECT op.id, op.employee_id FROM org_positions op JOIN pos_tree pt ON op.parent_id = pt.id WHERE op.is_active = 1
            )
            SELECT DISTINCT e.id, e.full_name, e.emp_code
            FROM employees e
            WHERE e.status = 'active'
              AND e.id != ?
              AND (
                  e.id IN (SELECT id FROM mgr_chain)
                  OR e.id IN (SELECT employee_id FROM pos_tree WHERE employee_id IS NOT NULL)
              )
            ORDER BY e.full_name",
            [$managerId, $managerId, $managerId]
        )->fetchAll();
    }

    public static function canViewAll(int $templateId, int $roleId): bool
    {
        return (bool) Database::query(
            "SELECT 1 FROM template_allowed_roles WHERE template_id = ? AND role_id = ? AND can_view_all = 1",
            [$templateId, $roleId]
        )->fetch();
    }

    /** يُرجع بيانات حد السلفة للموظف: الراتب، الحد الأقصى (25%)، المستخدم، والمتبقي */
    public static function advanceLimitForEmployee(int $employeeId): array
    {
        $emp = Database::query("SELECT base_salary FROM employees WHERE id = ?", [$employeeId])->fetch();
        $salary = (float)($emp['base_salary'] ?? 0);
        // احتياطي: آخر راتب مسجل في كشوف الرواتب
        if ($salary <= 0) {
            $payroll = Database::query(
                "SELECT base_salary FROM payrolls WHERE employee_id = ? ORDER BY period DESC, id DESC LIMIT 1",
                [$employeeId]
            )->fetch();
            $salary = (float)($payroll['base_salary'] ?? 0);
        }
        $max = $salary * 0.25;
        $start = date('Y-m-01');
        $end = date('Y-m-01', strtotime('+1 month'));
        $templateId = (int) Database::query("SELECT id FROM approval_templates WHERE code = 'hr_advance' LIMIT 1")->fetchColumn();
        $used = 0.0;
        if ($templateId) {
            $used = (float) Database::query(
                "SELECT COALESCE(SUM(amount), 0) FROM requests
                 WHERE template_id = ? AND requester_employee_id = ? AND status IN ('pending','approved')
                   AND created_at >= ? AND created_at < ?",
                [$templateId, $employeeId, $start, $end]
            )->fetchColumn();
        }
        $out = [
            'salary' => $salary,
            'max' => $max,
            'used' => $used,
            'remaining' => max(0.0, $max - $used),
        ];
        if ($salary <= 0) {
            $out['error'] = 'لم يُعثر على راتب مسجل للموظف. يرجى مراجعة قسم الموارد البشرية لتعبئة الراتب الأساسي.';
        }
        return $out;
    }

    public static function advanceLimitForUser(int $userId): array
    {
        $user = self::getUserWithPosition($userId);
        $employeeId = (int)($user['employee_id'] ?? 0);
        if (!$employeeId) {
            return ['salary' => 0.0, 'max' => 0.0, 'used' => 0.0, 'remaining' => 0.0, 'error' => 'الموظف غير مرتبط بحساب مستخدم'];
        }
        return self::advanceLimitForEmployee($employeeId);
    }

    private static function validateAdvanceRequest(int $templateId, array $payload, ?int $requesterEmployeeId, ?int $requestId = null): void
    {
        $template = WorkflowTemplateService::getTemplate($templateId);
        if (!$template || !in_array($template['code'], ['hr_advance', 'hr_exceptional_advance'], true)) return;
        $amount = (float)($payload['amount'] ?? 0);
        if ($amount <= 0) return;
        $employeeId = (int)($payload['employee_id'] ?? $requesterEmployeeId ?? 0);
        if (!$employeeId) throw new \RuntimeException('لا يمكن التحقق من الراتب: الموظف غير محدد');
        $limit = self::advanceLimitForEmployee($employeeId);
        if ($limit['salary'] <= 0) {
            throw new \RuntimeException('لا يمكن التحقق من الراتب: الراتب الأساسي غير محدد للموظف');
        }
        // عند التحقق من طلب موجود نستثني مبلغه ليتمكن المستخدم من إعادة إرساله
        $current = 0.0;
        if ($requestId) {
            $current = (float)(Database::query("SELECT amount FROM requests WHERE id = ?", [$requestId])->fetchColumn() ?: 0);
        }
        if ($amount - $current > $limit['remaining']) {
            throw new \RuntimeException('المبلغ المطلوب أعلى من المسموح، يرجى مراجعة قسم الموارد البشرية');
        }
    }

    /** يتحقق من تطابق مدة الإجازة اليومية مع نوعها (قصيرة بدون راتب <= 3 أيام، طويلة بدون راتب >= 4 أيام) */
    private static function validateLeaveRequest(int $templateId, array $payload): void
    {
        $template = WorkflowTemplateService::getTemplate($templateId);
        if (!$template || ($template['code'] ?? '') !== 'hr_leave') return;
        if ((string)($payload['request_type'] ?? '') !== 'leave') return;
        $leaveType = (string)($payload['leave_type'] ?? '');
        if (!in_array($leaveType, ['unpaid_short', 'unpaid_long'], true)) return;
        $days = (float)($payload['leave_days'] ?? 0);
        if ($leaveType === 'unpaid_short' && ($days < 1 || $days > 3)) {
            throw new \RuntimeException('الإجازة القصيرة بدون راتب يجب ألا تتجاوز مدتها 3 أيام');
        }
        if ($leaveType === 'unpaid_long' && $days < 4) {
            throw new \RuntimeException('الإجازة الطويلة بدون راتب يجب أن تكون مدتها 4 أيام فأكثر');
        }
    }

    /** يتحقق من وجود سبب/ملاحظات في طلب نسيان البصمة (دفاع عميق عن الاكتفاء
     *  بسمة required في النموذج على الواجهة فقط، لمنع إرسال الطلب بدون سبب عبر API مباشر) */
    private static function validateMissingAttendanceRequest(int $templateId, array $payload): void
    {
        $template = WorkflowTemplateService::getTemplate($templateId);
        if (!$template || ($template['code'] ?? '') !== 'hr_missing_attendance') return;
        if (trim((string)($payload['reason'] ?? '')) === '') {
            throw new \RuntimeException('سبب/ملاحظات طلب نسيان البصمة مطلوب، يرجى كتابة السبب');
        }
    }

    private static function fillAdvanceDefaults(array $template, array $payload): array
    {
        if (($template['code'] ?? '') !== 'hr_advance') return $payload;
        if (!isset($payload['advance_type'])) $payload['advance_type'] = 'short';
        if (!isset($payload['installments']) || $payload['installments'] === null || $payload['installments'] === '') {
            $payload['installments'] = 1;
        }
        return $payload;
    }


    public static function createRequest(int $templateId, int $userId, array $data, bool $skipAuth = false): int
    {
        $template = WorkflowTemplateService::getTemplate($templateId);
        if (!$template) throw new \RuntimeException('القالب غير موجود');

        $user = self::getUserWithPosition($userId) ?: [];
        if (!$skipAuth && !self::canCreate($templateId, (int)($user['role_id'] ?? 0))) {
            throw new \RuntimeException('ليس لديك صلاحية إنشاء هذا الطلب');
        }

        $payload = self::fillAdvanceDefaults($template, self::extractPayload($template['fields'], $data));
        self::validateAdvanceRequest($templateId, $payload, (int)($user['employee_id'] ?? 0), null);
        self::validateLeaveRequest($templateId, $payload);
        self::validateMissingAttendanceRequest($templateId, $payload);
        $amount = self::extractAmount($template, $payload);
        $title = (string)($data['title'] ?? ($template['name'] . ' - ' . date('Y-m-d')));
        $no = Helpers::docNumber('REQ', 'requests', 'request_no');

        Database::query(
            "INSERT INTO requests (template_id, request_no, title, requester_id, requester_employee_id, requester_position_id, status, amount, data_json, branch_id)
             VALUES (?,?,?,?,?,?,?,?,?,?)",
            [
                $templateId, $no, $title, $userId,
                $user['employee_id'] ?: null,
                $user['position_id'] ?: null,
                'draft', $amount, json_encode($payload, JSON_UNESCAPED_UNICODE),
                $user['branch_id'] ?: null
            ]
        );
        $requestId = (int) Database::lastInsertId();
        self::addParticipant($requestId, $userId, 'requester');
        self::logHistory($requestId, 'created', null, $userId, '');
        return $requestId;
    }

    public static function submitRequest(int $requestId, int $userId): void
    {
        $req = self::getRequest($requestId);
        if (!$req) throw new \RuntimeException('الطلب غير موجود');
        if ((int)$req['requester_id'] !== $userId) throw new \RuntimeException('ليس لديك صلاحية إرسال هذا الطلب');
        if (!in_array($req['status'], ['draft', 'returned_for_edit'], true)) {
            throw new \RuntimeException('الطلب ليس في حالة قابلة للإرسال');
        }

        $payload = json_decode($req['data_json'] ?? '{}', true);
        self::validateAdvanceRequest((int)$req['template_id'], $payload, (int)($req['requester_employee_id'] ?? 0), $requestId);
        self::validateLeaveRequest((int)$req['template_id'], $payload);
        self::validateMissingAttendanceRequest((int)$req['template_id'], $payload);

        $status = $req['status'] === 'returned_for_edit' ? 'resubmitted' : 'submitted';
        self::logHistory($requestId, $status, null, $userId, '');
        Database::query(
            "UPDATE requests SET status = 'pending', current_step = 1, updated_at = NOW() WHERE id = ?",
            [$requestId]
        );

        // جسر المحرك الديناميكي: إن وُجد مخطط منشور للقالب يسير الطلب عليه
        if (\App\Core\Workflow\Engine::start($requestId, $userId)) {
            return;
        }

        $steps = self::resolveSteps((int)$req['template_id'], 1, $requestId);
        if (!$steps) {
            Database::query("UPDATE requests SET status = 'approved' WHERE id = ?", [$requestId]);
            self::logHistory($requestId, 'approved', null, $userId, 'لا يوجد مسار موافقات');
            self::triggerOutcomeHandlers($req, 'approved', $userId, '');
            return;
        }
        foreach ($steps as $s) {
            self::notifyApprovers($s, $requestId, 'طلب موافقة بانتظارك');
        }
    }

    public static function saveDraftData(int $requestId, array $data, int $userId): void
    {
        $req = self::getRequest($requestId);
        if (!$req) throw new \RuntimeException('الطلب غير موجود');
        if ((int)$req['requester_id'] !== $userId) throw new \RuntimeException('ليس لديك صلاحية تعديل هذا الطلب');
        if (!in_array($req['status'], ['draft', 'returned_for_edit'], true)) throw new \RuntimeException('الطلب ليس في حالة قابلة للتعديل');

        $payload = self::fillAdvanceDefaults($req, self::extractPayload($req['fields'], $data));
        $amount = self::extractAmount($req, $payload);
        $title = (string)($data['title'] ?? $req['title']);
        Database::query(
            "UPDATE requests SET title = ?, amount = ?, data_json = ?, updated_at = NOW() WHERE id = ?",
            [$title, $amount, json_encode($payload, JSON_UNESCAPED_UNICODE), $requestId]
        );
    }

    public static function resubmit(int $requestId, array $data, int $userId): void
    {
        $req = self::getRequest($requestId);
        if (!$req) throw new \RuntimeException('الطلب غير موجود');
        if ((int)$req['requester_id'] !== $userId) throw new \RuntimeException('ليس لديك صلاحية تعديل هذا الطلب');
        if ($req['status'] !== 'returned_for_edit') throw new \RuntimeException('الطلب ليس في حالة إعادة الإرسال');
        self::saveDraftData($requestId, $data, $userId);
        self::logHistory($requestId, 'resubmitted', null, $userId, (string)($data['notes'] ?? ''));
        Database::query("UPDATE requests SET status = 'pending' WHERE id = ?", [$requestId]);
        // طلبات المخطط الديناميكي: إلغاء المهام القديمة وإعادة السير من البداية
        if (!empty($req['workflow_version_id'])) {
            Database::query("UPDATE workflow_tasks SET status = 'cancelled' WHERE request_id = ? AND status = 'pending'", [$requestId]);
            \App\Core\Workflow\Engine::start($requestId, $userId);
            return;
        }
        self::clearStepDecisions($requestId, (int)$req['template_id'], (int)$req['current_step']);
        foreach (self::resolveSteps((int)$req['template_id'], (int)$req['current_step'], $requestId) as $s) {
            self::notifyApprovers($s, $requestId, 'تم إعادة إرسال طلب للتعديل');
        }
    }

    public static function canEditRequest(int $requestId, int $userId): bool
    {
        $req = self::getRequest($requestId);
        if (!$req) return false;
        if (in_array($req['status'], ['draft', 'returned_for_edit'], true)) {
            return (int)$req['requester_id'] === $userId;
        }
        if ($req['status'] === 'pending') {
            // المقدم يستطيع التعديل
            if ((int)$req['requester_id'] === $userId) return true;
            // المدير/المعتمد الحالي يستطيع التعديل
            $steps = self::resolveSteps((int)$req['template_id'], (int)$req['current_step'], $requestId);
            foreach ($steps as $s) {
                if (self::userCanDecideStepAsUser($s, $req, $userId)) return true;
            }
        }
        return false;
    }

    public static function editRequest(int $requestId, array $data, int $userId): void
    {
        $req = self::getRequest($requestId);
        if (!$req) throw new \RuntimeException('الطلب غير موجود');
        if (!self::canEditRequest($requestId, $userId)) throw new \RuntimeException('ليس لديك صلاحية تعديل هذا الطلب');
        if (!in_array($req['status'], ['draft', 'pending', 'returned_for_edit'], true)) {
            throw new \RuntimeException('لا يمكن تعديل الطلب في حالته الحالية');
        }

        $payload = self::fillAdvanceDefaults($req, self::extractPayload($req['fields'], $data));
        self::validateAdvanceRequest((int)$req['template_id'], $payload, (int)($req['requester_employee_id'] ?? 0), $requestId);
        self::validateMissingAttendanceRequest((int)$req['template_id'], $payload);
        $amount = self::extractAmount($req, $payload);
        $title = (string)($data['title'] ?? $req['title']);
        $notes = (string)($data['notes'] ?? '');
        $isManager = (int)$req['requester_id'] !== $userId;

        Database::query(
            "UPDATE requests SET title = ?, amount = ?, data_json = ?, updated_at = NOW() WHERE id = ?",
            [$title, $amount, json_encode($payload, JSON_UNESCAPED_UNICODE), $requestId]
        );

        if ($req['status'] === 'pending') {
            $logNote = $notes ?: ($isManager ? 'تم تعديل الطلب من قبل المدير' : 'تم تعديل الطلب');
            self::logHistory($requestId, 'resubmitted', null, $userId, $logNote);
            // طلبات المخطط الديناميكي: إلغاء المهام القديمة وإعادة السير من البداية
            if (!empty($req['workflow_version_id'])) {
                Database::query("UPDATE workflow_tasks SET status = 'cancelled' WHERE request_id = ? AND status = 'pending'", [$requestId]);
                \App\Core\Workflow\Engine::start($requestId, $userId);
                return;
            }
            self::clearStepDecisions($requestId, (int)$req['template_id'], (int)$req['current_step']);
            foreach (self::resolveSteps((int)$req['template_id'], (int)$req['current_step'], $requestId) as $s) {
                self::notifyApprovers($s, $requestId, $isManager ? 'تم تعديل الطلب من قبل المدير' : 'تم تعديل طلب، يرجى المراجعة');
            }
        } else {
            self::logHistory($requestId, 'created', null, $userId, $notes ?: 'تم حفظ التعديل');
        }
    }

    public static function decide(int $requestId, string $action, string $notes, int $userId): void
    {
        $req = self::getRequest($requestId);
        if (!$req) throw new \RuntimeException('الطلب غير موجود');
        // جسر المحرك الديناميكي: القرارات على طلبات المخطط تمر عبر Engine
        if (!empty($req['workflow_version_id'])) {
            \App\Core\Workflow\Engine::decide($requestId, $action, $notes, $userId);
            return;
        }
        if ($req['status'] !== 'pending') throw new \RuntimeException('الطلب ليس في حالة المعالجة');

        $steps = self::resolveSteps((int)$req['template_id'], (int)$req['current_step'], $requestId);
        if (!$steps) throw new \RuntimeException('لا يوجد خطوة حالية للطلب');

        $step = null;
        foreach ($steps as $s) {
            if (!self::userCanDecideStepAsUser($s, $req, $userId)) continue;
            if (self::hasDecision($requestId, (int)$s['id'], $userId)) continue;
            $step = $s;
            break;
        }
        if (!$step) throw new \RuntimeException('ليس لديك صلاحية لموافقة هذا الطلب');

        self::addParticipant($requestId, $userId, 'approver');
        self::recordDecision($requestId, (int)$step['id'], $action, $userId, $notes);
        self::logHistory($requestId, $action, (int)$step['id'], $userId, $notes);

        if ($action === 'rejected') {
            Database::query("UPDATE requests SET status = 'rejected', updated_at = NOW() WHERE id = ?", [$requestId]);
            self::triggerOutcomeHandlers($req, 'rejected', $userId, $notes);
            self::notifyRequester($requestId, 'تم رفض طلبك', $notes);
            return;
        }
        if ($action === 'returned_for_edit') {
            if (empty($step['can_return_for_edit'])) {
                throw new \RuntimeException('هذه الخطوة لا تسمح بإعادة الطلب للتعديل');
            }
            Database::query("UPDATE requests SET status = 'returned_for_edit', updated_at = NOW() WHERE id = ?", [$requestId]);
            self::triggerOutcomeHandlers($req, 'returned_for_edit', $userId, $notes);
            self::notifyRequester($requestId, 'أعيد إرسال الطلب للتعديل', $notes);
            return;
        }

        $approvedCount = self::countGroupApprovals($requestId, $steps);
        if ($approvedCount < self::requiredApprovals($steps)) {
            return;
        }

        self::triggerStepHandlers($req, $step, $userId, $notes);

        $nextOrder = self::nextStepOrder((int)$req['template_id'], (int)$req['current_step'], $requestId);
        if ($nextOrder) {
            Database::query("UPDATE requests SET current_step = ?, updated_at = NOW() WHERE id = ?", [$nextOrder, $requestId]);
            foreach (self::resolveSteps((int)$req['template_id'], $nextOrder, $requestId) as $ns) {
                self::notifyApprovers($ns, $requestId, 'طلب موافقة بانتظارك');
            }
            self::notifyRequester($requestId, 'تمت الموافقة على طلبك وتم إرساله للخطوة التالية من الاعتماد', $notes, 'step_approved');
        } else {
            Database::query("UPDATE requests SET status = 'approved', updated_at = NOW() WHERE id = ?", [$requestId]);
            self::triggerOutcomeHandlers($req, 'approved', $userId, $notes);
            self::notifyRequester($requestId, 'تم الموافقة على طلبك', '');
        }
    }

    public static function addComment(int $requestId, string $notes, int $userId): void
    {
        self::logHistory($requestId, 'comment', null, $userId, $notes);
    }

    public static function cancelRequest(int $requestId, int $userId): void
    {
        $req = self::getRequest($requestId);
        if (!$req) throw new \RuntimeException('الطلب غير موجود');
        if ((int)$req['requester_id'] !== $userId) throw new \RuntimeException('ليس لديك صلاحية إلغاء هذا الطلب');
        if (!in_array($req['status'], ['draft', 'pending', 'returned_for_edit'], true)) {
            throw new \RuntimeException('لا يمكن إلغاء الطلب في هذه الحالة');
        }
        Database::query("UPDATE requests SET status = 'cancelled', updated_at = NOW() WHERE id = ?", [$requestId]);
        if (!empty($req['workflow_version_id'])) {
            Database::query("UPDATE workflow_tasks SET status = 'cancelled' WHERE request_id = ? AND status = 'pending'", [$requestId]);
        }
        self::logHistory($requestId, 'cancelled', null, $userId, '');
    }

    public static function getRequest(int $id): ?array
    {
        $r = Database::query(
            "SELECT r.*, t.name AS template_name, t.code AS template_code, t.fields_json, t.amount_field,
                    u.full_name AS requester_name, e.full_name AS employee_name, op.title AS requester_position
             FROM requests r
             JOIN approval_templates t ON t.id = r.template_id
             LEFT JOIN users u ON u.id = r.requester_id
             LEFT JOIN employees e ON e.id = r.requester_employee_id
             LEFT JOIN org_positions op ON op.id = r.requester_position_id
             WHERE r.id = ?",
            [$id]
        )->fetch();
        if (!$r) return null;
        $r['data'] = json_decode($r['data_json'] ?? '{}', true) ?: [];
        $r['fields'] = json_decode($r['fields_json'] ?? '[]', true) ?: [];
        return $r;
    }

    public static function getHistory(int $requestId): array
    {
        return Database::query(
            "SELECT h.*, s.title AS step_title, u.full_name AS actor_real_name
             FROM request_history h
             LEFT JOIN approval_steps s ON s.id = h.step_id
             LEFT JOIN users u ON u.id = h.actor_id
             WHERE h.request_id = ? ORDER BY h.id ASC",
            [$requestId]
        )->fetchAll();
    }

    public static function getMyRequests(int $userId): array
    {
        return Database::query(
            "SELECT r.*, t.name AS template_name, t.icon, u.full_name AS requester_name
             FROM requests r
             JOIN approval_templates t ON t.id = r.template_id
             LEFT JOIN users u ON u.id = r.requester_id
             WHERE r.requester_id = ? ORDER BY r.id DESC",
            [$userId]
        )->fetchAll();
    }

    public static function getVisibleRequests(int $userId): array
    {
        $user = self::getUserWithPosition($userId);
        if (!$user) return [];
        $roleId = (int)($user['role_id'] ?? 0);
        if ((string)($user['role_name'] ?? '') === 'مدير النظام') {
            return Database::query(
                "SELECT r.*, t.name AS template_name, t.icon FROM requests r
                 JOIN approval_templates t ON t.id = r.template_id ORDER BY r.id DESC"
            )->fetchAll();
        }

        $rows = Database::query(
            "SELECT r.*, t.name AS template_name, t.icon
             FROM requests r
             JOIN approval_templates t ON t.id = r.template_id
             WHERE r.requester_id = ?
                OR r.id IN (SELECT request_id FROM request_participants WHERE user_id = ?)
                OR r.template_id IN (SELECT template_id FROM template_allowed_roles WHERE role_id = ? AND can_view_all = 1)
             ORDER BY r.id DESC",
            [$userId, $userId, $roleId]
        )->fetchAll();

        // للطلبات المعلّقة: نُظهرها فقط للمُرسل أو لمعتمد الخطوة الحالية،
        // ولا نُظهرها لبقية المشاركين/المديرين حتى يتم الانتقال إلى خطوتهم.
        $out = [];
        foreach ($rows as $r) {
            if ((int)$r['requester_id'] === $userId) {
                $out[] = $r;
                continue;
            }
            if ($r['status'] !== 'pending') {
                $out[] = $r;
                continue;
            }
            $steps = self::resolveSteps((int)$r['template_id'], (int)$r['current_step'], (int)$r['id']);
            foreach ($steps as $s) {
                if (self::userCanDecideStep($s, $r)) {
                    $out[] = $r;
                    break;
                }
            }
        }
        return $out;
    }

    public static function getPendingForUser(int $userId): array
    {
        $user = self::getUserWithPosition($userId);
        if (!$user) return [];
        // حماية: لو لم يُطبّق ترحيل 90 بعد، لا نشير للعمود الجديد صراحة
        $graphFilter = self::requestsHasGraphColumn() ? "AND r.workflow_version_id IS NULL" : "";
        $all = Database::query(
            "SELECT r.*, t.name AS template_name, t.icon, t.code AS template_code,
                    u.full_name AS requester_name
             FROM requests r
             JOIN approval_templates t ON t.id = r.template_id
             LEFT JOIN users u ON u.id = r.requester_id
             WHERE r.status = 'pending' $graphFilter ORDER BY r.id DESC"
        )->fetchAll();
        $out = [];
        foreach ($all as $r) {
            $steps = self::resolveSteps((int)$r['template_id'], (int)$r['current_step'], (int)$r['id']);
            foreach ($steps as $s) {
                if (self::userCanDecideStep($s, $r) && !self::hasDecision((int)$r['id'], (int)$s['id'], $userId)) {
                    $r['step'] = $s;
                    $out[] = $r;
                    break;
                }
            }
        }
        // دمج مهام المحرك الديناميكي المعلقة للمستخدم (إن وُجدت جداوله)
        if (self::requestsHasGraphColumn()) {
            $out = array_merge($out, \App\Core\Workflow\Engine::pendingRequestsFor($userId));
        }
        return $out;
    }

    /** هل طُبّق ترحيل المحرك الديناميكي (90) على قاعدة البيانات؟ */
    private static function requestsHasGraphColumn(): bool
    {
        static $has = null;
        if ($has !== null) return $has;
        try {
            $has = (bool) Database::query(
                "SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'requests' AND COLUMN_NAME = 'workflow_version_id' LIMIT 1"
            )->fetch();
        } catch (\Throwable $e) {
            $has = false;
        }
        return $has;
    }

    public static function resolveSteps(int $templateId, int $stepOrder, int $requestId): array
    {
        $req = self::getRequest($requestId);
        if (!$req) return [];
        $out = [];
        foreach (WorkflowTemplateService::getSteps($templateId) as $s) {
            if ((int)$s['step_order'] !== $stepOrder) continue;
            if ((float)$req['amount'] < (float)$s['min_amount']) continue;
            if (!self::stepConditionMatches($s, $req)) continue;
            $out[] = $s;
        }
        return $out;
    }

    public static function resolveStep(int $templateId, int $stepOrder, int $requestId): ?array
    {
        return self::resolveSteps($templateId, $stepOrder, $requestId)[0] ?? null;
    }

    private static function stepConditionMatches(array $step, array $req): bool
    {
        $cond = json_decode($step['condition_json'] ?? '{}', true) ?: [];
        if (empty($cond)) return true;
        $data = $req['data'] ?? [];
        foreach ($cond as $key => $expected) {
            $actual = $data[$key] ?? null;
            if (is_array($expected)) {
                if (isset($expected['>']) && (float)$actual <= (float)$expected['>']) return false;
                if (isset($expected['>=']) && (float)$actual < (float)$expected['>=']) return false;
                if (isset($expected['<']) && (float)$actual >= (float)$expected['<']) return false;
                if (isset($expected['<=']) && (float)$actual > (float)$expected['<=']) return false;
                if (isset($expected['==']) && (string)$actual !== (string)$expected['==']) return false;
                if (isset($expected['!=']) && (string)$actual === (string)$expected['!=']) return false;
                if (!isset($expected['>'], $expected['>='], $expected['<'], $expected['<='], $expected['=='], $expected['!=']) && !in_array($actual, $expected, true)) return false;
            } elseif ((string)$actual !== (string)$expected) {
                return false;
            }
        }
        return true;
    }

    public static function userCanDecideStep(array $step, array $request): bool
    {
        return self::userCanDecideStepAsUser($step, $request, (int)($_SESSION['user_id'] ?? 0));
    }

    private static function userCanDecideStepAsUser(array $step, array $request, int $userId): bool
    {
        $user = self::getUserWithPosition($userId);
        if (!$user) return false;
        if ((string)($user['role_name'] ?? '') === 'مدير النظام') return true;

        $approverType = $step['approver_type'] ?? 'position';
        if ($approverType === 'direct_manager' || $approverType === 'department_manager') {
            $expectedUserId = self::resolveManagerApprover((int)($request['requester_employee_id'] ?? 0), $approverType);
            if ($expectedUserId !== null && $expectedUserId === (int)($user['id'] ?? 0)) return true;

            // fallback: إذا كان حساب المستخدم الحالي مرتبطاً بسجل المدير نفسه
            $managerEmpId = self::resolveManagerEmployeeId((int)($request['requester_employee_id'] ?? 0), $approverType);
            if ($managerEmpId !== null && $managerEmpId === (int)($user['employee_id'] ?? 0)) return true;
            return false;
        }

        if ($step['role_id']) {
            if ((int)$step['role_id'] === (int)($user['role_id'] ?? 0)) return true;
            $stepRole = Database::query("SELECT level FROM roles WHERE id = ?", [(int)$step['role_id']])->fetch();
            if ($stepRole && (int)($user['role_level'] ?? 0) >= (int)$stepRole['level']) return true;
        }
        if (!$step['job_title_id']) return false;
        $approverPos = self::resolveApproverPosition((int)($request['requester_position_id'] ?? 0), (int)$step['job_title_id']);
        return $approverPos !== null && (int)($user['position_id'] ?? 0) === $approverPos;
    }

    private static function resolveManagerEmployeeId(int $requesterEmployeeId, string $approverType): ?int
    {
        if (!$requesterEmployeeId) return null;
        if ($approverType === 'direct_manager') {
            $mgr = Database::query("SELECT manager_id FROM employees WHERE id = ?", [$requesterEmployeeId])->fetch();
            return $mgr ? (int)$mgr['manager_id'] : null;
        }
        if ($approverType === 'department_manager') {
            $mgr = Database::query("SELECT manager_id FROM employees WHERE id = ?", [$requesterEmployeeId])->fetch();
            $managerId = $mgr ? (int)$mgr['manager_id'] : 0;
            if ($managerId) {
                $deptMgr = Database::query("SELECT manager_id FROM employees WHERE id = ?", [$managerId])->fetch();
                return $deptMgr ? (int)$deptMgr['manager_id'] : null;
            }
        }
        return null;
    }

    private static function resolveManagerApprover(int $requesterEmployeeId, string $approverType): ?int
    {
        if (!$requesterEmployeeId) return null;
        $managerId = self::resolveManagerEmployeeId($requesterEmployeeId, $approverType);
        if ($managerId) {
            $u = Database::query("SELECT user_id FROM employees WHERE id = ? AND user_id IS NOT NULL", [$managerId])->fetch();
            if ($u) return (int)$u['user_id'];
        }
        // fallback: التسلق في شجرة المناصب
        $levels = $approverType === 'direct_manager' ? 1 : 2;
        $pos = Database::query(
            "SELECT op.id FROM employees e JOIN org_positions op ON op.employee_id = e.id WHERE e.id = ?",
            [$requesterEmployeeId]
        )->fetch()['id'] ?? null;
        if (!$pos) return null;
        $guard = 0;
        while ($pos && $levels-- > 0 && $guard++ < 20) {
            $pos = (int)(Database::query("SELECT parent_id FROM org_positions WHERE id = ?", [$pos])->fetchColumn() ?: 0);
        }
        if ($pos) {
            $users = Database::query(
                "SELECT u.id FROM org_positions op
                 JOIN employees e ON e.id = op.employee_id
                 JOIN users u ON u.id = e.user_id
                 WHERE op.id = ? AND op.is_active = 1",
                [$pos]
            )->fetchAll(\PDO::FETCH_COLUMN);
            if ($users) return (int)$users[0];
        }
        return null;
    }

    public static function resolveApproverPosition(int $requesterPositionId, int $stepJobTitleId): ?int
    {
        if ($requesterPositionId === 0) return null;
        $current = $requesterPositionId;
        while ($current) {
            if ($current === $stepJobTitleId) return $current;
            $parent = Database::query("SELECT parent_id FROM org_positions WHERE id = ?", [$current])->fetch()['parent_id'] ?? null;
            if ($parent === null) break;
            $current = (int)$parent;
        }
        return null;
    }

    public static function getUserWithPosition(int $userId): ?array
    {
        $user = Database::query(
            "SELECT u.*, r.id AS role_id, r.name AS role_name, r.level AS role_level,
                    e.id AS employee_id, e.position AS emp_position,
                    op.id AS position_id, op.title AS position_title, op.parent_id AS position_parent
             FROM users u
             LEFT JOIN roles r ON r.id = u.role_id
             LEFT JOIN employees e ON e.user_id = u.id
             LEFT JOIN org_positions op ON op.employee_id = e.id
             WHERE u.id = ?",
            [$userId]
        )->fetch() ?: null;
        return $user;
    }

    private static function extractPayload(array $fields, array $data): array
    {
        $out = [];
        foreach ($fields as $f) {
            $name = $f['name'];
            $type = $f['type'] ?? 'text';
            $val = $data[$name] ?? null;
            if ($type === 'items_list') {
                $out[$name] = is_array($val) ? $val : [];
                continue;
            }
            if ($type === 'file') {
                $out[$name] = is_string($val) ? $val : '';
                continue;
            }
            if ($type === 'checkbox') $out[$name] = !empty($val) ? 1 : 0;
            elseif ($type === 'number') $out[$name] = ($val === null || $val === '') ? null : (float)$val;
            else $out[$name] = $val === null ? '' : (string)$val;
        }
        if (!empty($out['date_from']) && !empty($out['date_to'])) {
            $out['leave_days'] = self::leaveDays((string)$out['date_from'], (string)$out['date_to']);
        }
        return $out;
    }

    private static function leaveDays(string $dateFrom, string $dateTo): float
    {
        $from = strtotime($dateFrom);
        $to = strtotime($dateTo);
        if ($from === false || $to === false || $to < $from) return 0;
        return floor(($to - $from) / 86400) + 1;
    }

    private static function extractAmount(array $template, array $payload): float
    {
        $field = $template['amount_field'] ?? '';
        return ($field && isset($payload[$field])) ? (float)$payload[$field] : 0;
    }

    private static function addParticipant(int $requestId, int $userId, string $role): void
    {
        Database::query(
            "INSERT IGNORE INTO request_participants (request_id, user_id, role) VALUES (?,?,?)",
            [$requestId, $userId, $role]
        );
    }

    /**
     * قوالب الطلبات الذاتية للموظفين (إجازات، سلف، عمل إضافي، نسيان بصمة): إشعارات الطلب
     * تُوجّه حصراً لمقدّم الطلب ومديره المباشر دون غيرهم، بغض النظر عن عدد مستويات الاعتماد الفعلية
     * (صلاحية القرار لمدير المدير/الموارد البشرية تبقى كما هي دون تغيير، فقط الإشعار يُقيّد).
     */
    private const EMPLOYEE_SCOPED_NOTIFICATION_TEMPLATES = ['hr_leave', 'hr_advance', 'hr_overtime', 'hr_missing_attendance'];

    private static function notifyApprovers(array $step, int $requestId, string $title): void
    {
        $req = self::getRequest($requestId);
        if (!$req) return;
        $templateName = $req['template_name'] ?? 'طلب موافقة';
        $requestNo = $req['request_no'] ?? ('#' . $requestId);
        $requester = $req['requester_name'] ?? $req['employee_name'] ?? 'موظف';
        $createdAt = $req['created_at'] ?? '';
        $body = "$templateName $requestNo — $requester — بحاجة لموافقتك";
        $link = '/workflows/requests/' . $requestId;
        $channels = self::stepChannels($step);
        $metadata = [
            'template_name' => $templateName,
            'template_code' => $req['template_code'] ?? null,
            'request_no' => $requestNo,
            'requester_name' => $requester,
            'created_at' => $createdAt,
            'step_title' => $step['title'] ?? '',
        ];
        $notified = [];

        // طلبات الموظفين الذاتية (إجازات/سلف/عمل إضافي/نسيان بصمة): الإشعار يُقيّد حصراً على المدير المباشر
        // بغض النظر عن نوع الخطوة الفعلي (role/job_title_id/department_manager)، منعاً لبث الإشعار لأي جهة غير المدير المباشر
        // (صلاحية القرار لأي مستوى إداري أعلى تبقى كما هي عبر صندوق الوارد، فقط الإشعار الاستباقي يُقيّد).
        if (in_array((string)($req['template_code'] ?? ''), self::EMPLOYEE_SCOPED_NOTIFICATION_TEMPLATES, true)) {
            $uid = self::resolveManagerApprover((int)($req['requester_employee_id'] ?? 0), 'direct_manager');
            if ($uid && !isset($notified[$uid])) {
                $notified[$uid] = true;
                self::addParticipant($requestId, $uid, 'approver');
                self::dispatchNotification($channels, $uid, $title, $body, $link, $requestId, 'pending_approval', $metadata);
            }
            return;
        }

        $approverType = $step['approver_type'] ?? 'position';
        if ($approverType === 'direct_manager' || $approverType === 'department_manager') {
            $uid = self::resolveManagerApprover((int)($req['requester_employee_id'] ?? 0), $approverType);
            if ($uid && !isset($notified[$uid])) {
                $notified[$uid] = true;
                self::addParticipant($requestId, $uid, 'approver');
                self::dispatchNotification($channels, $uid, $title, $body, $link, $requestId, 'pending_approval', $metadata);
            }
            return;
        }

        if ($step['job_title_id']) {
            $resolvedPos = self::resolveApproverPosition((int)($req['requester_position_id'] ?? 0), (int)$step['job_title_id']);
            if ($resolvedPos) {
                $users = Database::query(
                    "SELECT u.id FROM users u
                     JOIN employees e ON e.user_id = u.id
                     JOIN org_positions op ON op.employee_id = e.id
                     WHERE op.id = ? AND u.is_active = 1",
                    [$resolvedPos]
                )->fetchAll();
                foreach ($users as $u) {
                    $uid = (int)$u['id'];
                    if (isset($notified[$uid])) continue;
                    $notified[$uid] = true;
                    self::addParticipant($requestId, $uid, 'approver');
                    self::dispatchNotification($channels, $uid, $title, $body, $link, $requestId, 'pending_approval', $metadata);
                }
            }
        }
        if ($step['role_id']) {
            $roleUsers = Database::query("SELECT id FROM users WHERE role_id = ? AND is_active = 1", [(int)$step['role_id']])->fetchAll();
            foreach ($roleUsers as $u) {
                $uid = (int)$u['id'];
                if (isset($notified[$uid])) continue;
                $notified[$uid] = true;
                self::addParticipant($requestId, $uid, 'approver');
                self::dispatchNotification($channels, $uid, $title, $body, $link, $requestId, 'pending_approval', $metadata);
            }
        }
    }

    private static function notifyRequester(int $requestId, string $title, string $notes, ?string $actionType = null): void
    {
        $req = self::getRequest($requestId);
        if (!$req) return;
        $templateName = $req['template_name'] ?? 'طلب موافقة';
        $requestNo = $req['request_no'] ?? ('#' . $requestId);
        $statusText = $title;
        $body = "$templateName $requestNo — $statusText" . ($notes ? "\nملاحظة: $notes" : '');
        $actionType ??= match(true) {
            str_contains($title, 'رفض') => 'rejected',
            str_contains($title, 'موافقة') => 'approved',
            str_contains($title, 'تعديل') => 'returned_for_edit',
            default => 'status_update',
        };
        $metadata = [
            'template_name' => $templateName,
            'template_code' => $req['template_code'] ?? null,
            'request_no' => $requestNo,
            'status' => $statusText,
            'notes' => $notes,
        ];
        self::dispatchNotification(['push' => 1], (int)$req['requester_id'], $title, $body, '/workflows/requests/' . $requestId, $requestId, $actionType, $metadata);
    }

    private static function stepChannels(array $step): array
    {
        $channels = json_decode($step['notify_channels'] ?? '{}', true) ?: [];
        if (empty($channels)) {
            return ['push' => 1, 'email' => 0, 'whatsapp' => 0];
        }
        return $channels;
    }

    private static function dispatchNotification(array $channels, int $userId, string $title, string $body, ?string $link, ?int $requestId = null, ?string $actionType = null, array $metadata = []): void
    {
        if (!empty($channels['push'])) {
            Helpers::notify($userId, $title, $body, $link, null, null, [], null, $requestId ? 'request' : null, $requestId, $actionType, $metadata);
        }
        if (!empty($channels['email']) || !empty($channels['whatsapp'])) {
            $u = Database::query(
                "SELECT u.email, u.phone, e.phone AS emp_phone, e.email AS emp_email
                 FROM users u
                 LEFT JOIN employees e ON e.user_id = u.id
                 WHERE u.id = ? AND u.is_active = 1",
                [$userId]
            )->fetch();
            if (!$u) return;
            $fullBody = $body . ($link ? "\n" . $link : '');
            if (!empty($channels['email']) && ($u['email'] || $u['emp_email'])) {
                NotificationService::sendEmail($u['email'] ?: $u['emp_email'], $title, $fullBody);
            }
            if (!empty($channels['whatsapp']) && ($u['phone'] || $u['emp_phone'])) {
                NotificationService::sendWhatsApp($u['phone'] ?: $u['emp_phone'], $fullBody);
            }
        }
    }

    private static function hasDecision(int $requestId, int $stepId, int $userId): bool
    {
        return (bool) Database::query(
            "SELECT 1 FROM request_step_decisions WHERE request_id = ? AND step_id = ? AND actor_id = ? LIMIT 1",
            [$requestId, $stepId, $userId]
        )->fetch();
    }

    private static function recordDecision(int $requestId, int $stepId, string $decision, int $userId, string $notes): void
    {
        Database::query(
            "INSERT INTO request_step_decisions (request_id, step_id, decision, actor_id, notes)
             VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE decision = VALUES(decision), notes = VALUES(notes), created_at = NOW()",
            [$requestId, $stepId, $decision, $userId, $notes]
        );
    }

    private static function countGroupApprovals(int $requestId, array $steps): int
    {
        if (!$steps) return 0;
        $ids = array_map(fn($s) => (int)$s['id'], $steps);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        return (int) Database::query(
            "SELECT COUNT(*) FROM request_step_decisions
             WHERE request_id = ? AND step_id IN ($placeholders) AND decision = 'approved'",
            array_merge([$requestId], $ids)
        )->fetchColumn();
    }

    private static function requiredApprovals(array $steps): int
    {
        if (!$steps) return 1;
        $type = $steps[0]['step_type'] ?? 'sequential';
        if ($type === 'parallel') {
            $count = (int)($steps[0]['required_count'] ?? 0);
            return $count > 0 ? $count : count($steps);
        }
        return 1;
    }

    private static function nextStepOrder(int $templateId, int $currentOrder, int $requestId): ?int
    {
        $req = self::getRequest($requestId);
        if (!$req) return null;
        $orders = [];
        foreach (WorkflowTemplateService::getSteps($templateId) as $s) {
            $order = (int)$s['step_order'];
            if ($order <= $currentOrder) continue;
            if ((float)$req['amount'] < (float)$s['min_amount']) continue;
            if (!self::stepConditionMatches($s, $req)) continue;
            $orders[$order] = true;
        }
        if (!$orders) return null;
        ksort($orders);
        return (int) array_key_first($orders);
    }

    private static function clearStepDecisions(int $requestId, int $templateId, int $stepOrder): void
    {
        $ids = [];
        foreach (WorkflowTemplateService::getSteps($templateId) as $s) {
            if ((int)$s['step_order'] === $stepOrder) {
                $ids[] = (int)$s['id'];
            }
        }
        if (!$ids) return;
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        Database::query(
            "DELETE FROM request_step_decisions WHERE request_id = ? AND step_id IN ($placeholders)",
            array_merge([$requestId], $ids)
        );
    }

    public static function logHistory(int $requestId, string $action, ?int $stepId, int $actorId, string $notes): void
    {
        $actor = Database::query("SELECT full_name FROM users WHERE id = ?", [$actorId])->fetch();
        $stepOrder = 0;
        if ($stepId) {
            $s = WorkflowTemplateService::getStep($stepId);
            $stepOrder = $s ? (int)$s['step_order'] : 0;
        }
        Database::query(
            "INSERT INTO request_history (request_id, step_id, step_order, action, actor_id, actor_name, notes)
             VALUES (?,?,?,?,?,?,?)",
            [$requestId, $stepId, $stepOrder, $action, $actorId, $actor['full_name'] ?? '', $notes]
        );
        Helpers::audit($action, 'requests', $requestId, $notes);
    }
}
