<?php
namespace App\Core\Workflow;

use App\Core\{Database, Helpers, WorkflowEngine};

/**
 * منفّذ مخطط Workflow (Node Graph Engine).
 * يعمل بالتوازي مع محرك الخطوات الحالي: يُفعّل فقط عند وجود نسخة منشورة للقالب.
 */
final class Engine
{
    /** هل للقالب مخطط منشور مطابق للنطاق؟ */
    public static function hasPublishedGraph(int $templateId, ?array $req = null): bool
    {
        return self::pickVersion($templateId, $req) !== null;
    }

    /** بدء سير الطلب عبر المخطط. تعيد false إن لم يوجد مخطط منشور (للمسار القديم) */
    public static function start(int $requestId, int $actorId): bool
    {
        $req = WorkflowEngine::getRequest($requestId);
        if (!$req) return false;
        $version = self::pickVersion((int)$req['template_id'], $req);
        if (!$version) return false;

        Database::query(
            "UPDATE requests SET workflow_version_id = ?, updated_at = NOW() WHERE id = ?",
            [(int)$version['id'], $requestId]
        );
        $start = self::nodeByType((int)$version['id'], 'start');
        if (!$start) throw new \RuntimeException('المخطط لا يحتوي عقدة بداية');
        AuditLogger::log($requestId, 'node_entered', (int)$start['id'], $actorId, 'بدء سير المخطط: ' . $version['version_no']);
        $next = self::followEdge((int)$start['id'], 'default');
        if (!$next) {
            self::complete($requestId, 'approved', $actorId, 'لا توجد مراحل — اعتماد تلقائي');
            return true;
        }
        self::advance($requestId, $next, $actorId);
        return true;
    }

    /** اتخاذ قرار على مهمة ضمن المخطط */
    public static function decide(int $requestId, string $action, string $notes, int $actorId): void
    {
        $req = WorkflowEngine::getRequest($requestId);
        if (!$req || empty($req['workflow_version_id'])) {
            throw new \RuntimeException('هذا الطلب لا يسير على مخطط ديناميكي');
        }
        if ($req['status'] !== 'pending') throw new \RuntimeException('الطلب ليس في حالة المعالجة');

        $task = self::pendingTaskFor($requestId, (int)$req['current_node_id'], $actorId);
        if (!$task) throw new \RuntimeException('ليس لديك مهمة معلقة على هذا الطلب');

        $node = Database::query("SELECT * FROM workflow_nodes WHERE id = ?", [(int)$task['node_id']])->fetch();
        $config = json_decode($node['config_json'] ?? '{}', true) ?: [];
        $taskStatus = $action === 'approved' ? 'approved' : ($action === 'rejected' ? 'rejected' : 'returned');
        Database::query(
            "UPDATE workflow_tasks SET status = ?, decided_at = NOW(), notes = ? WHERE id = ?",
            [$taskStatus, $notes, (int)$task['id']]
        );
        AuditLogger::log($requestId, $action, (int)$node['id'], $actorId, $notes);

        if ($action === 'rejected') { self::handleReject($req, $config, $node, $actorId, $notes); return; }
        if ($action === 'returned_for_edit') {
            self::finishRequest($req, 'returned_for_edit', $actorId, $notes, 'أعيد طلبك للتعديل');
            return;
        }

        // موافقة: هل اكتملت العقدة حسب نمطها؟
        if (!self::nodeCompleted((int)$node['id'], $requestId, $config)) return;
        Database::query(
            "UPDATE workflow_tasks SET status = 'skipped' WHERE request_id = ? AND node_id = ? AND status = 'pending'",
            [$requestId, (int)$node['id']]
        );
        $next = self::followEdge((int)$node['id'], 'default');
        if (!$next) { self::complete($requestId, 'approved', $actorId, ''); return; }
        self::advance($requestId, $next, $actorId);
    }

    /**
     * نقطة الدخول البرمجية لأي وحدة: إنشاء طلب مرتبط بكيان وبدء سيره على المخطط المنشور.
     * تعيد request_id أو null إن لم يوجد تعريف منشور مطابق لنوع الكيان.
     */
    public static function startForEntity(string $entityType, int $entityId, int $userId, array $context = []): ?int
    {
        $def = self::pickDefinitionByType($entityType, $context);
        if (!$def || empty($def['template_id'])) return null;

        $template = \App\Core\WorkflowTemplateService::getTemplate((int)$def['template_id']);
        if (!$template) return null;

        $data = array_merge($context, [
            'title' => (string)($context['title'] ?? ($template['name'] . ' #' . $entityId)),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ]);
        if (isset($context['amount']) && !isset($data[$template['amount_field'] ?: 'amount'])) {
            $data[$template['amount_field'] ?: 'amount'] = (float)$context['amount'];
        }

        $requestId = WorkflowEngine::createRequest((int)$template['id'], $userId, $data, true);

        // createRequest يحتفظ فقط بحقول القالب — نعيد حقن مفاتيح الكيان في data_json
        $payload = json_decode((string)Database::query("SELECT data_json FROM requests WHERE id = ?", [$requestId])->fetchColumn(), true) ?: [];
        foreach (['entity_type', 'entity_id'] as $k) { $payload[$k] = $data[$k]; }
        foreach ($context as $k => $v) { if (!isset($payload[$k])) $payload[$k] = $v; }
        Database::query("UPDATE requests SET data_json = ? WHERE id = ?", [json_encode($payload, JSON_UNESCAPED_UNICODE), $requestId]);

        if (!empty($context['employee_id'])) {
            $emp = Database::query(
                "SELECT e.id, op.id AS position_id FROM employees e
                 LEFT JOIN org_positions op ON op.employee_id = e.id WHERE e.id = ?",
                [(int)$context['employee_id']]
            )->fetch();
            if ($emp) {
                Database::query(
                    "UPDATE requests SET requester_employee_id = ?, requester_position_id = ? WHERE id = ?",
                    [(int)$emp['id'], $emp['position_id'] ?: null, $requestId]
                );
            }
        }

        EntityRegistry::bind($entityType, $entityId, $requestId);
        WorkflowEngine::submitRequest($requestId, $userId);
        return $requestId;
    }

    /** تعريف منشور مطابق لنوع الكيان (مع النطاق والأولوية) */
    private static function pickDefinitionByType(string $entityType, array $context = []): ?array
    {
        $defs = Database::query(
            "SELECT * FROM workflow_definitions
             WHERE request_type = ? AND is_active = 1 AND template_id IS NOT NULL
               AND (valid_from IS NULL OR valid_from <= CURDATE())
               AND (valid_to IS NULL OR valid_to >= CURDATE())
             ORDER BY priority ASC",
            [$entityType]
        )->fetchAll();
        foreach ($defs as $def) {
            $branchIds = json_decode($def['branch_ids'] ?? 'null', true);
            if (is_array($branchIds) && $branchIds && !empty($context['branch_id'])
                && !in_array((int)$context['branch_id'], array_map('intval', $branchIds), true)) {
                continue;
            }
            $hasPublished = Database::query(
                "SELECT 1 FROM workflow_versions WHERE definition_id = ? AND status = 'published' LIMIT 1",
                [(int)$def['id']]
            )->fetch();
            if ($hasPublished) return $def;
        }
        return null;
    }

    /** تحويل مهمة معلقة لمستخدم آخر (تفويض لمرة واحدة مع توثيق) */
    public static function delegateTask(int $requestId, int $fromUserId, int $toUserId, string $notes = ''): void
    {
        $req = WorkflowEngine::getRequest($requestId);
        if (!$req) throw new \RuntimeException('الطلب غير موجود');
        if ($req['status'] !== 'pending') throw new \RuntimeException('الطلب ليس في حالة المعالجة');
        if ($toUserId === $fromUserId) throw new \RuntimeException('لا يمكن التحويل لنفسك');

        if (!empty($req['workflow_version_id'])) {
            $task = Database::query(
                "SELECT * FROM workflow_tasks WHERE request_id = ? AND node_id = ? AND assigned_to = ? AND status = 'pending' LIMIT 1",
                [$requestId, (int)$req['current_node_id'], $fromUserId]
            )->fetch();
            if (!$task) throw new \RuntimeException('ليس لديك مهمة معلقة على هذا الطلب');
            $conflict = Database::query(
                "SELECT 1 FROM workflow_tasks WHERE request_id = ? AND node_id = ? AND assigned_to = ? AND status = 'pending' LIMIT 1",
                [$requestId, (int)$task['node_id'], $toUserId]
            )->fetch();
            if ($conflict) throw new \RuntimeException('المستخدم المستهدف لديه مهمة على هذه المرحلة');
            Database::query(
                "UPDATE workflow_tasks SET assigned_to = ?, assigned_by_type = 'delegated' WHERE id = ?",
                [$toUserId, (int)$task['id']]
            );
            Helpers::notify($toUserId, 'حُوّلت إليك مهمة موافقة', (string)$req['title'], '/workflows/requests/' . $requestId);
            PushService::queue($toUserId, 'حُوّلت إليك مهمة موافقة', (string)$req['title'], '/workflows/requests/' . $requestId);
            Database::query(
                "INSERT IGNORE INTO request_participants (request_id, user_id, role) VALUES (?,?,'approver')",
                [$requestId, $toUserId]
            );
            AuditLogger::log($requestId, 'delegated', (int)$task['node_id'], $fromUserId,
                'حوّلها إلى #' . $toUserId . ($notes !== '' ? ': ' . $notes : ''));
            return;
        }

        // نظام الخطوات القديم: تفويض ناعم (مشارك + إشعار) لأن الخطوات مبنية على الأدوار
        $_SESSION['user_id'] = $fromUserId;
        $steps = WorkflowEngine::resolveSteps((int)$req['template_id'], (int)$req['current_step'], $requestId);
        $canDecide = false;
        foreach ($steps as $s) {
            if (WorkflowEngine::userCanDecideStep($s, $req)) { $canDecide = true; break; }
        }
        if (!$canDecide) throw new \RuntimeException('لا تملك صلاحية القرار على هذا الطلب');
        Database::query(
            "INSERT IGNORE INTO request_participants (request_id, user_id, role) VALUES (?,?,'approver')",
            [$requestId, $toUserId]
        );
        Helpers::notify($toUserId, 'حُوّل إليك طلب موافقة', (string)$req['title'], '/workflows/requests/' . $requestId);
        WorkflowEngine::logHistory($requestId, 'delegated', null, $fromUserId, 'حوّلها إلى #' . $toUserId . ($notes !== '' ? ': ' . $notes : ''));
    }

    // ===== الجسور العامة =====

    /** طلبات عليها مهام معلقة للمستخدم (تُدمج مع قائمة المحرك القديم) */
    public static function pendingRequestsFor(int $userId): array
    {
        return Database::query(
            "SELECT r.*, t.name AS template_name, t.icon, t.code AS template_code,
                    u.full_name AS requester_name
             FROM workflow_tasks wt
             JOIN requests r ON r.id = wt.request_id
             JOIN approval_templates t ON t.id = r.template_id
             LEFT JOIN users u ON u.id = r.requester_id
             WHERE wt.assigned_to = ? AND wt.status = 'pending' AND r.status = 'pending'
             ORDER BY r.id DESC",
            [$userId]
        )->fetchAll();
    }

    /** هل للمستخدم مهمة معلقة على الطلب (أو يستطيع القرار كمدير نظام)؟ */
    public static function userHasPendingTask(int $requestId, int $userId): bool
    {
        $row = Database::query("SELECT current_node_id FROM requests WHERE id = ?", [$requestId])->fetch();
        if (!$row || empty($row['current_node_id'])) return false;
        return self::pendingTaskFor($requestId, (int)$row['current_node_id'], $userId) !== null;
    }

    /** مخطط الطلب مع حالة كل عقدة (لشاشة المتابعة المرئية) */
    public static function graphForRequest(int $requestId): array
    {
        $row = Database::query("SELECT workflow_version_id, current_node_id FROM requests WHERE id = ?", [$requestId])->fetch();
        if (!$row || empty($row['workflow_version_id'])) {
            return ['nodes' => [], 'edges' => [], 'tasks' => [], 'current_node_id' => 0];
        }
        $vid = (int)$row['workflow_version_id'];
        return [
            'nodes' => Database::query("SELECT * FROM workflow_nodes WHERE version_id = ? ORDER BY id", [$vid])->fetchAll(),
            'edges' => Database::query("SELECT * FROM workflow_edges WHERE version_id = ? ORDER BY id", [$vid])->fetchAll(),
            'tasks' => Database::query(
                "SELECT wt.*, u.full_name FROM workflow_tasks wt JOIN users u ON u.id = wt.assigned_to
                 WHERE wt.request_id = ? ORDER BY wt.id",
                [$requestId]
            )->fetchAll(),
            'current_node_id' => (int)($row['current_node_id'] ?? 0),
        ];
    }

    // ===== اختيار النسخة المنشورة المطابقة للنطاق =====

    private static function pickVersion(int $templateId, ?array $req): ?array
    {
        $defs = Database::query(
            "SELECT * FROM workflow_definitions
             WHERE template_id = ? AND is_active = 1
               AND (valid_from IS NULL OR valid_from <= CURDATE())
               AND (valid_to IS NULL OR valid_to >= CURDATE())
             ORDER BY priority ASC",
            [$templateId]
        )->fetchAll();
        foreach ($defs as $def) {
            if (!self::scopeMatches($def, $req)) continue;
            $version = Database::query(
                "SELECT * FROM workflow_versions
                 WHERE definition_id = ? AND status = 'published'
                 ORDER BY version_no DESC LIMIT 1",
                [(int)$def['id']]
            )->fetch();
            if ($version) return $version;
        }
        return null;
    }

    private static function scopeMatches(array $def, ?array $req): bool
    {
        if (!$req) return true;
        $branchIds = json_decode($def['branch_ids'] ?? 'null', true);
        if (is_array($branchIds) && $branchIds
            && !in_array((int)($req['branch_id'] ?? 0), array_map('intval', $branchIds), true)) {
            return false;
        }
        $deptIds = json_decode($def['department_ids'] ?? 'null', true);
        if (is_array($deptIds) && $deptIds) {
            $dept = (string)($req['data']['department'] ?? '');
            if (!in_array($dept, array_map('strval', $deptIds), true)) return false;
        }
        return true;
    }

    // ===== المشي على العقد =====

    private static function advance(int $requestId, array $node, int $actorId): void
    {
        $guard = 0;
        while ($node && $guard++ < 25) {
            $type = $node['node_type'];
            AuditLogger::log($requestId, 'node_entered', (int)$node['id'], $actorId, (string)$node['title']);

            if ($type === 'approval') { self::enterApproval($requestId, $node, $actorId); return; }
            if ($type === 'end')      { self::complete($requestId, 'approved', $actorId, ''); return; }
            if ($type === 'reject')   { self::complete($requestId, 'rejected', $actorId, (string)$node['title']); return; }
            if ($type === 'return')   {
                $req = WorkflowEngine::getRequest($requestId);
                self::finishRequest($req, 'returned_for_edit', $actorId, '', 'أعيد طلبك للتعديل');
                return;
            }
            if ($type === 'wait') {
                Database::query("UPDATE requests SET current_node_id = ? WHERE id = ?", [(int)$node['id'], $requestId]);
                return; // يُستأنف عبر الجدولة/SLA (المرحلة 4)
            }

            $config = json_decode($node['config_json'] ?? '{}', true) ?: [];
            if ($type === 'condition') {
                $ok = RuleEvaluator::evaluate(self::nodeRule($config), self::buildContext($requestId));
                $node = self::followEdge((int)$node['id'], $ok ? 'yes' : 'no');
                continue;
            }
            if ($type === 'action') {
                // fail-open: فشل الإجراء الجانبي لا يوقف المخطط — يُسجّل في التدقيق
                try {
                    ActionDispatcher::run((string)($config['action'] ?? ''), (array)($config['params'] ?? []), self::buildContext($requestId));
                    AuditLogger::log($requestId, 'action_executed', (int)$node['id'], $actorId, (string)($config['action'] ?? ''));
                } catch (\Throwable $e) {
                    AuditLogger::log($requestId, 'action_executed', (int)$node['id'], $actorId, 'فشل الإجراء: ' . $e->getMessage());
                }
                $node = self::followEdge((int)$node['id'], 'default');
                continue;
            }
            if ($type === 'notify') {
                self::runNotifyNode($requestId, $node, $config);
                $node = self::followEdge((int)$node['id'], 'default');
                continue;
            }
            // start أو أي نوع آخر: تابع بالمسار الافتراضي
            $node = self::followEdge((int)$node['id'], 'default');
        }
        if ($guard >= 25) throw new \RuntimeException('المخطط تجاوز الحد الأقصى للعقد (حلقة محتملة)');
    }

    private static function enterApproval(int $requestId, array $node, int $actorId): void
    {
        $config = json_decode($node['config_json'] ?? '{}', true) ?: [];
        $req = WorkflowEngine::getRequest($requestId);
        $approvers = ApproverResolver::resolve((array)($config['approver'] ?? []), $req);
        if (!$approvers && !empty($config['fallback'])) {
            $approvers = ApproverResolver::resolve((array)$config['fallback'], $req);
        }
        if (!$approvers) {
            $approvers = Database::query(
                "SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.name = 'مدير النظام'"
            )->fetchAll(\PDO::FETCH_COLUMN);
        }
        if (!$approvers) {
            AuditLogger::log($requestId, 'auto_approved', (int)$node['id'], $actorId, 'لا يوجد معتمد مطابق — تخطّي تلقائي');
            $next = self::followEdge((int)$node['id'], 'default');
            if ($next) { self::advance($requestId, $next, $actorId); return; }
            self::complete($requestId, 'approved', $actorId, '');
            return;
        }

        // SLA: من إعداد العقدة أولاً (المصمم)، ثم جدول workflow_sla كاحتياطي
        $slaHours = (int)($config['sla']['max_hours'] ?? 0);
        if ($slaHours <= 0) {
            $sla = Database::query("SELECT * FROM workflow_sla WHERE node_id = ?", [(int)$node['id']])->fetch();
            $slaHours = (int)($sla['max_hours'] ?? 0);
        }
        $dueAt = $slaHours > 0 ? date('Y-m-d H:i:s', time() + $slaHours * 3600) : null;
        $byType = (string)($config['approver']['type'] ?? 'unknown');
        foreach ($approvers as $uid) {
            // ON DUPLICATE: إعادة دخول العقدة (إعادة إرسال/حلقة) تُعيد تفعيل المهمة بدل تجاهلها
            Database::query(
                "INSERT INTO workflow_tasks (request_id, node_id, assigned_to, assigned_by_type, due_at)
                 VALUES (?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE status = 'pending', decided_at = NULL, notes = NULL, due_at = VALUES(due_at)",
                [$requestId, (int)$node['id'], (int)$uid, $byType, $dueAt]
            );
            Helpers::notify((int)$uid, 'طلب موافقة بانتظارك', (string)($req['title'] ?? ''), '/workflows/requests/' . $requestId);
            PushService::queue((int)$uid, 'طلب موافقة بانتظارك', (string)($req['title'] ?? ''), '/workflows/requests/' . $requestId);
            Database::query(
                "INSERT IGNORE INTO request_participants (request_id, user_id, role) VALUES (?,?,'approver')",
                [$requestId, (int)$uid]
            );
        }
        Database::query("UPDATE requests SET current_node_id = ?, updated_at = NOW() WHERE id = ?", [(int)$node['id'], $requestId]);
    }

    /** اكتمال عقدة الموافقة حسب نمطها: any / all / count */
    private static function nodeCompleted(int $nodeId, int $requestId, array $config): bool
    {
        $mode = (string)($config['approval_mode'] ?? 'all');
        if ($mode === 'any') return true;
        $approved = (int)Database::query(
            "SELECT COUNT(*) FROM workflow_tasks WHERE request_id = ? AND node_id = ? AND status = 'approved'",
            [$requestId, $nodeId]
        )->fetchColumn();
        if ($mode === 'count') {
            return $approved >= max(1, (int)($config['required_count'] ?? 1));
        }
        $pending = (int)Database::query(
            "SELECT COUNT(*) FROM workflow_tasks WHERE request_id = ? AND node_id = ? AND status = 'pending'",
            [$requestId, $nodeId]
        )->fetchColumn();
        return $pending === 0 && $approved > 0;
    }

    private static function handleReject(array $req, array $config, array $node, int $actorId, string $notes): void
    {
        $onReject = (string)($config['on_reject'] ?? 'final');
        if ($onReject === 'return_requester') {
            self::finishRequest($req, 'returned_for_edit', $actorId, $notes, 'أعيد طلبك للتعديل: ' . $notes);
            return;
        }
        if ($onReject === 'return_previous') {
            $prev = Database::query(
                "SELECT node_id FROM workflow_tasks WHERE request_id = ? AND node_id <> ? ORDER BY id DESC LIMIT 1",
                [(int)$req['id'], (int)$node['id']]
            )->fetchColumn();
            if ($prev) {
                $assignees = Database::query(
                    "SELECT DISTINCT assigned_to FROM workflow_tasks WHERE request_id = ? AND node_id = ?",
                    [(int)$req['id'], (int)$prev]
                )->fetchAll(\PDO::FETCH_COLUMN);
                foreach ($assignees as $uid) {
                    Database::query(
                        "INSERT INTO workflow_tasks (request_id, node_id, assigned_to, assigned_by_type, status)
                         VALUES (?,?,?,'return_previous','pending')
                         ON DUPLICATE KEY UPDATE status = 'pending', decided_at = NULL",
                        [(int)$req['id'], (int)$prev, (int)$uid]
                    );
                    Helpers::notify((int)$uid, 'أعيد طلب إلى مرحلتك', (string)($req['title'] ?? ''), '/workflows/requests/' . (int)$req['id']);
                }
                Database::query("UPDATE requests SET current_node_id = ?, updated_at = NOW() WHERE id = ?", [(int)$prev, (int)$req['id']]);
                AuditLogger::log((int)$req['id'], 'returned_for_edit', (int)$prev, $actorId, 'إعادة لمرحلة سابقة: ' . $notes);
                return;
            }
        }
        self::finishRequest($req, 'rejected', $actorId, $notes, 'تم رفض طلبك: ' . $notes);
    }

    private static function finishRequest(array $req, string $status, int $actorId, string $notes, string $notifyMsg): void
    {
        Database::query("UPDATE requests SET status = ?, updated_at = NOW() WHERE id = ?", [$status, (int)$req['id']]);
        AuditLogger::log((int)$req['id'], $status, null, $actorId, $notes);
        WorkflowEngine::triggerOutcomeHandlers($req, $status, $actorId, $notes);
        EntityRegistry::fireOutcome($req, $status, $actorId, $notes);
        Helpers::notify((int)$req['requester_id'], $notifyMsg, (string)($req['title'] ?? ''), '/workflows/requests/' . (int)$req['id']);
    }

    private static function complete(int $requestId, string $status, int $actorId, string $notes): void
    {
        $req = WorkflowEngine::getRequest($requestId);
        if (!$req) return;
        $msg = $status === 'approved' ? 'تمت الموافقة على طلبك' : 'تم رفض طلبك';
        self::finishRequest($req, $status, $actorId, $notes, $msg . ($notes !== '' ? ': ' . $notes : ''));
    }

    // ===== أدوات مساعدة =====

    private static function followEdge(int $fromNodeId, string $handle): ?array
    {
        $to = Database::query(
            "SELECT to_node_id FROM workflow_edges WHERE from_node_id = ? AND handle = ? ORDER BY id LIMIT 1",
            [$fromNodeId, $handle]
        )->fetchColumn();
        if (!$to && $handle !== 'default') {
            $to = Database::query(
                "SELECT to_node_id FROM workflow_edges WHERE from_node_id = ? AND handle = 'default' ORDER BY id LIMIT 1",
                [$fromNodeId]
            )->fetchColumn();
        }
        if (!$to) return null;
        return Database::query("SELECT * FROM workflow_nodes WHERE id = ?", [(int)$to])->fetch() ?: null;
    }

    private static function nodeByType(int $versionId, string $type): ?array
    {
        return Database::query(
            "SELECT * FROM workflow_nodes WHERE version_id = ? AND node_type = ? ORDER BY id LIMIT 1",
            [$versionId, $type]
        )->fetch() ?: null;
    }

    private static function nodeRule(array $config): array
    {
        if (!empty($config['rule_id'])) {
            $json = Database::query(
                "SELECT rule_json FROM workflow_rules WHERE id = ? AND is_active = 1",
                [(int)$config['rule_id']]
            )->fetchColumn();
            return $json ? (json_decode($json, true) ?: []) : [];
        }
        return (array)($config['rule'] ?? []);
    }

    private static function pendingTaskFor(int $requestId, int $nodeId, int $userId): ?array
    {
        $task = Database::query(
            "SELECT * FROM workflow_tasks
             WHERE request_id = ? AND node_id = ? AND assigned_to = ? AND status = 'pending' LIMIT 1",
            [$requestId, $nodeId, $userId]
        )->fetch();
        if ($task) return $task;
        $isAdmin = Database::query(
            "SELECT 1 FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND r.name = 'مدير النظام'",
            [$userId]
        )->fetch();
        if ($isAdmin) {
            return Database::query(
                "SELECT * FROM workflow_tasks WHERE request_id = ? AND node_id = ? AND status = 'pending' LIMIT 1",
                [$requestId, $nodeId]
            )->fetch() ?: null;
        }
        return null;
    }

    private static function runNotifyNode(int $requestId, array $node, array $config): void
    {
        $map = ['in_app' => 'notify.in_app', 'email' => 'notify.email', 'whatsapp' => 'notify.whatsapp', 'sms' => 'notify.sms'];
        foreach ($map as $channel => $action) {
            if (empty($config['channels'][$channel])) continue;
            ActionDispatcher::run($action, [
                'targets' => (array)($config['targets'] ?? []),
                'title'   => (string)($config['title_msg'] ?? $node['title']),
                'body'    => (string)($config['body'] ?? ''),
                'link'    => '/workflows/requests/' . $requestId,
            ], self::buildContext($requestId));
        }
    }

    private static function buildContext(int $requestId): array
    {
        $req = WorkflowEngine::getRequest($requestId);
        $requester = WorkflowEngine::getUserWithPosition((int)$req['requester_id']) ?: [];
        $emp = Database::query(
            "SELECT department, admin_level, branch_id FROM employees WHERE id = ?",
            [(int)($req['requester_employee_id'] ?? 0)]
        )->fetch() ?: [];
        // تحميل صف الكيان المرتبط لدعم شروط entity.*
        $entity = [];
        $link = EntityRegistry::requestLink($requestId);
        if ($link) {
            $entity = EntityRegistry::entityRow($link['entity_type'], (int)$link['entity_id']) ?: [];
            if ($link['entity_type'] === 'leave_request' && !empty($entity['date_from']) && !empty($entity['date_to'])) {
                try {
                    $from = new \DateTime($entity['date_from']);
                    $to = new \DateTime($entity['date_to']);
                    $entity['days'] = (int)$from->diff($to)->format('%a') + 1;
                } catch (\Throwable $e) {
                    $entity['days'] = 0;
                }
            }
        }
        return [
            'data'      => (array)($req['data'] ?? []),
            'request'   => $req,
            'requester' => array_merge($requester, $emp),
            'entity'    => $entity,
        ];
    }
}
