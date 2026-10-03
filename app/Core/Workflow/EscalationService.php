<?php
namespace App\Core\Workflow;

use App\Core\{Database, Helpers, WorkflowEngine};

/**
 * خدمة التصعيد والتذكير — تُشغَّل دورياً عبر cron.
 *
 * - auto_approve / auto_reject: قرار تلقائي عند تجاوز SLA
 * - escalate (افتراضي): إعادة إسناد المهمة لمستوى أعلى مع إبقاء الطلب على نفس العقدة
 * - remind_after_hours: تذكير المكلَّف مرة واحدة قبل/بعد الاستحقاق
 */
final class EscalationService
{
    /** نقطة التشغيل الدورية — تعيد إحصائية التنفيذ */
    public static function run(): array
    {
        return [
            'auto_actions' => self::processOverdue(),
            'reminders'    => self::processReminders(),
            'pending_reminders' => self::remindPendingRequests(),
            'overtime_expired' => self::expireOvertimeRequests(),
        ];
    }

    /** المهام المتأخرة عن SLA */
    private static function processOverdue(): int
    {
        $tasks = Database::query(
            "SELECT wt.*, n.config_json, n.title AS node_title
             FROM workflow_tasks wt
             JOIN workflow_nodes n ON n.id = wt.node_id
             JOIN requests r ON r.id = wt.request_id
             WHERE wt.status = 'pending' AND wt.due_at IS NOT NULL AND wt.due_at < NOW()
               AND r.status = 'pending'"
        )->fetchAll();
        $count = 0;
        foreach ($tasks as $t) {
            $config = json_decode($t['config_json'] ?? '{}', true) ?: [];
            $sla = (array)($config['sla'] ?? []);
            $autoAction = (string)($sla['auto_action'] ?? 'escalate');
            try {
                if ($autoAction === 'auto_approve' || $autoAction === 'auto_reject') {
                    $decision = $autoAction === 'auto_approve' ? 'approved' : 'rejected';
                    Engine::decide((int)$t['request_id'], $decision, 'قرار تلقائي: تجاوز المدة المحددة (SLA)', (int)$t['assigned_to']);
                } else {
                    self::escalate($t, $sla);
                }
                $count++;
            } catch (\Throwable $e) {
                error_log('Escalation failed for task ' . $t['id'] . ': ' . $e->getMessage());
            }
        }
        return $count;
    }

    /** تصعيد مهمة: إغلاق الحالية وإسنادها للمستوى الأعلى على نفس العقدة */
    private static function escalate(array $task, array $sla): void
    {
        $req = WorkflowEngine::getRequest((int)$task['request_id']);
        $targets = !empty($sla['escalate_to'])
            ? ApproverResolver::resolve((array)$sla['escalate_to'], $req)
            : [];
        if (!$targets) {
            // افتراضي: المدير المباشر للمكلف الحالي
            $empId = (int)(Database::query(
                "SELECT e.id FROM employees e JOIN users u ON u.id = e.user_id WHERE u.id = ?",
                [(int)$task['assigned_to']]
            )->fetchColumn() ?: 0);
            if ($empId) {
                $targets = ApproverResolver::resolve(['type' => 'direct_manager'], ['requester_employee_id' => $empId]);
            }
        }
        if (!$targets) {
            $targets = Database::query(
                "SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.name = 'مدير النظام'"
            )->fetchAll(\PDO::FETCH_COLUMN);
        }
        if (!$targets) return;

        Database::query("UPDATE workflow_tasks SET status = 'escalated', decided_at = NOW(), notes = CONCAT(COALESCE(notes,''), ' [صُعّدت]') WHERE id = ?", [(int)$task['id']]);
        $hours = max(1, (int)($sla['max_hours'] ?? 24));
        $dueAt = date('Y-m-d H:i:s', time() + $hours * 3600);
        foreach ($targets as $uid) {
            Database::query(
                "INSERT INTO workflow_tasks (request_id, node_id, assigned_to, assigned_by_type, due_at)
                 VALUES (?,?,?,'escalation',?)
                 ON DUPLICATE KEY UPDATE status = 'pending', decided_at = NULL, notes = NULL, due_at = VALUES(due_at)",
                [(int)$task['request_id'], (int)$task['node_id'], (int)$uid, $dueAt]
            );
            Helpers::notify((int)$uid, 'تصعيد: طلب موافقة متأخر', (string)($req['title'] ?? ''), '/workflows/requests/' . (int)$task['request_id']);
        }
        AuditLogger::log((int)$task['request_id'], 'escalated', (int)$task['node_id'], (int)$task['assigned_to'],
            'صُعّدت من ' . (string)($task['assigned_to']) . ' إلى: ' . implode(',', $targets));
    }

    /** تذكير المكلفين بعد remind_after_hours من إنشاء المهمة */
    private static function processReminders(): int
    {
        $tasks = Database::query(
            "SELECT wt.*, n.config_json, r.title AS req_title
             FROM workflow_tasks wt
             JOIN workflow_nodes n ON n.id = wt.node_id
             JOIN requests r ON r.id = wt.request_id
             WHERE wt.status = 'pending' AND wt.reminded_at IS NULL AND r.status = 'pending'"
        )->fetchAll();
        $count = 0;
        foreach ($tasks as $t) {
            $config = json_decode($t['config_json'] ?? '{}', true) ?: [];
            $hours = (int)($config['sla']['remind_after_hours'] ?? 0);
            if ($hours <= 0) continue;
            if (strtotime((string)$t['created_at']) + $hours * 3600 > time()) continue;
            Helpers::notify(
                (int)$t['assigned_to'],
                'تذكير: طلب موافقة بانتظارك',
                (string)($t['req_title'] ?? ''),
                '/workflows/requests/' . (int)$t['request_id']
            );
            Database::query("UPDATE workflow_tasks SET reminded_at = NOW() WHERE id = ?", [(int)$t['id']]);
            $count++;
        }
        return $count;
    }

    /** تذكير منشئ الطلب والمدير المباشر بعد 24 ساعة من إنشاء طلب معلق */
    public static function remindPendingRequests(): int
    {
        $cutoff = date('Y-m-d H:i:s', time() - 24 * 3600);
        $requests = Database::query(
            "SELECT r.*, t.name AS template_name, u.id AS requester_user_id, e.id AS employee_id, e.manager_id
             FROM requests r
             JOIN approval_templates t ON t.id = r.template_id
             JOIN users u ON u.id = r.requester_id
             LEFT JOIN employees e ON e.id = r.requester_employee_id
             WHERE r.status = 'pending' AND r.created_at <= ?",
            [$cutoff]
        )->fetchAll();

        $count = 0;
        foreach ($requests as $req) {
            $requestId = (int)$req['id'];
            $requesterId = (int)$req['requester_user_id'];
            $templateName = $req['template_name'] ?? 'طلب موافقة';
            $requestNo = $req['request_no'] ?? ('#' . $requestId);
            $title = $req['title'] ?? '';
            $createdAt = $req['created_at'] ?? '';
            $hoursPending = floor((time() - strtotime($createdAt)) / 3600);
            $link = '/workflows/requests/' . $requestId;

            // تذكير لمنشئ الطلب
            if (!self::reminderSent($requestId, $requesterId, 'requester')) {
                $body = "$templateName $requestNo — ما زال معلقاً منذ $hoursPending ساعة.";
                Helpers::notify($requesterId, 'تذكير: طلبك معلق', $body, $link, null, null, [], null, 'request', $requestId, 'pending_reminder', [
                    'template_name' => $templateName,
                    'request_no' => $requestNo,
                    'hours_pending' => $hoursPending,
                    'title' => $title,
                ]);
                self::logReminder($requestId, $requesterId, 'requester');
                $count++;
            }

            // تذكير للمدير المباشر
            $managerId = (int)($req['manager_id'] ?? 0);
            if ($managerId) {
                $managerUser = Database::query("SELECT user_id FROM employees WHERE id = ?", [$managerId])->fetchColumn();
                if ($managerUser && !self::reminderSent($requestId, (int)$managerUser, 'manager')) {
                    $body = "$templateName $requestNo — طلب $title من أحد مرؤوسيك معلق منذ $hoursPending ساعة ويتطلب موافقتك.";
                    Helpers::notify((int)$managerUser, 'تذكير: طلب موافقة معلق لمرؤوسك', $body, $link, null, null, [], null, 'request', $requestId, 'pending_reminder_manager', [
                        'template_name' => $templateName,
                        'request_no' => $requestNo,
                        'hours_pending' => $hoursPending,
                        'title' => $title,
                    ]);
                    self::logReminder($requestId, (int)$managerUser, 'manager');
                    $count++;
                }
            }
        }
        return $count;
    }

    private static function reminderSent(int $requestId, int $userId, string $type): bool
    {
        return (bool) Database::query(
            "SELECT 1 FROM notification_reminders WHERE request_id = ? AND user_id = ? AND reminder_type = ? LIMIT 1",
            [$requestId, $userId, $type]
        )->fetch();
    }

    private static function logReminder(int $requestId, int $userId, string $type): void
    {
        Database::query(
            "INSERT INTO notification_reminders (request_id, user_id, reminder_type) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE sent_at = NOW()",
            [$requestId, $userId, $type]
        );
    }

    /** إنهاء صلاحية طلبات العمل الإضافي المعلقة بعد 48 ساعة */
    public static function expireOvertimeRequests(): int
    {
        $cutoff = date('Y-m-d H:i:s', time() - 48 * 3600);
        $requests = Database::query(
            "SELECT o.*, e.user_id AS employee_user_id, e.full_name, e.manager_id
             FROM overtime_requests o
             JOIN employees e ON e.id = o.employee_id
             WHERE o.status = 'pending' AND o.created_at <= ?",
            [$cutoff]
        )->fetchAll();

        $count = 0;
        foreach ($requests as $req) {
            $overtimeId = (int)$req['id'];
            $workflowRequestId = (int)($req['workflow_request_id'] ?? 0);
            $employeeUserId = (int)($req['employee_user_id'] ?? 0);
            $fullName = $req['full_name'] ?? 'موظف';
            $requestDate = $req['request_date'] ?? '';
            $hours = $req['hours'] ?? 0;

            Database::query(
                "UPDATE overtime_requests SET status = 'expired', updated_at = NOW() WHERE id = ?",
                [$overtimeId]
            );

            if ($workflowRequestId) {
                Database::query(
                    "UPDATE requests SET status = 'rejected', updated_at = NOW() WHERE id = ?",
                    [$workflowRequestId]
                );
                Database::query(
                    "INSERT INTO request_history (request_id, action, notes, actor_id)
                     VALUES (?, 'rejected', 'منتهي الصلاحية تلقائياً: تجاوز 48 ساعة', 0)",
                    [$workflowRequestId]
                );
                Database::query(
                    "UPDATE workflow_tasks SET status = 'rejected', decided_at = NOW(), notes = 'منتهي الصلاحية تلقائياً' WHERE request_id = ? AND status = 'pending'",
                    [$workflowRequestId]
                );
            }

            if ($employeeUserId) {
                Helpers::notify(
                    $employeeUserId,
                    'طلب عمل إضافي منتهي الصلاحية',
                    "$fullName — طلب العمل الإضافي ليوم $requestDate ($hours ساعة) لم يُعالَج خلال 48 ساعة وتم إنهاء صلاحيته.",
                    '/hr/overtime/' . $overtimeId,
                    null, null, [], null,
                    'overtime_request', $overtimeId, 'expired', [
                        'request_date' => $requestDate,
                        'hours' => $hours,
                    ]
                );
            }

            $count++;
        }
        return $count;
    }
}
