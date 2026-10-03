<?php
namespace App\Core;

use App\Core\HrService;

/**
 * خدمة الموافقات متعددة المستويات
 */
class ApprovalService
{
    /** إنشاء طلب موافقة جديد لكيان معيّن */
    public static function request(string $entityType, int $entityId, float $amount = 0, ?int $requestedBy = null): ?int
    {
        $wf = Database::query(
            "SELECT * FROM legacy_approval_workflows WHERE entity_type = ? AND is_active = 1",
            [$entityType]
        )->fetch();
        if (!$wf) return null;

        Database::query(
            "INSERT INTO legacy_approval_requests (workflow_id, entity_type, entity_id, amount, requested_by)
             VALUES (?,?,?,?,?)",
            [$wf['id'], $entityType, $entityId, $amount, $requestedBy ?? Auth::id()]
        );
        $requestId = (int) Database::lastInsertId();

        Helpers::queueSync('legacy_approval_requests', $requestId, 'insert', [
            'workflow_id'  => $wf['id'],
            'entity_type'  => $entityType,
            'entity_id'    => $entityId,
            'amount'       => $amount,
            'requested_by' => $requestedBy ?? Auth::id(),
            'status'       => 'pending',
        ]);

        // إشعار أصحاب الدور في المستوى الأول
        $step = self::stepForRequest((int)$wf['id'], 1, $amount);
        if ($step) {
            Helpers::notifyRole((int)$step['role_id'], 'طلب موافقة جديد', $wf['name'], '/approvals');
        }
        return $requestId;
    }

    private static function stepForRequest(int $workflowId, int $stepOrder, float $amount): ?array
    {
        $row = Database::query(
            "SELECT * FROM legacy_approval_steps
             WHERE workflow_id = ? AND step_order = ? AND min_amount <= ?
             ORDER BY step_order LIMIT 1",
            [$workflowId, $stepOrder, $amount]
        )->fetch();
        return $row ?: null;
    }

    /**
     * هل يحق للمستخدم الحالي اتخاذ قرار على طلب موافقة؟
     */
    public static function canDecide(array $request): bool
    {
        if (Auth::role() === 'مدير النظام') {
            return true;
        }
        if (!Auth::can('approvals.decide')) {
            return false;
        }
        $step = Database::query(
            "SELECT s.*, r.level AS step_role_level FROM legacy_approval_steps s
             LEFT JOIN roles r ON r.id = s.role_id
             WHERE s.workflow_id = ? AND s.step_order = ?",
            [(int)$request['workflow_id'], (int)$request['current_step']]
        )->fetch();
        if (!$step) return false;
        $userRoleId = (int)($_SESSION['role_id'] ?? 0);
        if ((int)$step['role_id'] === $userRoleId) return true;
        $userLevel = (int)Database::query("SELECT level FROM roles WHERE id = ?", [$userRoleId])->fetchColumn();
        return $userLevel > 0 && (int)($step['step_role_level'] ?? 0) > 0 && $userLevel >= (int)$step['step_role_level'];
    }

    /**
     * اتخاذ قرار على طلب موافقة
     * يعيد الحالة النهائية للطلب: pending | approved | rejected
     */
    public static function decide(int $requestId, string $decision, string $notes = ''): string
    {
        $req = Database::query("SELECT * FROM legacy_approval_requests WHERE id = ?", [$requestId])->fetch();
        if (!$req || $req['status'] !== 'pending') {
            return $req['status'] ?? 'rejected';
        }
        if (!self::canDecide($req)) {
            return 'unauthorized';
        }

        Database::query(
            "INSERT INTO legacy_approval_actions (request_id, step_order, decided_by, decision, notes)
             VALUES (?,?,?,?,?)",
            [$requestId, $req['current_step'], Auth::id(), $decision, $notes]
        );

        Helpers::queueSync('legacy_approval_actions', $requestId, 'update', [
            'request_id' => $requestId,
            'step_order' => $req['current_step'],
            'decided_by' => Auth::id(),
            'decision'   => $decision,
            'notes'      => $notes,
        ]);

        if ($decision === 'rejected') {
            Database::query("UPDATE legacy_approval_requests SET status='rejected' WHERE id = ?", [$requestId]);
            self::applyEntityStatus($req['entity_type'], (int)$req['entity_id'], 'rejected');
            if ($req['entity_type'] === 'leave_request') {
                HrService::revertLeaveApproval((int)$req['entity_id']);
            }
            Helpers::queueSync('legacy_approval_requests', $requestId, 'update', ['status' => 'rejected']);
            return 'rejected';
        }

        // هل يوجد مستوى تالٍ؟
        $next = self::stepForRequest((int)$req['workflow_id'], (int)$req['current_step'] + 1, (float)$req['amount']);
        if ($next) {
            Database::query(
                "UPDATE legacy_approval_requests SET current_step = current_step + 1 WHERE id = ?",
                [$requestId]
            );
            Helpers::notifyRole((int)$next['role_id'], 'طلب موافقة بانتظارك', $req['entity_type'], '/approvals');
            Helpers::queueSync('legacy_approval_requests', $requestId, 'update', ['current_step' => (int)$req['current_step'] + 1]);
            return 'pending';
        }

        Database::query("UPDATE legacy_approval_requests SET status='approved' WHERE id = ?", [$requestId]);
        self::applyEntityStatus($req['entity_type'], (int)$req['entity_id'], 'approved');
        if ($req['entity_type'] === 'leave_request') {
            HrService::applyLeaveApproval((int)$req['entity_id']);
        }
        if ($req['entity_type'] === 'stock_transfer') {
            StockService::deliverTransfer((int)$req['entity_id']);
        }
        if ($req['entity_type'] === 'material_request_transfer') {
            MaterialRequestService::onTransferApproved((int)$req['entity_id']);
        }
        if ($req['entity_type'] === 'purchase_request') {
            MaterialRequestService::onPurchaseRequestApproved((int)$req['entity_id']);
        }
        Helpers::queueSync('legacy_approval_requests', $requestId, 'update', ['status' => 'approved']);
        return 'approved';
    }

    /** تحديث حالة الكيان الأصلي عند اكتمال الموافقة */
    private static function applyEntityStatus(string $entityType, int $entityId, string $status): void
    {
        $map = [
            'purchase_request'          => 'purchase_requests',
            'leave_request'             => 'leave_requests',
            'expense'                   => 'expenses',
            'salary_advance'            => 'salary_advances',
            'salary_adjustment'         => 'salary_adjustments',
            'hr_adjustment'             => 'hr_adjustments',
            'stock_transfer'            => 'stock_transfers',
            'material_request_transfer' => 'stock_transfers',
            'quotation'                 => 'quotations',
        ];
        if (!isset($map[$entityType])) return;

        $table = $map[$entityType];
        if ($entityType === 'quotation') {
            $status = $status === 'approved' ? 'accepted' : 'rejected';
        }
        Database::query(
            "UPDATE $table SET status = ?, approved_by = ? WHERE id = ?",
            [$status, Auth::id(), $entityId]
        );

        // توليد جدول السداد للسلف المعتمدة طويلة الأجل
        if ($entityType === 'salary_advance' && $status === 'approved') {
            $advance = Database::query("SELECT * FROM salary_advances WHERE id = ?", [$entityId])->fetch();
            if ($advance && (int)$advance['is_long_term']) {
                HrService::generateRepaymentSchedule($entityId);
            }
        }
    }

    /** الطلبات المعلقة التي يحق للمستخدم الحالي البتّ فيها */
    public static function pendingForCurrentUser(): array
    {
        return Database::query(
            "SELECT ar.*, aw.name AS workflow_name, u.full_name AS requester
             FROM legacy_approval_requests ar
             JOIN legacy_approval_workflows aw ON aw.id = ar.workflow_id
             JOIN legacy_approval_steps s ON s.workflow_id = ar.workflow_id AND s.step_order = ar.current_step
             LEFT JOIN users u ON u.id = ar.requested_by
             WHERE ar.status = 'pending' AND s.role_id = ?
             ORDER BY ar.created_at",
            [$_SESSION['role_id'] ?? 0]
        )->fetchAll();
    }

    /** جميع الطلبات المعلقة (للمشرفين أو الإدارة) */
    public static function allPending(): array
    {
        return Database::query(
            "SELECT ar.*, aw.name AS workflow_name, u.full_name AS requester
             FROM legacy_approval_requests ar
             JOIN legacy_approval_workflows aw ON aw.id = ar.workflow_id
             LEFT JOIN users u ON u.id = ar.requested_by
             WHERE ar.status = 'pending'
             ORDER BY ar.created_at"
        )->fetchAll();
    }
}
