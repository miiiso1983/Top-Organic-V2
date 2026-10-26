<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Response, ApiAuth, ApprovalService, HrWorkflowService, StockService, Helpers, Auth, MenuService, ImageUpload, WorkflowEngine, WorkflowTemplateService, UnitConversionService, BarcodeService, ProductionService, MaterialRequestService, ProcurementReportService, NotificationService, HrService, ReservationService, AccountingService, MailService};
use App\Core\Workflow\EntityRegistry;

/**
 * REST API لتطبيق الموبايل (المدير والموظف)
 */
class ApiController extends Controller
{
    private function body(): array
    {
        $json = json_decode(file_get_contents('php://input'), true);
        return is_array($json) ? $json : $_POST;
    }

    public function login(): void
    {
        $b = $this->body();
        $user = Database::query(
            "SELECT u.*, r.name AS role_name, e.id AS employee_id, e.full_name AS employee_name,
                    j.title AS job_title, d.name AS department
             FROM users u
             JOIN roles r ON r.id = u.role_id
             LEFT JOIN employees e ON e.user_id = u.id
             LEFT JOIN jobs j ON j.id = e.job_id
             LEFT JOIN departments d ON d.id = e.department_id
             WHERE u.username = ? AND u.is_active = 1",
            [(string)($b['username'] ?? '')]
        )->fetch();

        if (!$user || !password_verify((string)($b['password'] ?? ''), $user['password_hash'])) {
            Response::json(['ok' => false, 'error' => 'بيانات الدخول غير صحيحة'], 401);
            return;
        }
        $perms = Auth::effectivePermissions((int)$user['role_id'], (string)$user['role_name']);
        Response::json([
            'ok' => true,
            'token' => ApiAuth::issueToken((int)$user['id']),
            'user' => [
                'id' => $user['id'],
                'name' => $user['full_name'],
                'role' => $user['role_name'],
                'employee_id' => !empty($user['employee_id']) ? (int)$user['employee_id'] : null,
                'employee_name' => $user['employee_name'] ?? null,
                'job_title' => $user['job_title'] ?? null,
                'department' => $user['department'] ?? null,
            ],
            'permissions' => $perms,
        ]);
    }

    /** يُرجع صلاحيات المستخدم الحالي (لتحديثها عند فتح التطبيق دون الحاجة لإعادة تسجيل الدخول) */
    public function permissions(): void
    {
        $user = ApiAuth::user();
        Response::json([
            'ok' => true,
            'data' => Auth::effectivePermissions((int)$user['role_id'], (string)$user['role_name']),
        ]);
    }

    /** تغيير كلمة مرور المستخدم الحالي من تطبيق الموبايل */
    public function changePassword(): void
    {
        $b = $this->body();
        $user = ApiAuth::user();
        if (!$user) {
            Response::json(['ok' => false, 'error' => 'غير مصرح'], 401);
            return;
        }
        $current = (string)($b['current_password'] ?? '');
        $new = (string)($b['new_password'] ?? '');
        $confirm = (string)($b['new_password_confirmation'] ?? '');
        if (!$current || !$new) {
            Response::json(['ok' => false, 'error' => 'جميع الحقول مطلوبة'], 422);
            return;
        }
        if (strlen($new) < 6) {
            Response::json(['ok' => false, 'error' => 'كلمة المرور الجديدة يجب ألا تقل عن 6 أحرف'], 422);
            return;
        }
        if ($confirm !== '' && $confirm !== $new) {
            Response::json(['ok' => false, 'error' => 'تأكيد كلمة المرور غير مطابق'], 422);
            return;
        }
        if (!password_verify($current, (string)$user['password_hash'])) {
            Response::json(['ok' => false, 'error' => 'كلمة المرور الحالية غير صحيحة'], 422);
            return;
        }
        Database::query(
            "UPDATE users SET password_hash = ? WHERE id = ?",
            [password_hash($new, PASSWORD_DEFAULT), (int)$user['id']]
        );
        Response::json(['ok' => true, 'message' => 'تم تغيير كلمة المرور بنجاح']);
    }

    public function dashboard(): void
    {
        $data = [];

        if (Auth::canAny(['sales.view', 'sales.create', 'pos.use'])) {
            $today = Database::query(
                "SELECT COUNT(*) AS orders_count, COALESCE(SUM(total),0) AS sales
                 FROM orders WHERE status <> 'cancelled' AND DATE(created_at) = CURDATE()"
            )->fetch();
            $data['today_orders'] = (int)$today['orders_count'];
            $data['today_sales'] = (float)$today['sales'];
        }
        if (Auth::canAny(['procurement.dashboard', 'material_request.view', 'material_request.mobile', 'mobile.requests', 'purchasing.view'])) {
            $data['procurement'] = [
                'pending_mr' => (int)Database::query("SELECT COUNT(*) AS c FROM material_requests WHERE status IN ('submitted','pending') AND deleted_at IS NULL")->fetch()['c'],
                'pending_pr' => (int)Database::query("SELECT COUNT(*) AS c FROM purchase_requests WHERE status = 'pending'")->fetch()['c'],
                'open_po' => (int)Database::query("SELECT COUNT(*) AS c FROM purchase_orders WHERE status IN ('sent','partially_received')")->fetch()['c'],
                'unpaid_invoices' => (float)Database::query("SELECT COALESCE(SUM(remaining_amount),0) AS t FROM purchase_invoices WHERE payment_status IN ('unpaid','partial')")->fetch()['t'],
            ];
        }
        if (Auth::can('finance.view')) {
            $cash = Database::query("SELECT COALESCE(SUM(balance),0) AS t FROM cash_registers WHERE is_active=1")->fetch();
            $data['cash_balance'] = (float)$cash['t'];
        }
        if (Auth::canAny(['inventory.view', 'inventory.mobile', 'inventory.manage'])) {
            $data['low_stock_items'] = count(StockService::lowStock());
        }
        if (Auth::canAny(['approvals.decide', 'mobile.approvals', 'workflow.decide'])) {
            $data['pending_approvals'] = (int)Database::query(
                "SELECT COUNT(*) AS c FROM legacy_approval_requests WHERE status='pending'"
            )->fetch()['c'];
        }

        Response::json(['ok' => true, 'data' => $data]);
    }

    public function orders(): void
    {
        $orders = Database::query(
            "SELECT id, order_no, order_type, status, total, created_at
             FROM orders ORDER BY id DESC LIMIT 50"
        )->fetchAll();
        Response::json(['ok' => true, 'data' => $orders]);
    }

    public function lowStock(): void
    {
        Response::json(['ok' => true, 'data' => StockService::lowStock()]);
    }

    public function notifications(): void
    {
        $rows = Database::query(
            "SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 50",
            [ApiAuth::id()]
        )->fetchAll();
        Response::json(['ok' => true, 'data' => $rows, 'unread' => Helpers::unreadCount(ApiAuth::id())]);
    }

    /** تعليم إشعار كمقروء (موبايل) */
    public function notificationRead(string $id): void
    {
        Helpers::markNotificationRead((int)$id, ApiAuth::id());
        Response::json(['ok' => true]);
    }

    /** تعليم كل الإشعارات كمقروءة (موبايل) */
    public function notificationsReadAll(): void
    {
        Database::query(
            "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0",
            [ApiAuth::id()]
        );
        Response::json(['ok' => true]);
    }

    /** البريد الداخلي (Mail/Inbox): صندوق الوارد — رسائل حقيقية بين مستخدمي النظام (ليست إشعارات بريد خارجي) */
    public function mailInbox(): void
    {
        $uid = ApiAuth::id();
        $attachmentsCountSql = MailService::migrationReady()
            ? "(SELECT COUNT(*) FROM internal_mail_attachments WHERE mail_id = m.id)"
            : "0";
        $rows = Database::query(
            "SELECT m.id, m.subject, m.body, m.created_at, m.sender_id, u.full_name AS sender_name, r.read_at,
                    $attachmentsCountSql AS attachments_count
             FROM internal_mail_recipients r
             JOIN internal_mails m ON m.id = r.mail_id
             JOIN users u ON u.id = m.sender_id
             WHERE r.recipient_id = ? AND r.deleted_by_recipient = 0
             ORDER BY (r.read_at IS NULL) DESC, m.id DESC
             LIMIT 200",
            [$uid]
        )->fetchAll();
        $unread = 0;
        foreach ($rows as $r) {
            if (empty($r['read_at'])) $unread++;
        }
        Response::json(['ok' => true, 'data' => $rows, 'unread' => $unread]);
    }

    /** البريد الداخلي: صندوق الصادر — الرسائل التي أرسلها المستخدم الحالي */
    public function mailSent(): void
    {
        $uid = ApiAuth::id();
        $attachmentsCountSql = MailService::migrationReady()
            ? "(SELECT COUNT(*) FROM internal_mail_attachments WHERE mail_id = m.id)"
            : "0";
        $rows = Database::query(
            "SELECT m.id, m.subject, m.body, m.created_at,
                    $attachmentsCountSql AS attachments_count,
                    (SELECT GROUP_CONCAT(u2.full_name SEPARATOR '، ')
                       FROM internal_mail_recipients r2 JOIN users u2 ON u2.id = r2.recipient_id
                      WHERE r2.mail_id = m.id) AS recipients_names
             FROM internal_mails m
             WHERE m.sender_id = ? AND m.deleted_by_sender = 0
             ORDER BY m.id DESC
             LIMIT 200",
            [$uid]
        )->fetchAll();
        Response::json(['ok' => true, 'data' => $rows]);
    }

    /** قائمة مستخدمي النظام لاختيار مستلمي رسالة بريد داخلية جديدة */
    public function mailUsers(): void
    {
        $uid = ApiAuth::id();
        $rows = Database::query(
            "SELECT id, full_name FROM users WHERE id != ? AND is_active = 1 ORDER BY full_name",
            [$uid]
        )->fetchAll();
        Response::json(['ok' => true, 'data' => $rows]);
    }

    /** قائمة الأقسام النشطة لاختيار قسم كامل عند إرسال رسالة بريد داخلية */
    public function mailDepartments(): void
    {
        $rows = Database::query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name")->fetchAll();
        Response::json(['ok' => true, 'data' => $rows]);
    }

    /** قائمة مجموعات البريد (قوائم توزيع مخصصة) المتاحة للاختيار عند الإرسال */
    public function mailGroups(): void
    {
        $uid = ApiAuth::id();
        if (!MailService::migrationReady()) {
            Response::json(['ok' => true, 'data' => []]);
            return;
        }
        $rows = Database::query(
            "SELECT g.id, g.name, g.created_by,
                    (SELECT COUNT(*) FROM mail_group_members WHERE group_id = g.id) AS members_count
             FROM mail_groups g WHERE g.is_active = 1 ORDER BY g.name"
        )->fetchAll();
        foreach ($rows as &$r) {
            $r['is_mine'] = (int)$r['created_by'] === $uid;
        }
        unset($r);
        Response::json(['ok' => true, 'data' => $rows]);
    }

    /** تفاصيل مجموعة بريد (مع أعضائها) — لشاشة التعديل في الموبايل */
    public function mailGroupShow(string $id): void
    {
        if (!MailService::migrationReady()) {
            Response::json(['ok' => false, 'error' => 'يلزم تحديث قاعدة البيانات أولاً'], 503);
            return;
        }
        $group = Database::query("SELECT * FROM mail_groups WHERE id = ? AND is_active = 1", [(int)$id])->fetch();
        if (!$group) {
            Response::json(['ok' => false, 'error' => 'المجموعة غير موجودة'], 404);
            return;
        }
        $memberIds = array_map('intval', array_column(
            Database::query("SELECT user_id FROM mail_group_members WHERE group_id = ?", [(int)$id])->fetchAll(),
            'user_id'
        ));
        $group['member_ids'] = $memberIds;
        $group['is_mine'] = (int)$group['created_by'] === ApiAuth::id();
        Response::json(['ok' => true, 'data' => $group]);
    }

    /** إنشاء مجموعة بريد جديدة من الموبايل */
    public function mailGroupStore(): void
    {
        if (!MailService::migrationReady()) {
            Response::json(['ok' => false, 'error' => 'يلزم تحديث قاعدة البيانات أولاً'], 503);
            return;
        }
        $uid = ApiAuth::id();
        $b = $this->body();
        $name = trim((string)($b['name'] ?? ''));
        $memberIds = array_values(array_unique(array_filter(array_map('intval', (array)($b['member_ids'] ?? [])))));
        if ($name === '') {
            Response::json(['ok' => false, 'error' => 'اسم المجموعة مطلوب'], 422);
            return;
        }
        Database::query("INSERT INTO mail_groups (name, created_by) VALUES (?,?)", [$name, $uid]);
        $groupId = (int)Database::lastInsertId();
        foreach ($memberIds as $mid) {
            if ($mid === $uid) continue;
            Database::query("INSERT IGNORE INTO mail_group_members (group_id, user_id) VALUES (?,?)", [$groupId, $mid]);
        }
        Response::json(['ok' => true, 'id' => $groupId]);
    }

    /** تحديث مجموعة بريد — مقصور على منشئها */
    public function mailGroupUpdate(string $id): void
    {
        if (!MailService::migrationReady()) {
            Response::json(['ok' => false, 'error' => 'يلزم تحديث قاعدة البيانات أولاً'], 503);
            return;
        }
        $uid = ApiAuth::id();
        $group = Database::query("SELECT * FROM mail_groups WHERE id = ? AND is_active = 1", [(int)$id])->fetch();
        if (!$group || (int)$group['created_by'] !== $uid) {
            Response::json(['ok' => false, 'error' => 'غير مصرح بتعديل هذه المجموعة'], 403);
            return;
        }
        $b = $this->body();
        $name = trim((string)($b['name'] ?? ''));
        $memberIds = array_values(array_unique(array_filter(array_map('intval', (array)($b['member_ids'] ?? [])))));
        if ($name === '') {
            Response::json(['ok' => false, 'error' => 'اسم المجموعة مطلوب'], 422);
            return;
        }
        Database::query("UPDATE mail_groups SET name = ? WHERE id = ?", [$name, (int)$id]);
        Database::query("DELETE FROM mail_group_members WHERE group_id = ?", [(int)$id]);
        foreach ($memberIds as $mid) {
            if ($mid === $uid) continue;
            Database::query("INSERT IGNORE INTO mail_group_members (group_id, user_id) VALUES (?,?)", [(int)$id, $mid]);
        }
        Response::json(['ok' => true]);
    }

    /** حذف مجموعة بريد — مقصور على منشئها */
    public function mailGroupDelete(string $id): void
    {
        if (!MailService::migrationReady()) {
            Response::json(['ok' => false, 'error' => 'يلزم تحديث قاعدة البيانات أولاً'], 503);
            return;
        }
        $uid = ApiAuth::id();
        $group = Database::query("SELECT * FROM mail_groups WHERE id = ?", [(int)$id])->fetch();
        if (!$group || (int)$group['created_by'] !== $uid) {
            Response::json(['ok' => false, 'error' => 'غير مصرح بحذف هذه المجموعة'], 403);
            return;
        }
        Database::query("UPDATE mail_groups SET is_active = 0 WHERE id = ?", [(int)$id]);
        Response::json(['ok' => true]);
    }

    /**
     * إرسال رسالة بريد داخلية جديدة (أو رد) لمستلم واحد أو أكثر */
    public function mailCompose(): void
    {
        $uid = ApiAuth::id();
        $b = $this->body();
        $subject = trim((string)($b['subject'] ?? ''));
        $bodyText = trim((string)($b['body'] ?? ''));
        $resolved = MailService::resolveRecipients(
            (array)($b['recipient_ids'] ?? []),
            (array)($b['department_ids'] ?? []),
            (array)($b['group_ids'] ?? []),
            $uid
        );
        if ($subject === '' || $bodyText === '') {
            Response::json(['ok' => false, 'error' => 'الموضوع ونص الرسالة مطلوبان'], 422);
            return;
        }
        if (!$resolved['recipients']) {
            Response::json(['ok' => false, 'error' => 'يجب اختيار مستلم واحد أو قسم أو مجموعة على الأقل'], 422);
            return;
        }
        // معاملة واحدة لضمان عدم إرسال الرسالة فعلياً للمستلمين إذا فشل رفع أحد المرفقات
        Database::beginTransaction();
        try {
            $mailId = MailService::createMail($uid, $subject, $bodyText, $resolved['recipients'], $resolved['targets']);
            MailService::saveAttachmentsFromRequest($mailId);
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
            return;
        }
        Response::json(['ok' => true, 'id' => $mailId]);
    }

    /** تفاصيل رسالة واردة؛ تُعلَّم كمقروءة تلقائياً عند فتحها */
    public function mailShow(string $id): void
    {
        $uid = ApiAuth::id();
        $row = Database::query(
            "SELECT m.id, m.subject, m.body, m.created_at, m.sender_id, u.full_name AS sender_name,
                    r.id AS recipient_row_id, r.read_at
             FROM internal_mail_recipients r
             JOIN internal_mails m ON m.id = r.mail_id
             JOIN users u ON u.id = m.sender_id
             WHERE m.id = ? AND r.recipient_id = ? AND r.deleted_by_recipient = 0",
            [(int)$id, $uid]
        )->fetch();
        if (!$row) {
            Response::json(['ok' => false, 'error' => 'الرسالة غير موجودة'], 404);
            return;
        }
        if (empty($row['read_at'])) {
            Database::query("UPDATE internal_mail_recipients SET read_at = NOW() WHERE id = ?", [(int)$row['recipient_row_id']]);
            $row['read_at'] = date('Y-m-d H:i:s');
        }
        $row['attachments'] = MailService::attachments((int)$row['id']);
        Response::json(['ok' => true, 'data' => $row]);
    }

    /** تفاصيل رسالة مُرسلة مع حالة القراءة عند كل مستلم (موبايل) */
    public function mailShowSent(string $id): void
    {
        $uid = ApiAuth::id();
        $row = Database::query(
            "SELECT * FROM internal_mails WHERE id = ? AND sender_id = ? AND deleted_by_sender = 0",
            [(int)$id, $uid]
        )->fetch();
        if (!$row) {
            Response::json(['ok' => false, 'error' => 'الرسالة غير موجودة'], 404);
            return;
        }
        $row['recipients'] = Database::query(
            "SELECT u.id, u.full_name, r.read_at
             FROM internal_mail_recipients r JOIN users u ON u.id = r.recipient_id
             WHERE r.mail_id = ? ORDER BY u.full_name",
            [(int)$id]
        )->fetchAll();
        $row['attachments'] = MailService::attachments((int)$id);
        $row['targets'] = MailService::targets((int)$id);
        Response::json(['ok' => true, 'data' => $row]);
    }

    /** تعليم رسالة واردة كمقروءة */
    public function mailRead(string $id): void
    {
        $uid = ApiAuth::id();
        Database::query(
            "UPDATE internal_mail_recipients SET read_at = NOW()
             WHERE mail_id = ? AND recipient_id = ? AND read_at IS NULL",
            [(int)$id, $uid]
        );
        Response::json(['ok' => true]);
    }

    /** تعليم كل الرسائل الواردة كمقروءة */
    public function mailReadAll(): void
    {
        $uid = ApiAuth::id();
        Database::query(
            "UPDATE internal_mail_recipients SET read_at = NOW() WHERE recipient_id = ? AND read_at IS NULL",
            [$uid]
        );
        Response::json(['ok' => true]);
    }

    /** حذف رسالة من صندوق الوارد (حذف خاص بالمستلم فقط، لا يحذف نسخة المُرسل) */
    public function mailDelete(string $id): void
    {
        $uid = ApiAuth::id();
        Database::query(
            "UPDATE internal_mail_recipients SET deleted_by_recipient = 1 WHERE mail_id = ? AND recipient_id = ?",
            [(int)$id, $uid]
        );
        Response::json(['ok' => true]);
    }

    /** حذف رسالة من صندوق الصادر (حذف خاص بالمُرسل فقط) */
    public function mailDeleteSent(string $id): void
    {
        $uid = ApiAuth::id();
        Database::query(
            "UPDATE internal_mails SET deleted_by_sender = 1 WHERE id = ? AND sender_id = ?",
            [(int)$id, $uid]
        );
        Response::json(['ok' => true]);
    }


    public function approvals(): void
    {
        $user = ApiAuth::user();
        $rows = Database::query(
            "SELECT ar.*, aw.name AS workflow_name
             FROM legacy_approval_requests ar
             JOIN legacy_approval_workflows aw ON aw.id = ar.workflow_id
             JOIN legacy_approval_steps s ON s.workflow_id = ar.workflow_id AND s.step_order = ar.current_step
             WHERE ar.status='pending' AND s.role_id = ? ORDER BY ar.created_at",
            [(int)$user['role_id']]
        )->fetchAll();
        Response::json(['ok' => true, 'data' => $rows]);
    }

    public function decideApproval(string $id): void
    {
        $b = $this->body();
        $_SESSION['user_id'] = ApiAuth::id();
        Auth::refresh();
        $decision = ($b['decision'] ?? '') === 'approved' ? 'approved' : 'rejected';
        $notes = (string)($b['notes'] ?? '');
        if (Database::query("SELECT id FROM requests WHERE id = ?", [(int)$id])->fetch()) {
            WorkflowEngine::decide((int)$id, $decision, $notes, (int)ApiAuth::id());
            Response::json(['ok' => true, 'result' => $decision]);
            return;
        }
        $result = ApprovalService::decide((int)$id, $decision, $notes);
        Response::json(['ok' => true, 'result' => $result]);
    }

    /** بصمة حضور/انصراف من الهاتف مع الموقع الجغرافي والصورة */
    public function attendanceCheck(): void
    {
        $b = $this->body();
        $emp = Database::query("SELECT * FROM employees WHERE user_id = ?", [ApiAuth::id()])->fetch();
        if (!$emp) {
            Response::json(['ok' => false, 'error' => 'لا يوجد ملف موظف مرتبط بهذا الحساب'], 404);
            return;
        }

        $lat = isset($b['latitude']) ? (float)$b['latitude'] : null;
        $lng = isset($b['longitude']) ? (float)$b['longitude'] : null;
        $type = ($b['check_type'] ?? 'in') === 'out' ? 'out' : 'in';

        // التحقق من آخر سجل حضور/انصراف للموظف لمنع التسجيل المتكرر أو المتعارض
        $lastRecord = Database::query(
            "SELECT check_type FROM attendance WHERE employee_id = ? ORDER BY checked_at DESC LIMIT 1",
            [(int)$emp['id']]
        )->fetch();
        if ($type === 'in' && $lastRecord && $lastRecord['check_type'] === 'in') {
            Response::json(['ok' => false, 'error' => 'يجب تسجيل الخروج أولاً قبل محاولة تسجيل دخول جديدة'], 409);
            return;
        }
        if ($type === 'out' && (!$lastRecord || $lastRecord['check_type'] === 'out')) {
            Response::json(['ok' => false, 'error' => 'لا يوجد تسجيل دخول نشط حالياً'], 409);
            return;
        }

        $photoPath = null;
        if (!empty($b['photo']) && is_string($b['photo'])) {
            $photoPath = $this->saveBase64Photo($b['photo']);
        }

        try {
            $result = HrService::recordAttendance(
                (int)$emp['id'], $type, $lat, $lng, $photoPath, 'mobile'
            );
            Response::json(['ok' => true, 'within_geofence' => $result['within_geofence'],
                'message' => $type === 'in' ? 'تم تسجيل الحضور' : 'تم تسجيل الانصراف']);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 403);
        }
    }

    private function saveBase64Photo(string $data, string $type = 'attendance'): ?string
    {
        if (!str_starts_with($data, 'data:image')) {
            $data = 'data:image/jpeg;base64,' . $data;
        }
        if (!preg_match('/^data:image\/(\w+);base64,(.+)$/', $data, $m)) {
            return null;
        }
        $ext = $m[1] === 'png' ? 'png' : 'jpg';
        $bytes = base64_decode($m[2], true);
        if (!$bytes) return null;

        $subDir = $type === 'reference' ? 'reference' : 'attendance';
        $uploadDir = __DIR__ . '/../../public/uploads/' . $subDir . '/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
        $prefix = $type === 'reference' ? 'ref_' : 'att_mobile_';
        $filename = $prefix . date('Ymd_His') . '_' . uniqid() . '.' . $ext;
        file_put_contents($uploadDir . $filename, $bytes);
        return '/uploads/' . $subDir . '/' . $filename;
    }

    /** حفظ ملف مرفقات عام مشفر بـ base64 للموافقات */
    private function saveBase64File(string $data, string $prefix): ?string
    {
        if (!preg_match('/^data:([^;]+);base64,(.*)$/s', $data, $m)) {
            return null;
        }
        $mime = $m[1];
        $base64 = $m[2];
        $bytes = base64_decode($base64, true);
        if (!$bytes) return null;
        $extMap = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/msword' => 'doc', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx'];
        $ext = $extMap[$mime] ?? 'file';
        $uploadDir = __DIR__ . '/../../public/uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
        $filename = $prefix . '_' . date('Ymd_His') . '_' . uniqid() . '.' . $ext;
        file_put_contents($uploadDir . $filename, $bytes);
        return $filename;
    }

    /** التحقق من الوجه أثناء البصمة باستخدام AWS Rekognition */
    public function verifyFace(): void
    {
        $b = $this->body();
        $emp = Database::query("SELECT * FROM employees WHERE user_id = ?", [ApiAuth::id()])->fetch();
        if (!$emp) {
            Response::json(['ok' => false, 'error' => 'لا يوجد ملف موظف مرتبط بهذا الحساب'], 404);
            return;
        }
        if (empty($b['selfie']) || !is_string($b['selfie'])) {
            Response::json(['ok' => false, 'error' => 'السيلفي مطلوب'], 400);
            return;
        }

        $referencePath = null;
        if (!empty($emp['reference_photo'])) {
            $referencePath = BASE_PATH . '/public' . $emp['reference_photo'];
        }
        if (!$referencePath || !is_file($referencePath)) {
            Response::json(['ok' => false, 'error' => 'لا توجد صورة مرجعية للموظف'], 400);
            return;
        }

        $threshold = (int)\App\Core\Settings::get('aws_similarity_threshold', 80);
        $result = \App\Core\FaceRecognitionService::compareBase64($referencePath, $b['selfie']);
        if ($result['error']) {
            Response::json(['ok' => false, 'error' => $result['error']], 500);
            return;
        }
        $similarity = $result['similarity'] ?? 0;
        $matched = $similarity >= $threshold;
        Response::json([
            'ok' => true,
            'matched' => $matched,
            'similarity' => round($similarity, 2),
            'threshold' => $threshold,
            'message' => $matched ? 'تم التحقق من الهوية' : 'لا يوجد تطابق كافٍ مع الصورة المرجعية',
        ]);
    }

    /** رفع/تحديث الصورة المرجعية للموظف من التطبيق */
    public function updateReferencePhoto(): void
    {
        $b = $this->body();
        $emp = Database::query("SELECT * FROM employees WHERE user_id = ?", [ApiAuth::id()])->fetch();
        if (!$emp) {
            Response::json(['ok' => false, 'error' => 'لا يوجد ملف موظف مرتبط بهذا الحساب'], 404);
            return;
        }
        if (empty($b['photo']) || !is_string($b['photo'])) {
            Response::json(['ok' => false, 'error' => 'الصورة مطلوبة'], 400);
            return;
        }
        $photoPath = $this->saveBase64Photo($b['photo'], 'reference');
        if (!$photoPath) {
            Response::json(['ok' => false, 'error' => 'فشل حفظ الصورة'], 500);
            return;
        }
        Database::query("UPDATE employees SET reference_photo = ? WHERE id = ?", [$photoPath, $emp['id']]);
        Response::json(['ok' => true, 'photo_path' => $photoPath, 'message' => 'تم تحديث الصورة المرجعية']);
    }

    /** حالة الحضور اليوم والإحداثيات المسموح بها */
    public function attendanceStatus(): void
    {
        $emp = Database::query("SELECT * FROM employees WHERE user_id = ?", [ApiAuth::id()])->fetch();
        if (!$emp) {
            Response::json(['ok' => false, 'error' => 'لا يوجد ملف موظف'], 404);
            return;
        }
        $today = Database::query(
            "SELECT MIN(CASE WHEN check_type='in' THEN checked_at END) AS clock_in,
                    MAX(CASE WHEN check_type='out' THEN checked_at END) AS clock_out
             FROM attendance WHERE employee_id = ? AND DATE(checked_at) = CURDATE()",
            [(int)$emp['id']]
        )->fetch();
        $branch = null;
        $hasBranchGeofence = false;
        if ($emp['branch_id']) {
            $branch = Database::query("SELECT id, name, latitude, longitude, allowed_radius_meters FROM branches WHERE id = ?", [(int)$emp['branch_id']])->fetch();
            $hasBranchGeofence = $branch && $branch['latitude'] && $branch['longitude'];
        }
        $geo = Database::query("SELECT svalue FROM settings WHERE skey='work_geofence'")->fetch();
        $fallback = $geo ? json_decode($geo['svalue'], true) : null;
        $hasFallbackGeofence = is_array($fallback) && isset($fallback['lat'], $fallback['lng']);
        $faceEnabled = (Database::query("SELECT svalue FROM settings WHERE skey='face_recognition_enabled'")->fetch()['svalue'] ?? '0') === '1';
        $strictEnabled = (Database::query("SELECT svalue FROM settings WHERE skey='attendance_strict_geofence'")->fetch()['svalue'] ?? '1') === '1';
        Response::json(['ok' => true, 'data' => [
            'clock_in' => $today['clock_in'],
            'clock_out' => $today['clock_out'],
            'branch' => $branch,
            'fallback_geofence' => $fallback,
            'geofence_configured' => $hasBranchGeofence || $hasFallbackGeofence,
            'geofence_exempt' => (bool)($emp['geofence_exempt'] ?? false),
            'strict' => $strictEnabled,
            'face_recognition_enabled' => $faceEnabled,
            'face_recognition_required' => $faceEnabled && \App\Core\FaceRecognitionService::isConfigured(),
            'has_reference_photo' => !empty($emp['reference_photo']),
        ]]);
    }

    public function biometricAttendanceImport(): void
    {
        $b = $this->body();
        $rows = $b['rows'] ?? [];
        $deviceId = (string)($b['device_id'] ?? '');
        if (!is_array($rows) || !$rows) {
            Response::json(['ok' => false, 'error' => 'لا توجد سجلات للاستيراد'], 422);
            return;
        }
        $count = 0;
        $errors = [];
        foreach ($rows as $row) {
            $code = trim((string)($row['emp_code'] ?? $row['pin'] ?? ''));
            if (!$code) {
                $errors[] = 'missing emp_code';
                continue;
            }
            $emp = Database::query("SELECT * FROM employees WHERE emp_code = ?", [$code])->fetch();
            if (!$emp) {
                $errors[] = "لا يوجد موظف: $code";
                continue;
            }
            $type = 'in';
            $t = strtolower((string)($row['check_type'] ?? $row['status'] ?? 'in'));
            if (in_array($t, ['out', 'checkout', 'check-out', 'leave', '1'], true)) {
                $type = 'out';
            }
            $checkedAt = (string)($row['checked_at'] ?? $row['datetime'] ?? date('Y-m-d H:i:s'));
            if (($emp['status'] ?? '') === 'suspended') {
                HrService::rejectSuspendedAttendance((int)$emp['id'], $emp, 'device', $deviceId, $type);
                $errors[] = "الموظف {$code} موقوف عن العمل، تم رفض بصمة {$type}";
                continue;
            }
            Database::query(
                "INSERT INTO attendance (employee_id, check_type, checked_at, device_id, source, within_geofence)
                 VALUES (?,?,?,?, 'device', 1)",
                [(int)$emp['id'], $type, $checkedAt, $deviceId]
            );
            Helpers::queueSync('attendance', (int)Database::lastInsertId(), 'insert', [
                'employee_id' => (int)$emp['id'], 'check_type' => $type, 'checked_at' => $checkedAt,
            ]);
            $count++;
        }
        Response::json(['ok' => true, 'imported' => $count, 'errors' => $errors]);
    }


    public function requestLeave(): void
    {
        $b = $this->body();
        $user = ApiAuth::user();
        if (!$user) {
            Response::json(['ok' => false, 'error' => 'غير مصرح'], 401);
            return;
        }
        $emp = Database::query("SELECT * FROM employees WHERE user_id = ?", [ApiAuth::id()])->fetch();
        if (!$emp) {
            Response::json(['ok' => false, 'error' => 'لا يوجد ملف موظف'], 404);
            return;
        }

        $leaveType = (string)($b['leave_type'] ?? 'unpaid_short');
        $dateFrom = (string)($b['date_from'] ?? '');
        $dateTo = (string)($b['date_to'] ?? '');
        $reason = (string)($b['reason'] ?? '');

        $validTypes = ['unpaid_short', 'unpaid_long', 'hourly'];
        if (!in_array($leaveType, $validTypes, true)) {
            Response::json(['ok' => false, 'error' => 'نوع الإجازة غير صالح'], 422);
            return;
        }
        if (!$dateFrom || !$dateTo || $dateTo < $dateFrom) {
            Response::json(['ok' => false, 'error' => 'تاريخ البدء والانتهاء غير صالح'], 422);
            return;
        }
        $days = HrService::leaveDays($dateFrom, $dateTo);
        if ($leaveType === 'unpaid_short' && ($days < 1 || $days > 3)) {
            Response::json(['ok' => false, 'error' => 'الإجازة القصيرة بدون راتب يجب ألا تتجاوز مدتها 3 أيام'], 422);
            return;
        }
        if ($leaveType === 'unpaid_long' && $days < 4) {
            Response::json(['ok' => false, 'error' => 'الإجازة الطويلة بدون راتب يجب أن تكون مدتها 4 أيام فأكثر'], 422);
            return;
        }
        if (!HrService::isEmployeeFileOpen((int)$emp['id'])) {
            Response::json(['ok' => false, 'error' => 'ملف الموظف مغلق أو مأرشف'], 403);
            return;
        }
        if (HrService::hasLeaveOverlap((int)$emp['id'], $dateFrom, $dateTo)) {
            Response::json(['ok' => false, 'error' => 'يوجد طلب إجازة متداخل مع نفس الفترة'], 422);
            return;
        }

        Database::query(
            "INSERT INTO leave_requests (employee_id, requested_by, request_type, leave_type, date_from, date_to, reason, status)
             VALUES (?,?,?,?,?,?,?,?)",
            [(int)$emp['id'], ApiAuth::id(), 'leave', $leaveType, $dateFrom, $dateTo, $reason, 'pending']
        );
        $requestId = (int)Database::lastInsertId();

        HrWorkflowService::request('leave_request', $requestId, (int)$user['id'], [
            'employee_id' => (int)$emp['id'],
            'employee_name' => $emp['full_name'] ?? '',
            'request_type' => 'leave',
            'leave_type' => $leaveType,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'reason' => $reason,
            'title' => 'طلب إجازة من الموبايل - ' . Helpers::e($emp['full_name'] ?? ''),
        ]);

        Response::json(['ok' => true, 'message' => 'تم تقديم طلب الإجازة', 'id' => $requestId]);
    }

    public function employeeLeaves(): void
    {
        $emp = Database::query("SELECT * FROM employees WHERE user_id = ?", [ApiAuth::id()])->fetch();
        if (!$emp) {
            Response::json(['ok' => false, 'error' => 'لا يوجد ملف موظف'], 404);
            return;
        }
        $rows = Database::query(
            "SELECT l.*, lar.current_step, lar.status AS approval_status
             FROM leave_requests l
             LEFT JOIN legacy_approval_requests lar ON lar.entity_type = 'leave_request' AND lar.entity_id = l.id
             WHERE l.employee_id = ?
             ORDER BY l.id DESC",
            [(int)$emp['id']]
        )->fetchAll();
        Response::json(['ok' => true, 'data' => $rows]);
    }

    public function pendingLeaveRequests(): void
    {
        $user = ApiAuth::user();
        $_SESSION['user_id'] = $user['id'];
        Auth::refresh();
        if (!Auth::can('approvals.decide') && !Auth::can('hr.leave.approve')) {
            Response::json(['ok' => false, 'error' => 'ليس لديك صلاحية'], 403);
            return;
        }
        $rows = Database::query(
            "SELECT l.*, e.full_name, e.emp_code, lar.id AS approval_request_id, lar.current_step
             FROM leave_requests l
             JOIN employees e ON e.id = l.employee_id
             LEFT JOIN legacy_approval_requests lar ON lar.entity_type = 'leave_request' AND lar.entity_id = l.id
             WHERE l.request_type = 'leave' AND l.status = 'pending'
             ORDER BY l.id DESC"
        )->fetchAll();
        Response::json(['ok' => true, 'data' => $rows]);
    }

    public function decideLeaveApi(string $id): void
    {
        $b = $this->body();
        $requestId = (int)$id;
        $decision = ($b['decision'] ?? '') === 'approved' ? 'approved' : 'rejected';

        $user = ApiAuth::user();
        $_SESSION['user_id'] = $user['id'];
        Auth::refresh();
        if (!Auth::can('approvals.decide') && !Auth::can('hr.leave.approve')) {
            Response::json(['ok' => false, 'error' => 'ليس لديك صلاحية'], 403);
            return;
        }

        $notes = (string)($b['notes'] ?? '');
        $finalStatus = null;

        $wfRequestId = HrWorkflowService::workflowRequestId('leave_request', $requestId);
        if ($wfRequestId) {
            try {
                WorkflowEngine::decide($wfRequestId, $decision, $notes, (int)$user['id']);
                $finalStatus = $decision;
            } catch (\Throwable $e) {
                Response::json(['ok' => false, 'error' => $e->getMessage()], 422); return;
            }
        } else {
            $request = Database::query("SELECT * FROM leave_requests WHERE id = ?", [$requestId])->fetch();
            if ($request && $request['status'] === 'pending') {
                Database::query(
                    "UPDATE leave_requests SET status = ?, approved_by = ? WHERE id = ?",
                    [$decision, Auth::id(), $requestId]
                );
                if ($decision === 'approved') {
                    HrService::applyLeaveApproval($requestId);
                } else {
                    HrService::revertLeaveApproval($requestId);
                }
                $finalStatus = $decision;
            }
        }

        if ($finalStatus === 'approved' || $finalStatus === 'rejected') {
            $request = Database::query(
                "SELECT l.*, e.user_id FROM leave_requests l JOIN employees e ON e.id = l.employee_id WHERE l.id = ?",
                [$requestId]
            )->fetch();
            if ($request && $request['user_id']) {
                Helpers::notify(
                    (int)$request['user_id'],
                    'نتيجة طلب الإجازة',
                    'تم ' . ($finalStatus === 'approved' ? 'الموافقة' : 'الرفض') . ' على طلب إجازتك',
                    '/hr/leaves/' . $requestId
                );
            }
        }

        Response::json(['ok' => true, 'status' => $finalStatus]);
    }

    public function requestAdvance(): void
    {
        $b = $this->body();
        $user = ApiAuth::user();
        if (!$user) {
            Response::json(['ok' => false, 'error' => 'غير مصرح'], 401);
            return;
        }
        $emp = Database::query("SELECT * FROM employees WHERE user_id = ?", [ApiAuth::id()])->fetch();
        if (!$emp) { Response::json(['ok' => false, 'error' => 'لا يوجد ملف موظف'], 404); return; }

        $amount = (float)($b['amount'] ?? 0);
        if ($amount <= 0) {
            Response::json(['ok' => false, 'error' => 'المبلغ يجب أن يكون أكبر من صفر'], 422);
            return;
        }
        $advanceType = (string)($b['advance_type'] ?? 'short');
        $installments = max(1, (int)($b['installments'] ?? 1));
        $isLongTerm = $advanceType === 'long';

        // طريقة احتساب القسط الشهري للسلف طويلة الأجل: نسبة من الراتب أو مبلغ ثابت مستقطع
        $installmentMethod = (string)($b['installment_method'] ?? 'fixed');
        if (!in_array($installmentMethod, ['fixed', 'percentage'], true)) {
            $installmentMethod = 'fixed';
        }
        $installmentPercentage = null;
        $installmentAmount = null;
        if ($isLongTerm) {
            if ($installmentMethod === 'percentage') {
                $installmentPercentage = (float)($b['installment_percentage'] ?? 0);
                if ($installmentPercentage <= 0 || $installmentPercentage > 100) {
                    Response::json(['ok' => false, 'error' => 'يجب إدخال نسبة قسط شهري صحيحة (بين 1 و100)'], 422);
                    return;
                }
                $installmentAmount = round((float)($emp['base_salary'] ?? 0) * $installmentPercentage / 100, 2);
            } elseif ((float)($b['installment_amount'] ?? 0) > 0) {
                $installmentAmount = round((float)$b['installment_amount'], 2);
            }
            if ($installmentAmount !== null && $installmentAmount > 0) {
                $installments = max(2, (int)ceil($amount / $installmentAmount));
            }
            if ($installments < 2) {
                Response::json(['ok' => false, 'error' => 'السلف طويلة الأجل تتطلب عدد أقساط لا يقل عن 2'], 422);
                return;
            }
        }
        if (!HrService::isEmployeeFileOpen((int)$emp['id'])) {
            Response::json(['ok' => false, 'error' => 'ملف الموظف مغلق أو مأرشف'], 403);
            return;
        }

        Database::beginTransaction();
        try {
        Database::query('SELECT id FROM employees WHERE id=? FOR UPDATE', [(int)$emp['id']])->fetch();
        Database::query(
            "INSERT INTO salary_advances (employee_id, amount, advance_type, installments, reason, request_source, is_long_term, remaining_amount, mobile_device_id, installment_method, installment_percentage, installment_amount)
             VALUES (?,?,?,?,?,'mobile',?,?,?,?,?,?)",
            [(int)$emp['id'], $amount, $advanceType, $installments,
             (string)($b['reason'] ?? ''), $isLongTerm ? 1 : 0, $amount,
             (string)($b['device_id'] ?? ''), $installmentMethod, $installmentPercentage, $installmentAmount]
        );
        $advanceId = (int)Database::lastInsertId();

        HrWorkflowService::request('salary_advance', $advanceId, (int)$user['id'], [
            'employee_id' => (int)$emp['id'],
            'employee_name' => $emp['full_name'] ?? '',
            'amount' => $amount,
            'advance_type' => $advanceType,
            'installments' => $installments,
            'reason' => (string)($b['reason'] ?? ''),
            'title' => 'طلب سلفة من الموبايل - ' . Helpers::e($emp['full_name'] ?? '') . ' - ' . Helpers::money($amount),
        ]);
        Database::commit();
        Response::json(['ok' => true, 'message' => 'تم تقديم طلب السلفة', 'id' => $advanceId]);
        } catch (\Throwable $e) {
            Database::rollBack(); Response::json(['ok'=>false,'error'=>$e->getMessage()], 422);
        }
    }

    public function employeeAdvances(): void
    {
        $emp = Database::query("SELECT * FROM employees WHERE user_id = ?", [ApiAuth::id()])->fetch();
        if (!$emp) { Response::json(['ok' => false, 'error' => 'لا يوجد ملف موظف'], 404); return; }
        $rows = Database::query(
            "SELECT a.*, lar.current_step, lar.status AS approval_status
             FROM salary_advances a
             LEFT JOIN legacy_approval_requests lar ON lar.entity_type = 'salary_advance' AND lar.entity_id = a.id
             WHERE a.employee_id = ?
             ORDER BY a.id DESC",
            [(int)$emp['id']]
        )->fetchAll();
        Response::json(['ok' => true, 'data' => $rows]);
    }

    public function pendingAdvanceRequests(): void
    {
        $user = ApiAuth::user();
        $_SESSION['user_id'] = $user['id'];
        Auth::refresh();
        if (!Auth::can('approvals.decide') && !Auth::can('hr.advance.approve')) {
            Response::json(['ok' => false, 'error' => 'ليس لديك صلاحية'], 403);
            return;
        }
        $rows = Database::query(
            "SELECT a.*, e.full_name, e.emp_code, lar.id AS approval_request_id, lar.current_step
             FROM salary_advances a
             JOIN employees e ON e.id = a.employee_id
             LEFT JOIN legacy_approval_requests lar ON lar.entity_type = 'salary_advance' AND lar.entity_id = a.id
             WHERE a.status = 'pending'
             ORDER BY a.id DESC"
        )->fetchAll();
        Response::json(['ok' => true, 'data' => $rows]);
    }

    public function decideAdvanceApi(string $id): void
    {
        $b = $this->body();
        $advanceId = (int)$id;
        $decision = ($b['decision'] ?? '') === 'approved' ? 'approved' : 'rejected';

        $user = ApiAuth::user();
        $_SESSION['user_id'] = $user['id'];
        Auth::refresh();
        if (!Auth::can('approvals.decide') && !Auth::can('hr.advance.approve')) {
            Response::json(['ok' => false, 'error' => 'ليس لديك صلاحية'], 403);
            return;
        }

        $notes = (string)($b['notes'] ?? '');
        $finalStatus = null;

        $wfRequestId = HrWorkflowService::workflowRequestId('salary_advance', $advanceId);
        if ($wfRequestId) {
            try {
                WorkflowEngine::decide($wfRequestId, $decision, $notes, (int)$user['id']);
                $finalStatus = $decision;
            } catch (\Throwable $e) {
                Response::json(['ok' => false, 'error' => $e->getMessage()], 422); return;
            }
        } else {
            $advance = Database::query("SELECT * FROM salary_advances WHERE id = ?", [$advanceId])->fetch();
            if ($advance && $advance['status'] === 'pending') {
                Database::query(
                    "UPDATE salary_advances SET status = ?, approved_by = ? WHERE id = ?",
                    [$decision, Auth::id(), $advanceId]
                );
                if ($decision === 'approved') {
                    HrService::generateRepaymentSchedule($advanceId);
                }
                $finalStatus = $decision;
            }
        }

        if ($finalStatus === 'approved' || $finalStatus === 'rejected') {
            $advance = Database::query(
                "SELECT a.*, e.user_id FROM salary_advances a JOIN employees e ON e.id = a.employee_id WHERE a.id = ?",
                [$advanceId]
            )->fetch();
            if ($advance && $advance['user_id']) {
                Helpers::notify(
                    (int)$advance['user_id'],
                    'نتيجة طلب السلفة',
                    'تم ' . ($finalStatus === 'approved' ? 'الموافقة' : 'الرفض') . ' على طلب سلفتك',
                    '/accounting/advances/' . $advanceId
                );
            }
        }

        Response::json(['ok' => true, 'status' => $finalStatus]);
    }

    public function requestOvertime(): void
    {
        $b = $this->body();
        $user = ApiAuth::user();
        if (!$user) {
            Response::json(['ok' => false, 'error' => 'غير مصرح'], 401);
            return;
        }
        $emp = Database::query("SELECT * FROM employees WHERE user_id = ?", [ApiAuth::id()])->fetch();
        if (!$emp) {
            Response::json(['ok' => false, 'error' => 'لا يوجد ملف موظف'], 404);
            return;
        }
        $date = (string)($b['request_date'] ?? '');
        $hours = (float)($b['hours'] ?? 0);
        $reason = (string)($b['reason'] ?? '');
        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            Response::json(['ok' => false, 'error' => 'تاريخ العمل الإضافي غير صالح'], 422);
            return;
        }
        // لا قيد على تاريخ طلب العمل الإضافي: يُسمح بتقديم الطلب لأي تاريخ (ماضٍ أو حاضر أو مستقبل) بحرية.
        if ($hours <= 0) {
            Response::json(['ok' => false, 'error' => 'عدد الساعات يجب أن يكون أكبر من صفر'], 422);
            return;
        }
        if (!HrService::isEmployeeFileOpen((int)$emp['id'])) {
            Response::json(['ok' => false, 'error' => 'ملف الموظف مغلق أو مأرشف'], 403);
            return;
        }
        $existing = Database::query(
            "SELECT id FROM overtime_requests WHERE employee_id = ? AND request_date = ?",
            [(int)$emp['id'], $date]
        )->fetch();
        if ($existing) {
            Response::json(['ok' => false, 'error' => 'يوجد طلب عمل إضافي مسجل مسبقاً لهذا اليوم'], 422);
            return;
        }
        try {
            $overtimeId = HrService::createOvertimeRequest((int)$emp['id'], $date, $hours, $reason);
            $amount = HrService::calculateOvertimeAmount((int)$emp['id'], $hours, date('Y-m', strtotime($date)));
            $wfRequestId = HrWorkflowService::request('overtime_request', $overtimeId, (int)$user['id'], [
                'employee_id' => (int)$emp['id'],
                'employee_name' => $emp['full_name'] ?? '',
                'request_date' => $date,
                'hours' => $hours,
                'reason' => $reason,
                'amount' => $amount,
                'title' => 'طلب عمل إضافي - ' . Helpers::e($emp['full_name'] ?? '') . ' - ' . $date,
            ]);
            if ($wfRequestId) {
                Database::query("UPDATE overtime_requests SET workflow_request_id = ? WHERE id = ?", [$wfRequestId, $overtimeId]);
            }
            Response::json(['ok' => true, 'message' => 'تم تقديم طلب العمل الإضافي', 'id' => $overtimeId, 'workflow_request_id' => $wfRequestId]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function employeeOvertime(): void
    {
        $emp = Database::query("SELECT * FROM employees WHERE user_id = ?", [ApiAuth::id()])->fetch();
        if (!$emp) {
            Response::json(['ok' => false, 'error' => 'لا يوجد ملف موظف'], 404);
            return;
        }
        Response::json(['ok' => true, 'data' => HrService::getEmployeeOvertime((int)$emp['id'])]);
    }

    public function pendingOvertime(): void
    {
        $user = ApiAuth::user();
        $_SESSION['user_id'] = $user['id'];
        Auth::refresh();
        if (!Auth::can('approvals.decide') && !Auth::can('hr.overtime.approve')) {
            Response::json(['ok' => false, 'error' => 'ليس لديك صلاحية'], 403);
            return;
        }
        $rows = Database::query(
            "SELECT o.*, e.full_name, e.emp_code, r.id AS workflow_request_id, r.template_id, r.current_step
             FROM overtime_requests o
             JOIN employees e ON e.id = o.employee_id
             LEFT JOIN requests r ON r.id = o.workflow_request_id
             WHERE o.status IN ('pending','manager_approved')
             ORDER BY o.created_at DESC"
        )->fetchAll();
        foreach ($rows as &$row) {
            HrService::recomputeOvertimeBiometricMatch((int)$row['id']);
            $row['attendance'] = HrService::getOvertimeAttendance((int)$row['employee_id'], (string)$row['request_date']);
            $row['biometric_hours'] = HrService::getBiometricOvertimeHours((int)$row['employee_id'], (string)$row['request_date']);
            $row['biometric_match'] = (float)$row['biometric_hours'] >= (float)$row['hours'];
            $row['can_decide'] = false;
            if (!empty($row['workflow_request_id']) && !empty($row['template_id'])) {
                $step = WorkflowEngine::resolveStep((int)$row['template_id'], (int)$row['current_step'], (int)$row['workflow_request_id']);
                if ($step) {
                    $req = WorkflowEngine::getRequest((int)$row['workflow_request_id']);
                    $row['can_decide'] = WorkflowEngine::userCanDecideStep($step, $req ?: []);
                    $row['step_title'] = $step['title'] ?? '';
                }
            }
        }
        unset($row);
        Response::json(['ok' => true, 'data' => $rows]);
    }

    public function overtimeAttendance(string $id): void
    {
        $overtimeId = (int)$id;
        $row = Database::query("SELECT employee_id, request_date FROM overtime_requests WHERE id = ?", [$overtimeId])->fetch();
        if (!$row) {
            Response::json(['ok' => false, 'error' => 'الطلب غير موجود'], 404);
            return;
        }
        Response::json(['ok' => true, 'data' => [
            'attendance' => HrService::getOvertimeAttendance((int)$row['employee_id'], (string)$row['request_date']),
            'biometric_hours' => HrService::getBiometricOvertimeHours((int)$row['employee_id'], (string)$row['request_date']),
        ]]);
    }

    public function decideOvertimeApi(string $id): void
    {
        $b = $this->body();
        $overtimeId = (int)$id;
        $decision = ($b['decision'] ?? '') === 'approved' ? 'approved' : 'rejected';
        $user = ApiAuth::user();
        $_SESSION['user_id'] = $user['id'];
        Auth::refresh();
        if (!Auth::can('approvals.decide') && !Auth::can('hr.overtime.approve')) {
            Response::json(['ok' => false, 'error' => 'ليس لديك صلاحية'], 403);
            return;
        }
        $notes = (string)($b['notes'] ?? '');
        $row = Database::query("SELECT workflow_request_id FROM overtime_requests WHERE id = ?", [$overtimeId])->fetch();
        if (!$row || !$row['workflow_request_id']) {
            Response::json(['ok' => false, 'error' => 'لا يوجد طلب عمل إضافي مرتبط بسير عمل'], 404);
            return;
        }
        try {
            WorkflowEngine::decide((int)$row['workflow_request_id'], $decision, $notes, (int)$user['id']);
            Response::json(['ok' => true, 'status' => $decision]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** ========== طلب نسيان بصمة (Missing Attendance) ========== */

    public function requestMissingAttendance(): void
    {
        $b = $this->body();
        $user = ApiAuth::user();
        if (!$user) {
            Response::json(['ok' => false, 'error' => 'غير مصرح'], 401);
            return;
        }
        $emp = Database::query("SELECT * FROM employees WHERE user_id = ?", [ApiAuth::id()])->fetch();
        if (!$emp) {
            Response::json(['ok' => false, 'error' => 'لا يوجد ملف موظف'], 404);
            return;
        }
        if (!HrService::isEmployeeFileOpen((int)$emp['id'])) {
            Response::json(['ok' => false, 'error' => 'ملف الموظف مغلق أو مأرشف'], 403);
            return;
        }

        $payload = [
            'request_type' => (string)($b['request_type'] ?? 'in'),
            'clock_in_at' => ($b['clock_in_at'] ?? '') ?: null,
            'clock_out_at' => ($b['clock_out_at'] ?? '') ?: null,
            'reason' => (string)($b['reason'] ?? ''),
        ];

        try {
            $requestId = HrService::createMissingAttendanceRequest((int)$emp['id'], $payload);
            $wfRequestId = HrWorkflowService::request('missing_attendance_request', $requestId, (int)$user['id'], [
                'employee_id' => (int)$emp['id'],
                'employee_name' => $emp['full_name'] ?? '',
                'request_type' => $payload['request_type'],
                'clock_in_at' => $payload['clock_in_at'],
                'clock_out_at' => $payload['clock_out_at'],
                'reason' => $payload['reason'],
                'title' => 'طلب نسيان بصمة - ' . Helpers::e($emp['full_name'] ?? ''),
            ]);
            if ($wfRequestId) {
                Database::query(
                    "UPDATE missing_attendance_requests SET workflow_request_id = ? WHERE id = ?",
                    [$wfRequestId, $requestId]
                );
            }
            Response::json([
                'ok' => true,
                'message' => 'تم تقديم طلب نسيان البصمة',
                'id' => $requestId,
                'workflow_request_id' => $wfRequestId,
            ]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function employeeMissingAttendance(): void
    {
        $emp = Database::query("SELECT * FROM employees WHERE user_id = ?", [ApiAuth::id()])->fetch();
        if (!$emp) {
            Response::json(['ok' => false, 'error' => 'لا يوجد ملف موظف'], 404);
            return;
        }
        $rows = Database::query(
            "SELECT m.*, r.id AS workflow_request_id, r.current_step, r.status AS workflow_status
             FROM missing_attendance_requests m
             LEFT JOIN requests r ON r.id = m.workflow_request_id
             WHERE m.employee_id = ?
             ORDER BY m.created_at DESC",
            [(int)$emp['id']]
        )->fetchAll();
        Response::json(['ok' => true, 'data' => $rows]);
    }

    public function pendingMissingAttendance(): void
    {
        $user = ApiAuth::user();
        $_SESSION['user_id'] = $user['id'];
        Auth::refresh();
        if (!Auth::can('approvals.decide') && !Auth::can('hr.missing_attendance.approve')) {
            Response::json(['ok' => false, 'error' => 'ليس لديك صلاحية'], 403);
            return;
        }
        // تُقيّد رؤية طلبات نسيان البصمة حصراً على المدراء الذين لديهم فريق (مرؤوسون فعليون)،
        // وتُعرض لهم فقط طلبات أفراد فريقهم أو طلباتهم الخاصة؛ من لا يملك فريقاً لا يرى أي طلب حتى لو كانت لديه صلاحية الموافقة العامة.
        $subordinateIds = array_column(WorkflowEngine::subordinateEmployees((int)$user['id']), 'id');
        if (empty($subordinateIds)) {
            Response::json(['ok' => true, 'data' => []]);
            return;
        }
        $visibleIds = $subordinateIds;
        $ownEmp = Database::query("SELECT id FROM employees WHERE user_id = ?", [(int)$user['id']])->fetch();
        if ($ownEmp) {
            $visibleIds[] = (int)$ownEmp['id'];
        }
        $visibleIds = array_values(array_unique(array_map('intval', $visibleIds)));
        $placeholders = implode(',', array_fill(0, count($visibleIds), '?'));
        $rows = Database::query(
            "SELECT m.*, e.full_name, e.emp_code, r.id AS workflow_request_id, r.template_id, r.current_step
             FROM missing_attendance_requests m
             JOIN employees e ON e.id = m.employee_id
             LEFT JOIN requests r ON r.id = m.workflow_request_id
             WHERE m.status IN ('pending','manager_approved') AND m.employee_id IN ($placeholders)
             ORDER BY m.created_at DESC",
            $visibleIds
        )->fetchAll();
        foreach ($rows as &$row) {
            $row['can_decide'] = false;
            $row['step_title'] = '';
            if (!empty($row['workflow_request_id']) && !empty($row['template_id'])) {
                $step = WorkflowEngine::resolveStep((int)$row['template_id'], (int)$row['current_step'], (int)$row['workflow_request_id']);
                if ($step) {
                    $req = WorkflowEngine::getRequest((int)$row['workflow_request_id']);
                    $row['can_decide'] = WorkflowEngine::userCanDecideStep($step, $req ?: []);
                    $row['step_title'] = $step['title'] ?? '';
                }
            }
        }
        unset($row);
        Response::json(['ok' => true, 'data' => $rows]);
    }

    public function decideMissingAttendanceApi(string $id): void
    {
        $b = $this->body();
        $requestId = (int)$id;
        $decision = ($b['decision'] ?? '') === 'approved' ? 'approved' : 'rejected';
        $user = ApiAuth::user();
        $_SESSION['user_id'] = $user['id'];
        Auth::refresh();
        if (!Auth::can('approvals.decide') && !Auth::can('hr.missing_attendance.approve')) {
            Response::json(['ok' => false, 'error' => 'ليس لديك صلاحية'], 403);
            return;
        }
        $notes = (string)($b['notes'] ?? '');
        $row = Database::query("SELECT workflow_request_id FROM missing_attendance_requests WHERE id = ?", [$requestId])->fetch();
        if (!$row || !$row['workflow_request_id']) {
            Response::json(['ok' => false, 'error' => 'لا يوجد طلب مرتبط بسير عمل'], 404);
            return;
        }
        try {
            WorkflowEngine::decide((int)$row['workflow_request_id'], $decision, $notes, (int)$user['id']);
            Response::json(['ok' => true, 'status' => $decision]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }


    /** تاريخ الموظف: حضور، إجازات، سلف، تسويات، رواتب + فلتر الشهر */
    public function employeeHistory(): void
    {
        $b = $this->body();
        $emp = Database::query("SELECT * FROM employees WHERE user_id = ?", [ApiAuth::id()])->fetch();
        if (!$emp) { Response::json(['ok' => false, 'error' => 'لا يوجد ملف موظف'], 404); return; }
        $id = (int)$emp['id'];
        $month = (string)($_GET['month'] ?? $b['month'] ?? '');
        if ($month && !preg_match('/^\d{4}-\d{2}$/', $month)) {
            Response::json(['ok' => false, 'error' => 'شهر غير صالح'], 422); return;
        }

        $history = HrService::employeeHistory($id, $month ?: null);
        Response::json(['ok' => true, 'month' => $month ?: 'all', 'data' => [
            'employee' => ['code' => $emp['emp_code'], 'name' => $emp['full_name'],
                           'position' => $emp['position'], 'salary' => (float)$emp['base_salary']],
            'attendance' => $history['attendance'],
            'leaves' => $history['leaves'],
            'advances' => $history['advances'],
            'adjustments' => $history['adjustments'],
            'payrolls' => Database::query(
                "SELECT period, net_salary, status FROM payrolls
                 WHERE employee_id = ? ORDER BY id DESC LIMIT 12", [$id])->fetchAll(),
        ]]);
    }

    /** ========== كشف الراتب: الشهر الحالي والسجل التاريخي (بعد الاعتماد فقط) ========== */

    /** قائمة الرواتب للموظف الحالي (الشهر الحالي + السجل التاريخي)؛ تظهر فور توليدها بغض النظر
     * عن حالة اعتمادها ('draft'/'approved'/'paid') ليرى الموظف كشف راتبه فور توليده من قبل الموارد البشرية */
    public function myPayrollHistory(): void
    {
        $emp = Database::query("SELECT id FROM employees WHERE user_id = ?", [ApiAuth::id()])->fetch();
        if (!$emp) { Response::json(['ok' => false, 'error' => 'لا يوجد ملف موظف'], 404); return; }
        $rows = Database::query(
            "SELECT id, period, base_salary, total_earnings, total_deductions, net_salary, status, approved_at, paid_at
             FROM payrolls
             WHERE employee_id = ?
             ORDER BY period DESC, id DESC LIMIT 36",
            [(int)$emp['id']]
        )->fetchAll();
        Response::json(['ok' => true, 'data' => $rows]);
    }

    /** تفاصيل كشف راتب واحد (كل بنود الاستحقاقات والاستقطاعات) للموظف صاحب الراتب فقط (أي حالة، بما في ذلك المسودة) */
    public function myPayrollDetail(string $id): void
    {
        $emp = Database::query("SELECT id FROM employees WHERE user_id = ?", [ApiAuth::id()])->fetch();
        if (!$emp) { Response::json(['ok' => false, 'error' => 'لا يوجد ملف موظف'], 404); return; }
        $payrollId = (int)$id;
        $p = Database::query(
            "SELECT p.* FROM payrolls p WHERE p.id = ? AND p.employee_id = ?",
            [$payrollId, (int)$emp['id']]
        )->fetch();
        if (!$p) { Response::json(['ok' => false, 'error' => 'كشف الراتب غير موجود'], 404); return; }
        $overtime = Database::query(
            "SELECT hours, multiplier, amount FROM payroll_overtime WHERE payroll_id = ? ORDER BY id",
            [$payrollId]
        )->fetchAll();
        $allowances = Database::query(
            "SELECT allowance_type, amount FROM payroll_allowances WHERE payroll_id = ? ORDER BY allowance_type",
            [$payrollId]
        )->fetchAll();
        $p = \App\Core\PayrollPresentation::detail($p);
        Response::json(['ok' => true, 'data' => $p, 'overtime' => $overtime, 'allowances' => $allowances, 'columns' => \App\Core\PayrollPresentation::columns($p)]);
    }

    /** مزامنة النمط الهجين: يستقبل عمليات محلية ويعيد غير المتزامن */
    public function sync(): void
    {
        $b = $this->body();
        $applied = 0;

        // استقبال عمليات من الجهاز المحلي
        foreach ((array)($b['operations'] ?? []) as $op) {
            Helpers::queueSync(
                (string)($op['entity'] ?? 'unknown'),
                (int)($op['entity_id'] ?? 0),
                in_array($op['action'] ?? '', ['insert','update','delete'], true) ? $op['action'] : 'insert',
                (array)($op['payload'] ?? [])
            );
            $applied++;
        }

        // إرسال ما لم يتزامن بعد
        $pending = Database::query(
            "SELECT * FROM sync_queue WHERE synced = 0 ORDER BY id LIMIT 200"
        )->fetchAll();
        if ($pending) {
            $ids = array_column($pending, 'id');
            $in = implode(',', array_fill(0, count($ids), '?'));
            Database::query("UPDATE sync_queue SET synced=1, synced_at=NOW() WHERE id IN ($in)", $ids);
        }

        Response::json(['ok' => true, 'received' => $applied, 'pending' => $pending]);
    }

    // ===================== المبيعات / نقطة البيع =====================

    /** يُرجع منيو الطابق/الصالة المطلوب (?floor_id=X)، أو المنيو الافتراضي إن لم يُحدَّد */
    public function menu(): void
    {
        $floorId = !empty($_GET['floor_id']) ? (int)$_GET['floor_id'] : null;
        $data = MenuService::menuForFloor($floorId);
        Response::json(['ok' => true, 'data' => ['categories' => $data['categories'], 'items' => $data['items']]]);
    }

    /** قائمة الطوابق/الصالات (لاختيار المنيو المناسب في التطبيق) */
    public function floors(): void
    {
        $floors = Database::query("SELECT * FROM floors WHERE is_active=1 ORDER BY sort_order, id")->fetchAll();
        Response::json(['ok' => true, 'data' => $floors]);
    }

    public function tables(): void
    {
        $floorId = !empty($_GET['floor_id']) ? (int)$_GET['floor_id'] : null;
        $sql = "SELECT t.*, f.name AS floor_name,
               (SELECT o.id FROM orders o
                WHERE o.table_id = t.id AND o.status NOT IN ('completed','cancelled')
                ORDER BY o.id DESC LIMIT 1) AS current_order_id,
               (SELECT o.order_no FROM orders o
                WHERE o.table_id = t.id AND o.status NOT IN ('completed','cancelled')
                ORDER BY o.id DESC LIMIT 1) AS current_order
             FROM dining_tables t
             LEFT JOIN floors f ON f.id = t.floor_id";
        $params = [];
        if ($floorId) {
            $sql .= " WHERE t.floor_id = ?";
            $params[] = $floorId;
        }
        $sql .= " ORDER BY f.sort_order, t.name";
        $tables = Database::query($sql, $params)->fetchAll();
        Response::json(['ok' => true, 'data' => $tables]);
    }

    // ===================== إدارة أصناف المنيو (موبايل) =====================

    public function storeMenuItem(): void
    {
        $b = $this->body();
        try {
            $image = ImageUpload::fromBase64($b['image_base64'] ?? null, 'menu_items');
        } catch (\RuntimeException $e) {
            Response::json(['ok' => false, 'error' => 'الصورة: ' . $e->getMessage()], 422);
            return;
        }
        Database::query(
            "INSERT INTO menu_items (category_id, name, description, image, price, cost, prep_minutes, station_id)
             VALUES (?,?,?,?,?,?,?,?)",
            [
                (int)($b['category_id'] ?? 0), (string)($b['name'] ?? ''), (string)($b['description'] ?? ''),
                $image, (float)($b['price'] ?? 0), (float)($b['cost'] ?? 0),
                (int)($b['prep_minutes'] ?? 10), $b['station_id'] ?? null,
            ]
        );
        $id = (int)Database::lastInsertId();
        Helpers::audit('إضافة صنف منيو (API)', 'menu_items', $id);
        Response::json(['ok' => true, 'id' => $id, 'message' => 'تم إضافة الصنف']);
    }

    public function updateMenuItem(string $id): void
    {
        $b = $this->body();
        $item = Database::query("SELECT * FROM menu_items WHERE id = ?", [(int)$id])->fetch();
        if (!$item) { Response::json(['ok' => false, 'error' => 'الصنف غير موجود'], 404); return; }

        try {
            $image = ImageUpload::fromBase64($b['image_base64'] ?? null, 'menu_items') ?? $item['image'];
        } catch (\RuntimeException $e) {
            Response::json(['ok' => false, 'error' => 'الصورة: ' . $e->getMessage()], 422);
            return;
        }
        Database::query(
            "UPDATE menu_items SET category_id=?, name=?, description=?, image=?, price=?, cost=?, prep_minutes=?, station_id=?
             WHERE id = ?",
            [
                (int)($b['category_id'] ?? $item['category_id']), (string)($b['name'] ?? $item['name']),
                (string)($b['description'] ?? $item['description']), $image,
                (float)($b['price'] ?? $item['price']), (float)($b['cost'] ?? $item['cost']),
                (int)($b['prep_minutes'] ?? $item['prep_minutes']), $b['station_id'] ?? $item['station_id'], (int)$id,
            ]
        );
        Helpers::queueSync('menu_items', (int)$id, 'update', [
            'price' => (float)($b['price'] ?? $item['price']),
            'cost'  => (float)($b['cost'] ?? $item['cost']),
        ]);
        Helpers::audit('تعديل صنف منيو (API)', 'menu_items', (int)$id);
        Response::json(['ok' => true, 'message' => 'تم تحديث الصنف']);
    }

    public function deleteMenuItem(string $id): void
    {
        Database::query("UPDATE menu_items SET is_active = 0 WHERE id = ?", [(int)$id]);
        Helpers::audit('حذف صنف منيو (API)', 'menu_items', (int)$id);
        Response::json(['ok' => true, 'message' => 'تم حذف الصنف']);
    }

    /** إتمام عملية بيع من التطبيق (يعيد استخدام منطق نقطة البيع في الويب) */
    public function checkout(): void
    {
        (new PosController())->checkout();
    }

    /** إنشاء طلب كابتن أو إضافة أصناف على طلب مفتوح (نفس منطق الويب) */
    public function captainOrder(): void
    {
        (new CaptainController())->apiOrder();
    }

    public function orderShow(string $id): void
    {
        $order = Database::query(
            "SELECT o.*, t.name AS table_name, u.full_name AS cashier
             FROM orders o
             LEFT JOIN dining_tables t ON t.id = o.table_id
             LEFT JOIN users u ON u.id = o.created_by WHERE o.id = ?", [(int)$id]
        )->fetch();
        if (!$order) { Response::json(['ok' => false, 'error' => 'الطلب غير موجود'], 404); return; }

        $items = Database::query(
            "SELECT oi.*, mi.name FROM order_items oi
             JOIN menu_items mi ON mi.id = oi.menu_item_id WHERE oi.order_id = ?", [(int)$id]
        )->fetchAll();
        $payments = Database::query("SELECT * FROM order_payments WHERE order_id = ?", [(int)$id])->fetchAll();

        Response::json(['ok' => true, 'data' => ['order' => $order, 'items' => $items, 'payments' => $payments]]);
    }

    public function updateOrderStatus(string $id): void
    {
        $b = $this->body();
        $status = (string)($b['status'] ?? '');
        $allowed = ['pending','confirmed','preparing','ready','served','delivering','completed'];
        if (!in_array($status, $allowed, true)) { Response::json(['ok' => false, 'error' => 'حالة غير صالحة'], 422); return; }

        Database::query("UPDATE orders SET status = ?, completed_at = IF(?='completed', NOW(), completed_at) WHERE id = ?",
            [$status, $status, (int)$id]);
        Database::query("INSERT INTO order_status_log (order_id, status, changed_by) VALUES (?,?,?)",
            [(int)$id, $status, ApiAuth::id()]);
        Helpers::queueSync('orders', (int)$id, 'update', ['status' => $status]);
        if ($status === 'completed') {
            Database::query(
                "UPDATE dining_tables SET status='available'
                 WHERE id = (SELECT table_id FROM orders WHERE id = ?)", [(int)$id]);
        }
        Response::json(['ok' => true]);
    }

    /** إدارة الطلبات المعلقة من المدير: موافقة / رفض / تعديل أصناف */
    public function manageOrder(string $id): void
    {
        $b = $this->body();
        $orderId = (int)$id;
        $action = (string)($b['action'] ?? '');
        $notes = (string)($b['notes'] ?? '');

        if (!in_array($action, ['approve', 'reject', 'edit'], true)) {
            Response::json(['ok' => false, 'error' => 'إجراء غير صالح'], 422);
            return;
        }

        $order = Database::query("SELECT * FROM orders WHERE id = ?", [$orderId])->fetch();
        if (!$order) {
            Response::json(['ok' => false, 'error' => 'الطلب غير موجود'], 404);
            return;
        }
        if (in_array($order['status'], ['completed', 'cancelled'], true)) {
            Response::json(['ok' => false, 'error' => 'الطلب غير قابل للتعديل'], 422);
            return;
        }

        Database::beginTransaction();
        try {
            if ($action === 'approve') {
                Database::query("UPDATE orders SET status = ?, notes = ? WHERE id = ?", ['confirmed', $notes, $orderId]);
                Database::query(
                    "INSERT INTO order_status_log (order_id, status, changed_by) VALUES (?,?,?)",
                    [$orderId, 'confirmed', ApiAuth::id()]
                );
                Helpers::queueSync('orders', $orderId, 'update', ['status' => 'confirmed', 'notes' => $notes]);
                Helpers::audit('موافقة على طلب', 'orders', $orderId, $notes);
                if (!empty($order['created_by'])) {
                    try {
                        NotificationService::sendToUser((int)$order['created_by'], 'تمت الموافقة على طلب', "تمت الموافقة على الطلب {$order['order_no']}" . ($notes ? " - $notes" : ''), '/orders/' . $orderId);
                    } catch (\Throwable $ignored) {}
                }
            } elseif ($action === 'reject') {
                Database::query("UPDATE orders SET status = ?, notes = ? WHERE id = ?", ['cancelled', $notes, $orderId]);
                AccountingService::reverseCogsItems($orderId, []);
                AccountingService::reverseAllRevenue($orderId);
                Database::query(
                    "UPDATE dining_tables SET status='available' WHERE id = (SELECT table_id FROM orders WHERE id = ?)",
                    [$orderId]
                );
                Database::query(
                    "INSERT INTO order_status_log (order_id, status, changed_by) VALUES (?,?,?)",
                    [$orderId, 'cancelled', ApiAuth::id()]
                );
                Helpers::queueSync('orders', $orderId, 'update', ['status' => 'cancelled', 'notes' => $notes]);
                Helpers::audit('رفض طلب', 'orders', $orderId, $notes);
                if (!empty($order['created_by'])) {
                    try {
                        NotificationService::sendToUser((int)$order['created_by'], 'تم رفض الطلب', "تم رفض الطلب {$order['order_no']}" . ($notes ? " - $notes" : ''), '/orders/' . $orderId);
                    } catch (\Throwable $ignored) {}
                }
            } else { // edit
                $items = (array)($b['items'] ?? []);
                if (empty($items)) {
                    Database::rollBack();
                    Response::json(['ok' => false, 'error' => 'لا يوجد أصناف للتعديل'], 422);
                    return;
                }

                // تعديل الكميات وحذف الأصناف المستبعدة ( qty <= 0 )
                foreach ($items as $it) {
                    $itemId = (int)($it['id'] ?? 0);
                    $qty = (float)($it['qty'] ?? 0);
                    if ($itemId <= 0) continue;
                    if ($qty <= 0) {
                        Database::query(
                            "UPDATE order_items SET qty = 0, total = 0, kitchen_status = 'cancelled' WHERE id = ? AND order_id = ?",
                            [$itemId, $orderId]
                        );
                    } else {
                        Database::query(
                            "UPDATE order_items SET qty = ?, total = qty * unit_price WHERE id = ? AND order_id = ?",
                            [$qty, $itemId, $orderId]
                        );
                    }
                }
                // استبعاد أي صنف غير موجود في قائمة التعديل
                $sentIds = array_filter(array_map(fn($it) => (int)($it['id'] ?? 0), $items));
                if ($sentIds) {
                    $in = implode(',', array_fill(0, count($sentIds), '?'));
                    Database::query(
                        "UPDATE order_items SET qty = 0, total = 0, kitchen_status = 'cancelled' WHERE order_id = ? AND id NOT IN ($in)",
                        array_merge([$orderId], $sentIds)
                    );
                }

                $itemsSummary = Database::query(
                    "SELECT COALESCE(SUM(total),0) AS subtotal FROM order_items WHERE order_id = ? AND kitchen_status != 'cancelled'",
                    [$orderId]
                )->fetch();
                $subtotal = (float)$itemsSummary['subtotal'];
                $discount = (float)$order['discount'];
                $deliveryFee = (float)($order['delivery_fee'] ?? 0);
                $total = max(0, $subtotal - $discount + $deliveryFee);

                Database::query(
                    "UPDATE orders SET subtotal = ?, total = ?, notes = ?, status = 'pending' WHERE id = ?",
                    [$subtotal, $total, $notes, $orderId]
                );
                Database::query(
                    "INSERT INTO order_status_log (order_id, status, changed_by) VALUES (?,?,?)",
                    [$orderId, 'pending', ApiAuth::id()]
                );
                Helpers::queueSync('orders', $orderId, 'update', ['status' => 'pending', 'notes' => $notes, 'items_edited' => true]);
                Helpers::audit('تعديل أصناف طلب', 'orders', $orderId, $notes);
                if (!empty($order['created_by'])) {
                    try {
                        NotificationService::sendToUser((int)$order['created_by'], 'تم تعديل الطلب', "تم تعديل أصناف الطلب {$order['order_no']}" . ($notes ? " - $notes" : ''), '/orders/' . $orderId);
                    } catch (\Throwable $ignored) {}
                }
            }
            Database::commit();

            // إعادة قراءة الطلب المحدّث لإرجاعه للتطبيق
            $updated = Database::query(
                "SELECT o.*, t.name AS table_name, u.full_name AS cashier
                 FROM orders o
                 LEFT JOIN dining_tables t ON t.id = o.table_id
                 LEFT JOIN users u ON u.id = o.created_by WHERE o.id = ?",
                [$orderId]
            )->fetch();
            $items = Database::query(
                "SELECT oi.*, mi.name FROM order_items oi
                 JOIN menu_items mi ON mi.id = oi.menu_item_id WHERE oi.order_id = ?",
                [$orderId]
            )->fetchAll();

            Response::json(['ok' => true, 'data' => ['order' => $updated, 'items' => $items]]);
        } catch (\Throwable $e) {
            Database::rollBack();
            Response::json(['ok' => false, 'error' => 'خطأ في معالجة الطلب: ' . $e->getMessage()], 500);
        }
    }

    // ===================== الحجوزات =====================

    public function reservations(): void
    {
        $filters = [
            'status' => (string)($_GET['status'] ?? ''),
            'date_from' => (string)($_GET['date_from'] ?? ''),
            'date_to' => (string)($_GET['date_to'] ?? ''),
            'branch_id' => (int)($_GET['branch_id'] ?? 0),
            'q' => (string)($_GET['q'] ?? ''),
        ];
        $data = ReservationService::list(array_filter($filters));
        Response::json(['ok' => true, 'data' => $data]);
    }

    public function getReservation(string $id): void
    {
        $res = ReservationService::getWithDetails((int)$id);
        if (!$res) {
            Response::json(['ok' => false, 'error' => 'الحجز غير موجود'], 404);
            return;
        }
        $logs = Database::query(
            "SELECT * FROM reservation_logs WHERE reservation_id = ? ORDER BY created_at DESC",
            [(int)$id]
        )->fetchAll();
        Response::json(['ok' => true, 'data' => $res, 'logs' => $logs]);
    }

    public function storeReservation(): void
    {
        $b = $this->body();
        try {
            $id = ReservationService::create([
                'customer_id' => (int)($b['customer_id'] ?? 0) ?: null,
                'customer_name' => (string)($b['customer_name'] ?? ''),
                'customer_phone' => (string)($b['customer_phone'] ?? ''),
                'customer_email' => (string)($b['customer_email'] ?? ''),
                'branch_id' => (int)($b['branch_id'] ?? 0) ?: null,
                'table_id' => $b['table_id'] ?? null,
                'guests' => (int)($b['guests'] ?? 2),
                'reserved_at' => (string)($b['reserved_at'] ?? ''),
                'duration_minutes' => (int)($b['duration_minutes'] ?? 90),
                'reservation_type' => (string)($b['reservation_type'] ?? 'dine_in'),
                'source' => (string)($b['source'] ?? 'app'),
                'notes' => (string)($b['notes'] ?? ''),
                'customer_notes' => (string)($b['customer_notes'] ?? ''),
            ], ApiAuth::id());
            Response::json(['ok' => true, 'id' => $id, 'message' => 'تم إنشاء الحجز']);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function updateReservation(string $id): void
    {
        $b = $this->body();
        try {
            ReservationService::update((int)$id, [
                'customer_id' => (int)($b['customer_id'] ?? 0) ?: null,
                'customer_name' => (string)($b['customer_name'] ?? ''),
                'customer_phone' => (string)($b['customer_phone'] ?? ''),
                'customer_email' => (string)($b['customer_email'] ?? ''),
                'branch_id' => (int)($b['branch_id'] ?? 0) ?: null,
                'table_id' => $b['table_id'] ?? null,
                'guests' => (int)($b['guests'] ?? 2),
                'reserved_at' => (string)($b['reserved_at'] ?? ''),
                'duration_minutes' => (int)($b['duration_minutes'] ?? 90),
                'reservation_type' => (string)($b['reservation_type'] ?? ''),
                'source' => (string)($b['source'] ?? ''),
                'notes' => (string)($b['notes'] ?? ''),
                'customer_notes' => (string)($b['customer_notes'] ?? ''),
            ], ApiAuth::id());
            Response::json(['ok' => true, 'message' => 'تم تحديث الحجز']);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function updateReservationStatus(string $id): void
    {
        $b = $this->body();
        $status = (string)($b['status'] ?? '');
        try {
            match ($status) {
                'confirmed' => ReservationService::confirm((int)$id, ApiAuth::id()),
                'seated' => ReservationService::seat((int)$id, ApiAuth::id()),
                'completed' => ReservationService::complete((int)$id, ApiAuth::id()),
                'waiting' => ReservationService::moveToWaiting((int)$id, ApiAuth::id()),
                'cancelled' => ReservationService::cancel((int)$id, (string)($b['reason'] ?? ''), ApiAuth::id()),
                'rejected' => ReservationService::reject((int)$id, (string)($b['reason'] ?? ''), ApiAuth::id()),
                default => throw new \RuntimeException('حالة غير معروفة'),
            };
            Response::json(['ok' => true, 'message' => 'تم تحديث الحالة']);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function reservationAvailableTables(): void
    {
        $guests = (int)($_GET['guests'] ?? 2);
        $at = (string)($_GET['reserved_at'] ?? '');
        $duration = (int)($_GET['duration'] ?? 90);
        $exclude = (int)($_GET['exclude'] ?? 0);
        $branchId = (int)($_GET['branch_id'] ?? 0);
        $tables = ReservationService::findAvailableTables($guests, $at, $duration, $exclude ?: null, $branchId ?: null);
        Response::json(['ok' => true, 'tables' => $tables]);
    }

    // ===================== المطبخ =====================

    public function kitchenOrders(): void
    {
        (new KitchenController())->data();
    }

    public function kitchenItemStatus(string $id): void
    {
        (new KitchenController())->updateItemStatus($id);
    }

    // ===================== المخزون =====================

    public function warehouses(): void
    {
        $warehouses = Database::query(
            "SELECT id, name, warehouse_type, is_active FROM warehouses WHERE is_active = 1 ORDER BY name"
        )->fetchAll();
        Response::json(['ok' => true, 'data' => $warehouses]);
    }

    public function recipes(): void
    {
        $recipes = Database::query(
            "SELECT pr.id, pr.name, pr.output_item_id, pr.output_qty, i.name AS output_name
             FROM production_recipes pr
             JOIN items i ON i.id = pr.output_item_id
             WHERE pr.is_active = 1 ORDER BY pr.name"
        )->fetchAll();
        Response::json(['ok' => true, 'data' => $recipes]);
    }

    public function employees(): void
    {
        $user = ApiAuth::user();
        $subordinatesOnly = (int)($_GET['subordinates'] ?? 0) === 1;
        if ($subordinatesOnly) {
            if (!$user) {
                Response::json(['ok' => false, 'error' => 'غير مصرح'], 401);
                return;
            }
            $ids = array_column(WorkflowEngine::subordinateEmployees((int)$user['id']), 'id');
            if (empty($ids)) {
                Response::json(['ok' => true, 'data' => []]);
                return;
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $employees = Database::query(
                "SELECT id, full_name, emp_code, file_status FROM employees
                 WHERE file_status = 'open' AND status = 'active' AND id IN ($placeholders)
                 ORDER BY full_name",
                $ids
            )->fetchAll();
            Response::json(['ok' => true, 'data' => $employees]);
            return;
        }
        $employees = Database::query(
            "SELECT id, full_name, emp_code, file_status FROM employees WHERE file_status = 'open' AND status = 'active' ORDER BY full_name"
        )->fetchAll();
        Response::json(['ok' => true, 'data' => $employees]);
    }

    public function departments(): void
    {
        $departments = Database::query(
            "SELECT id, name, warehouse_type FROM warehouses WHERE is_active = 1 ORDER BY name"
        )->fetchAll();
        Response::json(['ok' => true, 'data' => $departments]);
    }

    /** ========== تقييم أداء الفريق من الموبايل (حسب الهيكل الإداري) ========== */

    /** قائمة المرؤوسين (كامل شجرة الهيكل الإداري) مع حالة تقييمهم للفترة المطلوبة */
    public function teamEvaluations(): void
    {
        $user = ApiAuth::user();
        $period = (string)($_GET['period'] ?? date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            Response::json(['ok' => false, 'error' => 'فترة غير صالحة'], 422);
            return;
        }
        $subordinates = WorkflowEngine::subordinateEmployees((int)$user['id']);
        if (!$subordinates) {
            Response::json(['ok' => true, 'period' => $period, 'data' => []]);
            return;
        }
        $ids = array_column($subordinates, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = Database::query(
            "SELECT e.id, e.full_name, e.emp_code, j.title AS job_title, d.name AS department,
                    ev.id AS evaluation_id, ev.total_score, ev.status AS evaluation_status
             FROM employees e
             LEFT JOIN jobs j ON j.id = e.job_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN evaluations ev ON ev.employee_id = e.id AND ev.period = ? AND ev.evaluation_type = 'monthly'
             WHERE e.id IN ($placeholders)
             ORDER BY e.full_name",
            array_merge([$period], $ids)
        )->fetchAll();
        Response::json(['ok' => true, 'period' => $period, 'data' => $rows]);
    }

    /** مؤشرات الأداء القابلة للتطبيق على موظف مرؤوس معيّن */
    public function teamEmployeeKpis(string $id): void
    {
        $user = ApiAuth::user();
        $employeeId = (int)$id;
        $subordinateIds = array_column(WorkflowEngine::subordinateEmployees((int)$user['id']), 'id');
        if (!in_array($employeeId, $subordinateIds, true) && !Auth::can('hr.evaluation.manage')) {
            Response::json(['ok' => false, 'error' => 'هذا الموظف ليس ضمن فريقك'], 403);
            return;
        }
        $kpiIds = HrService::getApplicableKpiIds($employeeId);
        $kpis = [];
        if ($kpiIds) {
            $placeholders = implode(',', array_fill(0, count($kpiIds), '?'));
            $kpis = Database::query(
                "SELECT id, name, description, weight FROM performance_kpis WHERE id IN ($placeholders) ORDER BY id",
                $kpiIds
            )->fetchAll();
        }
        $period = (string)($_GET['period'] ?? date('Y-m'));
        $existing = Database::query(
            "SELECT id FROM evaluations WHERE employee_id = ? AND period = ? AND evaluation_type = 'monthly'",
            [$employeeId, $period]
        )->fetch();
        Response::json(['ok' => true, 'data' => $kpis, 'already_evaluated' => (bool)$existing]);
    }

    /** إنشاء تقييم أداء لمرؤوس ضمن الهيكل الإداري */
    public function storeTeamEvaluation(): void
    {
        $b = $this->body();
        $user = ApiAuth::user();
        $employeeId = (int)($b['employee_id'] ?? 0);
        $subordinateIds = array_column(WorkflowEngine::subordinateEmployees((int)$user['id']), 'id');
        if (!in_array($employeeId, $subordinateIds, true) && !Auth::can('hr.evaluation.manage')) {
            Response::json(['ok' => false, 'error' => 'هذا الموظف ليس ضمن فريقك'], 403);
            return;
        }
        if (!HrService::isEmployeeFileOpen($employeeId)) {
            Response::json(['ok' => false, 'error' => 'ملف الموظف مغلق أو مأرشف'], 422);
            return;
        }
        $period = (string)($b['period'] ?? date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            Response::json(['ok' => false, 'error' => 'فترة غير صالحة'], 422);
            return;
        }
        $existing = Database::query(
            "SELECT id FROM evaluations WHERE employee_id = ? AND period = ? AND evaluation_type = 'monthly'",
            [$employeeId, $period]
        )->fetch();
        if ($existing) {
            Response::json(['ok' => false, 'error' => 'تم تقييم هذا الموظف مسبقاً لهذه الفترة'], 422);
            return;
        }
        $applicableKpiIds = HrService::getApplicableKpiIds($employeeId);
        $weightMap = [];
        if ($applicableKpiIds) {
            $placeholders = implode(',', array_fill(0, count($applicableKpiIds), '?'));
            foreach (Database::query("SELECT id, weight FROM performance_kpis WHERE id IN ($placeholders)", $applicableKpiIds)->fetchAll() as $k) {
                $weightMap[(int)$k['id']] = (float)$k['weight'];
            }
        }
        $kpiScores = [];
        foreach ((array)($b['kpi_scores'] ?? []) as $row) {
            $kpiId = (int)($row['kpi_id'] ?? 0);
            if ($kpiId && in_array($kpiId, $applicableKpiIds, true)) {
                $kpiScores[] = [
                    'kpi_id' => $kpiId,
                    'score' => (float)($row['score'] ?? 0),
                    'weight' => $weightMap[$kpiId] ?? 0,
                    'notes' => (string)($row['notes'] ?? ''),
                ];
            }
        }
        if (!$kpiScores) {
            Response::json(['ok' => false, 'error' => 'يجب إدخال درجة لمؤشر واحد على الأقل'], 422);
            return;
        }
        try {
            $evalId = HrService::createEvaluation(
                $employeeId, $period, 'monthly', $kpiScores,
                (string)($b['notes'] ?? ''), (int)$user['id']
            );
            Response::json(['ok' => true, 'evaluation_id' => $evalId]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** تفاصيل تقييم أداء مرؤوس (يجب أن يكون ضمن فريق المدير أو يملك صلاحية إدارة التقييم) */
    public function teamEvaluationDetail(string $id): void
    {
        $user = ApiAuth::user();
        $evaluationId = (int)$id;
        $eval = Database::query(
            "SELECT ev.*, e.full_name, e.emp_code, j.title AS job_title, d.name AS department
             FROM evaluations ev
             JOIN employees e ON e.id = ev.employee_id
             LEFT JOIN jobs j ON j.id = e.job_id
             LEFT JOIN departments d ON d.id = e.department_id
             WHERE ev.id = ?",
            [$evaluationId]
        )->fetch();
        if (!$eval) {
            Response::json(['ok' => false, 'error' => 'التقييم غير موجود'], 404);
            return;
        }
        $subordinateIds = array_column(WorkflowEngine::subordinateEmployees((int)$user['id']), 'id');
        if (!in_array((int)$eval['employee_id'], $subordinateIds, true) && !Auth::can('hr.evaluation.manage')) {
            Response::json(['ok' => false, 'error' => 'هذا التقييم ليس ضمن فريقك'], 403);
            return;
        }
        $items = HrService::getEvaluationItems($evaluationId);
        Response::json(['ok' => true, 'data' => $eval, 'items' => $items]);
    }

    /** تقديم طلب نسيان بصمة نيابةً عن مرؤوس ضمن فريق المدير الحالي */
    public function teamRequestMissingAttendance(string $id): void
    {
        $user = ApiAuth::user();
        $employeeId = (int)$id;
        $subordinateIds = array_column(WorkflowEngine::subordinateEmployees((int)$user['id']), 'id');
        if (!in_array($employeeId, $subordinateIds, true)) {
            Response::json(['ok' => false, 'error' => 'هذا الموظف ليس ضمن فريقك'], 403);
            return;
        }
        $emp = Database::query("SELECT id, full_name FROM employees WHERE id = ?", [$employeeId])->fetch();
        if (!$emp) {
            Response::json(['ok' => false, 'error' => 'الموظف غير موجود'], 404);
            return;
        }
        $b = $this->body();
        $payload = [
            'request_type' => (string)($b['request_type'] ?? 'in'),
            'clock_in_at' => ($b['clock_in_at'] ?? '') ?: null,
            'clock_out_at' => ($b['clock_out_at'] ?? '') ?: null,
            'reason' => (string)($b['reason'] ?? ''),
        ];
        try {
            $requestId = HrService::createMissingAttendanceRequest($employeeId, $payload);
            $wfRequestId = HrWorkflowService::request('missing_attendance_request', $requestId, (int)$user['id'], [
                'employee_id' => $employeeId,
                'employee_name' => $emp['full_name'] ?? '',
                'request_type' => $payload['request_type'],
                'clock_in_at' => $payload['clock_in_at'],
                'clock_out_at' => $payload['clock_out_at'],
                'reason' => $payload['reason'],
                'title' => 'طلب نسيان بصمة نيابةً عن ' . Helpers::e($emp['full_name'] ?? '') . ' - مقدّم من المدير',
            ]);
            if ($wfRequestId) {
                Database::query("UPDATE missing_attendance_requests SET workflow_request_id = ? WHERE id = ?", [$wfRequestId, $requestId]);
            }
            Response::json(['ok' => true, 'message' => 'تم إرسال طلب نسيان البصمة نيابةً عن الموظف', 'id' => $requestId]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** تقديم طلب إجازة نيابةً عن مرؤوس ضمن فريق المدير الحالي */
    public function teamRequestLeave(string $id): void
    {
        $user = ApiAuth::user();
        $employeeId = (int)$id;
        $subordinateIds = array_column(WorkflowEngine::subordinateEmployees((int)$user['id']), 'id');
        if (!in_array($employeeId, $subordinateIds, true)) {
            Response::json(['ok' => false, 'error' => 'هذا الموظف ليس ضمن فريقك'], 403);
            return;
        }
        $emp = Database::query("SELECT id, full_name FROM employees WHERE id = ?", [$employeeId])->fetch();
        if (!$emp) {
            Response::json(['ok' => false, 'error' => 'الموظف غير موجود'], 404);
            return;
        }
        $b = $this->body();
        $leaveType = (string)($b['leave_type'] ?? 'unpaid');
        $dateFrom = (string)($b['date_from'] ?? '');
        $dateTo = (string)($b['date_to'] ?? '');
        $reason = (string)($b['reason'] ?? '');

        // قيم عمود leave_requests.leave_type الفعلية: enum('annual','sick','unpaid','emergency','hourly','n/a')
        $validTypes = ['unpaid', 'hourly', 'emergency', 'sick', 'annual'];
        if (!in_array($leaveType, $validTypes, true)) {
            Response::json(['ok' => false, 'error' => 'نوع الإجازة غير صالح'], 422);
            return;
        }
        if (!$dateFrom || !$dateTo || $dateTo < $dateFrom) {
            Response::json(['ok' => false, 'error' => 'تاريخ البدء والانتهاء غير صالح'], 422);
            return;
        }
        if (!HrService::isEmployeeFileOpen($employeeId)) {
            Response::json(['ok' => false, 'error' => 'ملف الموظف مغلق أو مأرشف'], 403);
            return;
        }
        if (HrService::hasLeaveOverlap($employeeId, $dateFrom, $dateTo)) {
            Response::json(['ok' => false, 'error' => 'يوجد طلب إجازة متداخل مع نفس الفترة'], 422);
            return;
        }

        Database::query(
            "INSERT INTO leave_requests (employee_id, requested_by, request_type, leave_type, date_from, date_to, reason, status)
             VALUES (?,?,?,?,?,?,?,?)",
            [$employeeId, (int)$user['id'], 'leave', $leaveType, $dateFrom, $dateTo, $reason, 'pending']
        );
        $requestId = (int)Database::lastInsertId();

        HrWorkflowService::request('leave_request', $requestId, (int)$user['id'], [
            'employee_id' => $employeeId,
            'employee_name' => $emp['full_name'] ?? '',
            'request_type' => 'leave',
            'leave_type' => $leaveType,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'reason' => $reason,
            'title' => 'طلب إجازة نيابةً عن ' . Helpers::e($emp['full_name'] ?? '') . ' - مقدّم من المدير',
        ]);

        Response::json(['ok' => true, 'message' => 'تم إرسال طلب الإجازة نيابةً عن الموظف', 'id' => $requestId]);
    }

    /** هل الموظف الحالي ضمن قسم الحراسة؟ (لتفعيل واجهة إدارة السكن بالموبايل) */
    private function apiIsHousingGuard(): bool
    {
        return \App\Core\HousingAccess::isGuard();
    }

    private function apiCanManageHousing(): bool
    {
        return \App\Core\HousingAccess::canView();
    }

    /** قائمة مواقع السكن (باستثناء السكن الخارجي المخفي) */
    public function housingSites(): void
    {
        if (!$this->apiCanManageHousing()) {
            Response::json(['ok' => false, 'error' => 'ليس لديك صلاحية إدارة أو عرض السكن'], 403);
            return;
        }
        $rows = Database::query("SELECT id, site_key, name FROM housing_sites WHERE site_key <> 'external' ORDER BY name")->fetchAll();
        Response::json(['ok' => true, 'data' => $rows]);
    }

    /** شقق موقع سكن معين */
    public function housingApartments(): void
    {
        if (!$this->apiCanManageHousing()) {
            Response::json(['ok' => false, 'error' => 'غير مصرح'], 403);
            return;
        }
        $siteId = (int)($_GET['site_id'] ?? 0);
        $rows = Database::query("SELECT id, apartment_number FROM housing_apartments WHERE site_id = ? ORDER BY apartment_number", [$siteId])->fetchAll();
        Response::json(['ok' => true, 'data' => $rows]);
    }

    /** غرف شقة معينة مع عدد الأسرة المتاحة */
    public function housingRooms(): void
    {
        if (!$this->apiCanManageHousing()) {
            Response::json(['ok' => false, 'error' => 'غير مصرح'], 403);
            return;
        }
        $apartmentId = (int)($_GET['apartment_id'] ?? 0);
        $excludeId = (int)($_GET['exclude_id'] ?? 0);
        $rows = Database::query(
            "SELECT r.id, r.room_number, r.bed_capacity,
                    (SELECT COUNT(*) FROM employee_housing_assignments ea WHERE ea.room_id = r.id AND ea.status = 'active' AND ea.id != ?) AS occupied_beds
             FROM housing_rooms r WHERE r.apartment_id = ? ORDER BY r.room_number",
            [$excludeId, $apartmentId]
        )->fetchAll();
        foreach ($rows as &$r) {
            $r['available_beds'] = max(0, (int)$r['bed_capacity'] - (int)$r['occupied_beds']);
        }
        Response::json(['ok' => true, 'data' => $rows]);
    }

    /** All housing residents, with independent per-row editing authorization. */
    public function housingEmployees(): void
    {
        if (!$this->apiCanManageHousing()) {
            Response::json(['ok' => false, 'error' => 'ليس لديك صلاحية إدارة أو عرض السكن'], 403);
            return;
        }
        $rows = \App\Core\HousingAccess::employees(($_GET['residents_only'] ?? '0') === '1');
        Response::json(['ok' => true, 'data' => $rows]);
    }

    /** تحديث سكن موظف ضمن قسم الحراسة (الموقع/الشقة/الغرفة) من تطبيق الموبايل */
    public function updateHousingAssignment(string $id): void
    {
        if (!$this->apiCanManageHousing()) {
            Response::json(['ok' => false, 'error' => 'ليس لديك صلاحية إدارة أو عرض السكن'], 403);
            return;
        }
        $employeeId = (int)$id;
        $emp = Database::query("SELECT e.id,e.file_status,COALESCE(d.name,e.department) department_name FROM employees e LEFT JOIN departments d ON d.id=e.department_id WHERE e.id=?",[$employeeId])->fetch();
        if (!$emp || !\App\Core\HousingAccess::canEdit($emp)) {
            Response::json(['ok'=>false,'error'=>'ليس لديك صلاحية تعديل تسكين هذا الموظف'],403);
            return;
        }

        $b = $this->body();
        $siteId = (int)($b['site_id'] ?? 0);
        $apartmentId = (int)($b['apartment_id'] ?? 0) ?: null;
        $roomId = (int)($b['room_id'] ?? 0) ?: null;
        if (!$siteId) {
            Response::json(['ok' => false, 'error' => 'الموقع مطلوب'], 422);
            return;
        }

        $existing = Database::query("SELECT id FROM employee_housing_assignments WHERE employee_id = ? AND status = 'active'", [$employeeId])->fetch();
        $excludeId = $existing ? (int)$existing['id'] : 0;

        if ($roomId) {
            $room = Database::query("SELECT bed_capacity FROM housing_rooms WHERE id = ?", [$roomId])->fetch();
            $occupied = (int)Database::query(
                "SELECT COUNT(*) FROM employee_housing_assignments WHERE room_id = ? AND status = 'active' AND id != ?",
                [$roomId, $excludeId]
            )->fetchColumn();
            if (!$room || $occupied >= (int)$room['bed_capacity']) {
                Response::json(['ok' => false, 'error' => 'لا يوجد سرير متاح في هذه الغرفة'], 422);
                return;
            }
        }

        $siteRow = Database::query("SELECT site_key, monthly_deduction FROM housing_sites WHERE id = ?", [$siteId])->fetch();
        $deductionAmount = (float)($siteRow['monthly_deduction'] ?? 0);

        Database::beginTransaction();
        try {
            if ($existing) {
                Database::query(
                    "UPDATE employee_housing_assignments SET site_id = ?, apartment_id = ?, room_id = ?, deduction_amount = ? WHERE id = ?",
                    [$siteId, $apartmentId, $roomId, $deductionAmount, (int)$existing['id']]
                );
            } else {
                Database::query(
                    "INSERT INTO employee_housing_assignments (employee_id, site_id, apartment_id, room_id, deduction_amount, assigned_date, status, created_by)
                     VALUES (?,?,?,?,?,CURDATE(),'active',?)",
                    [$employeeId, $siteId, $apartmentId, $roomId, $deductionAmount, (int)ApiAuth::id()]
                );
            }
            if ($siteRow && $siteRow['site_key']) {
                Database::query("UPDATE employees SET housing_type = ?, housing_monthly_deduction = ? WHERE id = ?", [$siteRow['site_key'], $deductionAmount, $employeeId]);
            }
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            Response::json(['ok' => false, 'error' => 'تعذر التحديث: ' . $e->getMessage()], 500);
            return;
        }
        Response::json(['ok' => true, 'message' => 'تم تحديث بيانات السكن بنجاح']);
    }



    public function mobileItems(): void
    {
        $items = Database::query(
            "SELECT i.id, i.name, i.unit_id, u.name AS unit_name, c.name AS category_name
             FROM items i
             JOIN item_categories c ON c.id = i.category_id
             JOIN units u ON u.id = i.unit_id
             WHERE i.is_active = 1 ORDER BY i.name"
        )->fetchAll();
        Response::json(['ok' => true, 'data' => $items]);
    }

    /** أصناف طلبات الشراء المُدارة من واجهة الإدارة */
    public function mobilePurchaseRequestItems(): void
    {
        $db = Database::query("SELECT DATABASE()")->fetchColumn();
        $hasFlag = (bool) Database::query(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'items' AND COLUMN_NAME = 'available_for_purchase_request'",
            [$db]
        )->fetchColumn();
        if ($hasFlag) {
            $items = Database::query(
                "SELECT i.id, i.name, i.unit_id, u.name AS unit_name
                 FROM items i
                 LEFT JOIN units u ON u.id = i.unit_id
                 WHERE i.is_active = 1 AND i.available_for_purchase_request = 1 ORDER BY i.name"
            )->fetchAll();
        } else {
            $items = Database::query(
                "SELECT i.id, i.name, i.unit_id, u.name AS unit_name
                 FROM items i
                 LEFT JOIN units u ON u.id = i.unit_id
                 WHERE i.is_active = 1 ORDER BY i.name"
            )->fetchAll();
        }
        Response::json(['ok' => true, 'data' => $items]);
    }



    public function inventory(): void
    {
        $items = Database::query(
            "SELECT i.*, c.name AS category_name, c.type AS category_type, u.name AS unit_name,
                    COALESCE(SUM(s.qty),0) AS total_qty
             FROM items i
             JOIN item_categories c ON c.id = i.category_id
             JOIN units u ON u.id = i.unit_id
             LEFT JOIN stock s ON s.item_id = i.id
             WHERE i.is_active = 1
             GROUP BY i.id ORDER BY i.name"
        )->fetchAll();
        foreach ($items as &$it) {
            $it['total_qty'] = UnitConversionService::fromBaseUnit((int) $it['unit_id'], (float) $it['total_qty']);
        }
        unset($it);
        Response::json(['ok' => true, 'data' => $items]);
    }

    public function inventoryBarcode(): void
    {
        $code = trim((string) ($_GET['code'] ?? ''));
        $warehouseId = (int) ($_GET['warehouse_id'] ?? 0);
        if ($code === '') {
            Response::json(['ok' => false, 'error' => 'الباركود فارغ'], 422);
            return;
        }
        $item = BarcodeService::findItem($code, $warehouseId > 0 ? $warehouseId : null);
        if (!$item) {
            Response::json(['ok' => false, 'error' => 'المادة غير موجودة'], 404);
            return;
        }
        $item['total_balance'] = UnitConversionService::fromBaseUnit($item['unit_id'], StockService::totalBalance((int) $item['id']));
        Response::json(['ok' => true, 'data' => $item]);
    }

    public function inventoryMovements(): void
    {
        $movements = Database::query(
            "SELECT m.*, i.name AS item_name, w.name AS warehouse_name
             FROM stock_movements m
             JOIN items i ON i.id = m.item_id
             JOIN warehouses w ON w.id = m.warehouse_id
             ORDER BY m.id DESC LIMIT 100"
        )->fetchAll();
        Response::json(['ok' => true, 'data' => $movements]);
    }

    public function storeInventoryMovement(): void
    {
        $b = $this->body();
        $item = $this->resolveApiItem($b['barcode'] ?? null, $b['item_id'] ?? null);
        if (!$item) {
            Response::json(['ok' => false, 'error' => 'الباركود أو المادة غير معروفة'], 404);
            return;
        }
        $itemId = (int) $item['id'];
        $warehouseId = (int) ($b['warehouse_id'] ?? 1);
        $unitId = (int) ($b['unit_id'] ?? $item['unit_id']);
        $qty = (float) ($b['qty'] ?? 0);
        $type = (string) ($b['movement_type'] ?? 'in');
        $allowed = ['in', 'out', 'adjustment', 'return', 'consumption'];
        if (!in_array($type, $allowed, true)) {
            Response::json(['ok' => false, 'error' => 'نوع حركة غير صالح'], 422);
            return;
        }
        if (in_array($type, ['out', 'consumption'], true)) {
            $baseQty = UnitConversionService::toBaseUnit($unitId, $qty);
            $balance = StockService::balance($warehouseId, $itemId);
            if ($balance < $baseQty - 0.0001) {
                $displayBalance = UnitConversionService::fromBaseUnit($item['unit_id'], $balance);
                Response::json(['ok' => false, 'error' => 'الكمية في المخزن غير كافية (متوفر: ' . $displayBalance . ' ' . ($item['unit_name'] ?? '') . ')'], 422);
                return;
            }
        }

        $destinationWarehouseId = (int) ($b['destination_warehouse_id'] ?? 0);
        if ($destinationWarehouseId > 0 && $destinationWarehouseId !== $warehouseId) {
            $destinationExists = Database::query(
                "SELECT id FROM warehouses WHERE id = ? AND is_active = 1",
                [$destinationWarehouseId]
            )->fetch();
            if (!$destinationExists) {
                Response::json(['ok' => false, 'error' => 'المخزن/القسم الوجهة غير موجود أو غير نشط'], 422);
                return;
            }
        }

        $unitCost = (float) ($b['unit_cost'] ?? 0);
        $reference = (string) ($b['reference'] ?? '');
        $notes = (string) ($b['notes'] ?? '');
        $expiryDate = $b['expiry_date'] ?? null;

        Database::beginTransaction();
        try {
            StockService::moveWithUnit(
                $warehouseId,
                $itemId,
                $type,
                $qty,
                $unitId,
                $unitCost,
                $reference,
                $notes,
                $expiryDate,
                $destinationWarehouseId > 0 ? $destinationWarehouseId : null
            );

            if ($destinationWarehouseId > 0 && $destinationWarehouseId !== $warehouseId) {
                StockService::moveWithUnit(
                    $destinationWarehouseId,
                    $itemId,
                    'in',
                    $qty,
                    $unitId,
                    $unitCost,
                    $reference,
                    $notes,
                    $expiryDate
                );
            }
            Database::commit();
            Response::json(['ok' => true, 'balance' => UnitConversionService::fromBaseUnit($item['unit_id'], StockService::balance($warehouseId, $itemId))]);
        } catch (\Throwable $e) {
            Database::rollBack();
            Response::json(['ok' => false, 'error' => 'خطأ أثناء تسجيل الحركة: ' . $e->getMessage()], 500);
        }
    }

    public function inventoryTransfers(): void
    {
        $limit = max(1, min(100, (int) ($_GET['limit'] ?? 50)));
        $offset = max(0, (int) ($_GET['offset'] ?? 0));
        $userId = ApiAuth::id();
        $transfers = Database::query(
            "SELECT t.*, wf.name AS from_name, wt.name AS to_name
             FROM stock_transfers t
             JOIN warehouses wf ON wf.id = t.from_warehouse_id
             JOIN warehouses wt ON wt.id = t.to_warehouse_id
             WHERE t.requested_by = ?
             ORDER BY t.id DESC LIMIT ? OFFSET ?",
            [$userId, $limit, $offset]
        )->fetchAll();
        Response::json(['ok' => true, 'data' => $transfers]);
    }

    public function storeInventoryTransfer(): void
    {
        $b = $this->body();
        $items = (array) ($b['items'] ?? []);
        if (empty($items)) {
            Response::json(['ok' => false, 'error' => 'يجب إرسال بنود المناقلة'], 422);
            return;
        }

        $no = Helpers::docNumber('TRF', 'stock_transfers', 'transfer_no');
        Database::beginTransaction();
        try {
            Database::query(
                "INSERT INTO stock_transfers (transfer_no, from_warehouse_id, to_warehouse_id, requested_by, notes)
                 VALUES (?,?,?,?,?)",
                [$no, (int) ($b['from_warehouse_id'] ?? 0), (int) ($b['to_warehouse_id'] ?? 0),
                 ApiAuth::id(), (string) ($b['notes'] ?? '')]
            );
            $transferId = (int) Database::lastInsertId();
            foreach ($items as $line) {
                $line = (array) $line;
                $item = $this->resolveApiItem($line['barcode'] ?? null, $line['item_id'] ?? null);
                if (!$item) {
                    throw new \Exception('بند غير معروف: ' . ($line['barcode'] ?? $line['item_id'] ?? ''));
                }
                $unitId = (int) ($line['unit_id'] ?? $item['unit_id']);
                $qty = (float) ($line['qty'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }
                $baseQty = UnitConversionService::toBaseUnit($unitId, $qty);
                Database::query(
                    "INSERT INTO stock_transfer_items (transfer_id, item_id, qty, unit_id) VALUES (?,?,?,?)",
                    [$transferId, (int) $item['id'], $baseQty, $unitId]
                );
            }
            ApprovalService::request('stock_transfer', $transferId);
            Database::commit();
            Response::json(['ok' => true, 'transfer_id' => $transferId, 'transfer_no' => $no]);
        } catch (\Throwable $e) {
            Database::rollBack();
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function deliverInventoryTransfer(string $id): void
    {
        try {
            StockService::deliverTransfer((int) $id);
            Response::json(['ok' => true]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function inventoryStocktakes(): void
    {
        $stocktakes = Database::query(
            "SELECT st.*, w.name AS warehouse_name
             FROM stocktakes st
             JOIN warehouses w ON w.id = st.warehouse_id
             WHERE st.status = 'open'
             ORDER BY st.id DESC LIMIT 50"
        )->fetchAll();
        Response::json(['ok' => true, 'data' => $stocktakes]);
    }

    public function storeInventoryStocktake(): void
    {
        $b = $this->body();
        $warehouseId = (int) ($b['warehouse_id'] ?? 0);
        $items = (array) ($b['items'] ?? []);
        if ($warehouseId <= 0 || empty($items)) {
            Response::json(['ok' => false, 'error' => 'يجب تحديد المخزن وبنود الجرد'], 422);
            return;
        }

        Database::beginTransaction();
        try {
            Database::query(
                "INSERT INTO stocktakes (warehouse_id, stocktake_type, status, created_by)
                 VALUES (?,?, 'open', ?)",
                [$warehouseId, (string) ($b['stocktake_type'] ?? 'periodic'), ApiAuth::id()]
            );
            $stId = (int) Database::lastInsertId();
            $totalDiff = 0.0;
            foreach ($items as $line) {
                $line = (array) $line;
                $item = $this->resolveApiItem($line['barcode'] ?? null, $line['item_id'] ?? null);
                if (!$item) {
                    throw new \Exception('بند غير معروف: ' . ($line['barcode'] ?? $line['item_id'] ?? ''));
                }
                $itemId = (int) $item['id'];
                $unitId = (int) ($line['unit_id'] ?? $item['unit_id']);
                $counted = (float) ($line['counted_qty'] ?? 0);
                $countedBase = UnitConversionService::toBaseUnit($unitId, $counted);
                $systemQty = StockService::balance($warehouseId, $itemId);
                Database::query(
                    "INSERT INTO stocktake_items (stocktake_id, item_id, system_qty, counted_qty) VALUES (?,?,?,?)",
                    [$stId, $itemId, $systemQty, $countedBase]
                );
                $totalDiff += $countedBase - $systemQty;
            }
            Database::commit();
            Response::json(['ok' => true, 'stocktake_id' => $stId, 'diff' => $totalDiff]);
        } catch (\Throwable $e) {
            Database::rollBack();
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function inventoryManufacture(): void
    {
        $b = $this->body();
        try {
            ProductionService::manufacture(
                (int) ($b['recipe_id'] ?? 0),
                (float) ($b['qty'] ?? 0),
                (int) ($b['from_warehouse_id'] ?? 0),
                (int) ($b['to_warehouse_id'] ?? 0)
            );
            Response::json(['ok' => true]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    // ===================== المشتريات =====================

    public function purchaseRequests(): void
    {
        $requests = Database::query(
            "SELECT pr.*, u.full_name AS requester
             FROM purchase_requests pr
             LEFT JOIN users u ON u.id = pr.requested_by
             ORDER BY pr.id DESC LIMIT 100"
        )->fetchAll();
        Response::json(['ok' => true, 'data' => $requests]);
    }

    public function storePurchaseRequest(): void
    {
        $b = $this->body();
        $no = Helpers::docNumber('PRQ', 'purchase_requests', 'request_no');
        $items = $b['items'] ?? [];
        $departmentId = (int)($b['department_id'] ?? 0);
        $departmentName = $departmentId
            ? Database::query("SELECT name FROM departments WHERE id = ?", [$departmentId])->fetchColumn()
            : (string)($b['department'] ?? '');
        if (is_array($items) && !empty($items)) {
            Database::query(
                "INSERT INTO purchase_requests (request_no, department, notes, requested_by) VALUES (?,?,?,?)",
                [$no, $departmentName, (string)($b['notes'] ?? ''), ApiAuth::id()]
            );
            $requestId = (int)Database::lastInsertId();
            foreach ($items as $it) {
                $itemId = (int)($it['item_id'] ?? 0);
                $qty = (float)($it['qty'] ?? 0);
                $notes = (string)($it['reason'] ?? ($it['item_notes'] ?? ''));
                if ($itemId <= 0 || $qty <= 0) continue;
                $itemName = Database::query("SELECT name FROM items WHERE id = ?", [$itemId])->fetchColumn();
                Database::query(
                    "INSERT INTO purchase_request_items (request_id, item_id, new_item_name, qty, notes) VALUES (?,?,?,?,?)",
                    [$requestId, $itemId, $itemName, $qty, $notes]
                );
            }
            $template = WorkflowTemplateService::getTemplateByCode('purchase_request');
            if ($template) {
                $wfData = [
                    'title' => 'طلب شراء ' . $no,
                    'department_id' => $departmentId,
                    'department' => $departmentName,
                    'reason' => (string)($b['notes'] ?? ''),
                    'items' => $items,
                    'urgent' => !empty($b['urgent']) ? '1' : '0',
                    'entity_type' => 'purchase_request',
                    'entity_id' => $requestId,
                ];
                if (!empty($b['attachment']) && is_string($b['attachment']) && str_starts_with($b['attachment'], 'data:')) {
                    $saved = $this->saveBase64File($b['attachment'], 'purchase_attachment');
                    if ($saved) $wfData['attachment'] = $saved;
                }
                $wfId = WorkflowEngine::createRequest((int)$template['id'], (int)ApiAuth::id(), $wfData, true);
                Database::query("UPDATE purchase_requests SET workflow_request_id = ? WHERE id = ?", [$wfId, $requestId]);
                Database::query(
                    "INSERT INTO hr_workflow_links (entity_type, entity_id, request_id) VALUES (?,?,?)",
                    ['purchase_request', $requestId, $wfId]
                );
                WorkflowEngine::submitRequest($wfId, (int)ApiAuth::id());
                Response::json(['ok' => true, 'message' => "تم إنشاء طلب الشراء $no وأرسل لمسار الموافقات", 'id' => $requestId, 'workflow_request_id' => $wfId]);
                return;
            }
            ApprovalService::request('purchase_request', $requestId);
            Response::json(['ok' => true, 'message' => "تم إنشاء طلب الشراء $no وأرسل للموافقة", 'id' => $requestId]);
            return;
        }

        // سلوك Legacy: صنف واحد مع item_id/qty
        Database::query(
            "INSERT INTO purchase_requests (request_no, department, notes, requested_by) VALUES (?,?,?,?)",
            [$no, (string)($b['department'] ?? ''), (string)($b['notes'] ?? ''), ApiAuth::id()]
        );
        $requestId = (int)Database::lastInsertId();
        Database::query(
            "INSERT INTO purchase_request_items (request_id, item_id, new_item_name, qty, notes) VALUES (?,?,?,?,?)",
            [$requestId, $b['item_id'] ?? null, $b['new_item_name'] ?? null,
             (float)($b['qty'] ?? 0), (string)($b['item_notes'] ?? '')]
        );
        ApprovalService::request('purchase_request', $requestId);
        Response::json(['ok' => true, 'message' => "تم إنشاء طلب الشراء $no وأرسل للموافقة"]);
    }

    public function purchaseOrders(): void
    {
        $orders = Database::query(
            "SELECT po.*, s.name AS supplier_name FROM purchase_orders po
             JOIN suppliers s ON s.id = po.supplier_id
             ORDER BY po.id DESC LIMIT 100"
        )->fetchAll();
        Response::json(['ok' => true, 'data' => $orders]);
    }

    public function goodsReceipts(): void
    {
        $rows = Database::query(
            "SELECT gr.*, po.po_no, w.name AS warehouse_name FROM goods_receipts gr
             JOIN purchase_orders po ON po.id = gr.po_id
             JOIN warehouses w ON w.id = gr.warehouse_id
             ORDER BY gr.id DESC LIMIT 100"
        )->fetchAll();
        Response::json(['ok' => true, 'data' => $rows]);
    }

    public function suppliers(): void
    {
        $rows = Database::query("SELECT id, name, phone, email, is_active FROM suppliers WHERE is_active=1 ORDER BY name")->fetchAll();
        Response::json(['ok' => true, 'data' => $rows]);
    }

    public function productionOrders(): void
    {
        $rows = Database::query(
            "SELECT p.*, i.name AS item_name FROM production_orders p
             JOIN items i ON i.id = p.item_id ORDER BY p.id DESC LIMIT 100"
        )->fetchAll();
        Response::json(['ok' => true, 'data' => $rows]);
    }

    public function storeProductionOrder(): void
    {
        $b = $this->body();
        $itemId = (int)($b['item_id'] ?? 0);
        $recipeId = (int)($b['recipe_id'] ?? 0);
        if (!$recipeId) {
            $recipe = Database::query("SELECT id FROM production_recipes WHERE output_item_id = ? LIMIT 1", [$itemId])->fetch();
            $recipeId = (int)($recipe['id'] ?? 0);
        }
        $no = Helpers::docNumber('PRD', 'production_orders', 'production_no');
        Database::query(
            "INSERT INTO production_orders (production_no, item_id, recipe_id, planned_qty, production_date, status, cost_center_id, created_by)
             VALUES (?,?,?,?,?,'planned',?,?)",
            [$no, $itemId, $recipeId ?: null, (float)($b['qty'] ?? 0), $b['date'] ?? date('Y-m-d'),
             (int)($b['cost_center_id'] ?? 0) ?: null, ApiAuth::id()]
        );
        $prodId = (int)Database::lastInsertId();
        if (!empty($b['auto_request_materials']) && $recipeId) {
            try {
                MaterialRequestService::createFromProductionOrder($prodId, ApiAuth::id());
            } catch (\Throwable $e) {
                // ignore: production order created without auto MR
            }
        }
        Response::json(['ok' => true, 'production_id' => $prodId, 'production_no' => $no]);
    }

    public function kitchenSections(): void
    {
        $rows = Database::query("SELECT id, name, is_active FROM kitchen_sections WHERE is_active=1 ORDER BY name")->fetchAll();
        Response::json(['ok' => true, 'data' => $rows]);
    }

    public function storeKitchenMaterialRequest(): void
    {
        $b = $this->body();
        $sectionId = (int)($b['kitchen_section_id'] ?? 0);
        $items = (array)($b['items'] ?? []);
        if ($sectionId <= 0 || !$items) {
            Response::json(['ok' => false, 'error' => 'يجب تحديد القسم والبنود'], 422);
            return;
        }
        try {
            $id = MaterialRequestService::createFromKitchenSection($sectionId, $items, ApiAuth::id());
            Response::json(['ok' => true, 'id' => $id]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function procurementAiSnapshot(): void
    {
        Response::json(['ok' => true, 'data' => ProcurementReportService::aiSnapshot()]);
    }



    // ===================== التقارير =====================

    public function reportSales(): void
    {
        [$from, $to] = $this->reportRange();
        $summary = Database::query(
            "SELECT COUNT(*) AS orders_count, COALESCE(SUM(total),0) AS total_sales,
                    COALESCE(AVG(total),0) AS avg_order
             FROM orders WHERE status <> 'cancelled' AND DATE(created_at) BETWEEN ? AND ?",
            [$from, $to]
        )->fetch();
        $byType = Database::query(
            "SELECT order_type, COUNT(*) AS cnt, COALESCE(SUM(total),0) AS total
             FROM orders WHERE status <> 'cancelled' AND DATE(created_at) BETWEEN ? AND ?
             GROUP BY order_type", [$from, $to]
        )->fetchAll();
        $daily = Database::query(
            "SELECT DATE(created_at) AS d, COALESCE(SUM(total),0) AS total
             FROM orders WHERE status <> 'cancelled' AND DATE(created_at) BETWEEN ? AND ?
             GROUP BY DATE(created_at) ORDER BY d", [$from, $to]
        )->fetchAll();
        Response::json(['ok' => true, 'data' => [
            'from' => $from, 'to' => $to, 'summary' => $summary, 'by_type' => $byType, 'daily' => $daily,
        ]]);
    }

    public function reportInventory(): void
    {
        $type = (string) ($_GET['type'] ?? 'all');
        $valid = ['raw', 'asset', 'tool', 'clothing', 'prepared', 'all'];
        if (!in_array($type, $valid, true)) {
            $type = 'all';
        }
        $where = $type === 'all' ? '' : "WHERE c.type = ?";
        $params = $type === 'all' ? [] : [$type];

        $rows = Database::query(
            "SELECT c.type AS category_type, COUNT(i.id) AS items_count,
                    COALESCE(SUM(s.qty),0) AS total_qty,
                    COALESCE(SUM(s.qty * i.cost_price),0) AS total_value
             FROM items i
             JOIN item_categories c ON c.id = i.category_id
             LEFT JOIN stock s ON s.item_id = i.id
             $where
             GROUP BY c.type
             ORDER BY c.type",
            $params
        )->fetchAll();
        Response::json(['ok' => true, 'data' => $rows]);
    }

    public function reportProfit(): void
    {
        [$from, $to] = $this->reportRange();
        $sales = Database::query(
            "SELECT COALESCE(SUM(total),0) AS t FROM orders
             WHERE status <> 'cancelled' AND DATE(created_at) BETWEEN ? AND ?", [$from, $to]
        )->fetch();
        $cogs = Database::query(
            "SELECT COALESCE(SUM(oi.qty * mi.cost),0) AS t
             FROM order_items oi
             JOIN menu_items mi ON mi.id = oi.menu_item_id
             JOIN orders o ON o.id = oi.order_id
             WHERE o.status <> 'cancelled' AND DATE(o.created_at) BETWEEN ? AND ?", [$from, $to]
        )->fetch();
        $expenses = Database::query(
            "SELECT COALESCE(SUM(amount),0) AS t FROM expenses
             WHERE status IN ('approved','paid') AND expense_date BETWEEN ? AND ?", [$from, $to]
        )->fetch();
        Response::json(['ok' => true, 'data' => [
            'from' => $from, 'to' => $to,
            'sales' => (float)$sales['t'], 'cogs' => (float)$cogs['t'], 'expenses' => (float)$expenses['t'],
        ]]);
    }

    public function reportHr(): void
    {
        $today = Database::query(
            "SELECT e.full_name, e.department,
                    MIN(CASE WHEN a.check_type='in' THEN a.checked_at END) AS first_in,
                    MAX(CASE WHEN a.check_type='out' THEN a.checked_at END) AS last_out
             FROM employees e
             LEFT JOIN attendance a ON a.employee_id = e.id AND DATE(a.checked_at) = CURDATE()
             WHERE e.file_status='open' GROUP BY e.id ORDER BY e.full_name"
        )->fetchAll();
        Response::json(['ok' => true, 'data' => $today]);
    }

    private function reportRange(): array
    {
        $b = $this->body();
        $from = (string)($_GET['from'] ?? $b['from'] ?? date('Y-m-01'));
        $to   = (string)($_GET['to'] ?? $b['to'] ?? date('Y-m-d'));
        return [$from, $to];
    }

    // ===================== المهام =====================

    public function tasks(): void
    {
        $user = ApiAuth::user();
        $emp = Database::query("SELECT id FROM employees WHERE user_id = ?", [ApiAuth::id()])->fetch();
        $empId = (int)($emp['id'] ?? 0);
        $sql = "SELECT t.*, e.full_name AS assignee, cc.name AS cost_center_name, b.name AS branch_name
                FROM tasks t
                JOIN employees e ON e.id = t.assigned_to
                LEFT JOIN cost_centers cc ON cc.id = t.cost_center_id
                LEFT JOIN branches b ON b.id = t.branch_id
                WHERE 1=1 ";
        $params = [];
        if (!Auth::can('hr.manage') && !Auth::can('mobile.tasks')) {
            $sql .= " AND t.assigned_to = ?";
            $params[] = $empId;
        }
        $sql .= " ORDER BY FIELD(t.status,'open','in_progress','done','cancelled'), t.priority DESC, t.due_date ASC LIMIT 200";
        $tasks = Database::query($sql, $params)->fetchAll();
        Response::json(['ok' => true, 'data' => $tasks]);
    }

    public function storeTask(): void
    {
        $b = $this->body();
        Database::query(
            "INSERT INTO tasks (title, description, assigned_to, due_date, priority, cost_center_id, branch_id, status, created_by)
             VALUES (?,?,?,?,?,?,?,?,?)",
            [(string)($b['title'] ?? ''), (string)($b['description'] ?? ''),
             (int)($b['assigned_to'] ?? 0), $b['due_date'] ?? null,
             (string)($b['priority'] ?? 'normal'), (int)($b['cost_center_id'] ?? 0) ?: null,
             (int)($b['branch_id'] ?? 0) ?: null, 'open', ApiAuth::id()]
        );
        $taskId = (int)Database::lastInsertId();
        $assignee = Database::query("SELECT user_id FROM employees WHERE id = ?", [(int)($b['assigned_to'] ?? 0)])->fetch();
        if ($assignee && $assignee['user_id']) {
            NotificationService::sendToUser(
                (int)$assignee['user_id'], 'مهمة جديدة', (string)($b['title'] ?? ''), '/mobile/tasks', ['push', 'email']
            );
        }
        Response::json(['ok' => true, 'message' => 'تم إسناد المهمة', 'task_id' => $taskId]);
    }

    public function updateTaskStatus(string $id): void
    {
        $b = $this->body();
        $status = (string)($b['status'] ?? 'open');
        if (!in_array($status, ['open', 'in_progress', 'done', 'cancelled'], true)) {
            Response::json(['ok' => false, 'error' => 'حالة غير صالحة'], 422);
            return;
        }
        Database::query(
            "UPDATE tasks SET status = ?, status_updated_at = NOW(), status_updated_by = ? WHERE id = ?",
            [$status, ApiAuth::id(), (int)$id]
        );
        $task = Database::query(
            "SELECT t.*, e.user_id AS assignee_user_id, c.user_id AS creator_user_id
             FROM tasks t
             LEFT JOIN employees e ON e.id = t.assigned_to
             LEFT JOIN users c ON c.id = t.created_by
             WHERE t.id = ?",
            [(int)$id]
        )->fetch();
        if ($task) {
            $title = 'تحديث مهمة';
            $body = 'تم تحديث حالة المهمة "' . ($task['title'] ?? '') . '" إلى ' . $status;
            if ($task['assignee_user_id'] && (int)$task['assignee_user_id'] !== ApiAuth::id()) {
                NotificationService::sendToUser((int)$task['assignee_user_id'], $title, $body, '/mobile/tasks', ['push', 'email']);
            }
            if ($task['creator_user_id'] && (int)$task['creator_user_id'] !== ApiAuth::id()) {
                NotificationService::sendToUser((int)$task['creator_user_id'], $title, $body, '/mobile/tasks', ['push', 'email']);
            }
        }
        Response::json(['ok' => true]);
    }

    // ===================== العهدة / الأصول =====================

    public function custody(): void
    {
        $custody = Database::query(
            "SELECT c.*, i.name AS item_name, e.full_name AS employee_name
             FROM custody c
             JOIN items i ON i.id = c.item_id
             LEFT JOIN employees e ON e.id = c.employee_id
             ORDER BY c.id DESC LIMIT 200"
        )->fetchAll();
        Response::json(['ok' => true, 'data' => $custody]);
    }

    public function storeCustody(): void
    {
        $b = $this->body();
        $itemId = (int)($b['item_id'] ?? 0);
        $qty    = (float)($b['qty'] ?? 1);
        $type   = (string)($b['custodian_type'] ?? 'employee');

        Database::beginTransaction();
        try {
            Database::query(
                "INSERT INTO custody (item_id, qty, custodian_type, employee_id, department, expiry_date, notes, created_by)
                 VALUES (?,?,?,?,?,?,?,?)",
                [
                    $itemId, $qty, $type,
                    $type === 'employee' ? (int)($b['employee_id'] ?? 0) : null,
                    $type === 'department' ? (string)($b['department'] ?? '') : null,
                    $b['expiry_date'] ?? null, (string)($b['notes'] ?? ''), ApiAuth::id(),
                ]
            );
            StockService::move(1, $itemId, 'out', $qty, 0, 'CUSTODY', 'تسليم عهدة');
            Database::commit();
            Response::json(['ok' => true, 'message' => 'تم تسليم العهدة وخصمها من المخزن']);
        } catch (\Throwable $e) {
            Database::rollBack();
            Response::json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function issueCustody(): void
    {
        $b = $this->body();
        $item = $this->resolveApiItem($b['barcode'] ?? null, $b['item_id'] ?? null);
        if (!$item) {
            Response::json(['ok' => false, 'error' => 'الباركود أو المادة غير معروفة'], 404);
            return;
        }
        $itemId = (int) $item['id'];
        $categoryType = (string) $item['category_type'];
        $unitId = (int) ($b['unit_id'] ?? $item['unit_id']);
        $qty = (float) ($b['qty'] ?? 1);
        $warehouseId = (int) ($b['warehouse_id'] ?? 1);
        $type = (string) ($b['custodian_type'] ?? 'employee');

        if (!in_array($categoryType, ['asset', 'tool', 'clothing'], true)) {
            Response::json(['ok' => false, 'error' => 'العهدة مقتصرة على الموجودات والأدوات والملابس'], 422);
            return;
        }
        if ($categoryType === 'clothing' && $type !== 'employee') {
            Response::json(['ok' => false, 'error' => 'الملابس فقط عهدة شخصية للموظف'], 422);
            return;
        }
        if ($type === 'employee' && (int) ($b['employee_id'] ?? 0) <= 0) {
            Response::json(['ok' => false, 'error' => 'يجب اختيار الموظف'], 422);
            return;
        }

        Database::beginTransaction();
        try {
            $baseQty = UnitConversionService::toBaseUnit($unitId, $qty);
            $balance = StockService::balance($warehouseId, $itemId);
            if ($balance < $baseQty - 0.0001) {
                Response::json(['ok' => false, 'error' => 'الكمية في المخزن غير كافية (متوفر: ' . $balance . ')'], 422);
                return;
            }
            Database::query(
                "INSERT INTO custody (item_id, qty, unit_id, warehouse_id, custodian_type, employee_id, department, expiry_date, notes, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?)",
                [
                    $itemId, $baseQty, $unitId, $warehouseId, $type,
                    $type === 'employee' ? (int) ($b['employee_id'] ?? 0) : null,
                    $type === 'department' ? (string) ($b['department'] ?? '') : null,
                    $b['expiry_date'] ?? null, (string) ($b['notes'] ?? ''), ApiAuth::id(),
                ]
            );
            StockService::moveWithUnit($warehouseId, $itemId, 'out', $qty, $unitId, 0, 'CUSTODY', 'تسليم عهدة');
            Database::commit();
            Response::json(['ok' => true]);
        } catch (\Throwable $e) {
            Database::rollBack();
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function returnCustody(string $id): void
    {
        $b = $this->body();
        $c = Database::query("SELECT * FROM custody WHERE id = ?", [(int)$id])->fetch();
        if (!$c || $c['status'] !== 'active') {
            Response::json(['ok' => false, 'error' => 'العهدة غير موجودة أو تم إرجاعها مسبقاً'], 404);
            return;
        }
        $newStatus = (string)($b['status'] ?? 'returned');
        if (!in_array($newStatus, ['returned', 'damaged', 'lost'], true)) { $newStatus = 'returned'; }
        Database::query("UPDATE custody SET status = ?, returned_at = NOW() WHERE id = ?", [$newStatus, (int)$id]);
        if ($newStatus === 'returned') {
            $warehouseId = (int) ($c['warehouse_id'] ?? 1);
            StockService::move($warehouseId, (int)$c['item_id'], 'return', (float)$c['qty'], 0, 'CUSTODY-RET', 'إرجاع عهدة');
        }
        Response::json(['ok' => true, 'message' => 'تم تحديث حالة العهدة']);
    }

    // ===================== سير العمل والموافقات (Workflow) =====================

    /** Accountant/cash desk operations share the same transaction implementation as web. */
    public function advanceDisbursements(): void
    {
        if (!Auth::can('accounting.manage') && !Auth::can('payroll.manage')) {
            Response::json(['ok'=>false,'error'=>'ليس لديك صلاحية صرف السلف'],403); return;
        }
        $page = max(1, (int)($_GET['page'] ?? 1)); $size = 30; $offset = ($page-1)*$size;
        $rows = Database::query("SELECT a.id,a.amount,a.advance_type,a.status,a.accountant_approved_at,a.paid_at,a.payment_method,
            e.full_name,e.emp_code,l.request_id
            FROM salary_advances a JOIN employees e ON e.id=a.employee_id
            JOIN hr_workflow_links l ON l.entity_type='salary_advance' AND l.entity_id=a.id
            WHERE a.status IN ('approved','paid','settled') ORDER BY a.id DESC LIMIT $size OFFSET $offset")->fetchAll();
        foreach ($rows as &$row) {
            $row['can_authorize'] = $row['status']==='approved' && empty($row['accountant_approved_at']) && Auth::can('accounting.manage');
            $row['can_pay'] = $row['status']==='approved' && !empty($row['accountant_approved_at']) && empty($row['paid_at']);
        } unset($row);
        Response::json(['ok'=>true,'data'=>['advances'=>$rows,'page'=>$page,'has_more'=>count($rows)===$size,
            'cash_registers'=>Database::query('SELECT id,name FROM cash_registers WHERE is_active=1 ORDER BY name')->fetchAll(),
            'bank_accounts'=>Database::query('SELECT id,name FROM bank_accounts WHERE is_active=1 ORDER BY name')->fetchAll()]]);
    }

    public function advanceReceipt(string $id): void
    {
        if (!Auth::can('accounting.manage') && !Auth::can('payroll.manage')) {
            Response::json(['ok'=>false,'error'=>'ليس لديك صلاحية عرض سند الصرف'],403); return;
        }
        $receipt=Database::query("SELECT a.id,a.amount,a.reason,a.paid_at,a.payment_method,
            e.full_name,e.emp_code,u.full_name AS paid_by_name FROM salary_advances a
            JOIN employees e ON e.id=a.employee_id LEFT JOIN users u ON u.id=a.paid_by
            WHERE a.id=? AND a.status IN ('paid','settled') AND a.paid_at IS NOT NULL AND a.paid_by IS NOT NULL",[(int)$id])->fetch();
        if (!$receipt) { Response::json(['ok'=>false,'error'=>'السند متاح بعد التسليم الفعلي فقط'],404); return; }
        Response::json(['ok'=>true,'data'=>$receipt]);
    }

    public function advanceAuthorize(string $id): void
    {
        (new HrController())->approveAdvanceForPayment($id, true);
    }

    public function advancePay(string $id): void
    {
        if (!Auth::can('accounting.manage') && !Auth::can('payroll.manage')) {
            Response::json(['ok'=>false,'error'=>'ليس لديك صلاحية صرف السلف'],403); return;
        }
        $body = $this->body();
        $method = (string)($body['payment_method'] ?? '');
        $accountId = (int)($body[$method === 'bank' ? 'bank_account_id' : 'cash_register_id'] ?? 0);
        if (!in_array($method,['cash','bank'],true) || $accountId < 1) {
            Response::json(['ok'=>false,'error'=>'اختر طريقة الدفع والصندوق أو الحساب'],422); return;
        }
        $table = $method === 'bank' ? 'bank_accounts' : 'cash_registers';
        if (!Database::query("SELECT id FROM $table WHERE id=? AND is_active=1",[$accountId])->fetchColumn()) {
            Response::json(['ok'=>false,'error'=>'الحساب المختار غير متاح'],422); return;
        }
        $previousPost = $_POST;
        $_POST = array_intersect_key($body, array_flip(['payment_method','bank_account_id','cash_register_id']));
        try { (new HrController())->payAdvance($id, true); } finally { $_POST = $previousPost; }
    }

    public function workflowTemplates(): void
    {
        $user = ApiAuth::user();
        $roleId = (int)$user['role_id'];
        // القوالب التي يملك المستخدم صلاحية إنشائها (من template_allowed_roles + الأدوار الإدارية/الصلاحيات)
        $allowedTemplates = WorkflowTemplateService::getTemplatesForUser($roleId);
        $allowedIds = array_flip(array_map('intval', array_column($allowedTemplates, 'id')));

        // جميع القوالب النشطة؛ الإجازة (hr_leave) والسلفة (hr_advance) تظهران دائماً لجميع
        // الموظفين (حق ذاتي Self-Service)؛ بقية القوالب بما فيها نسيان البصمة
        // (hr_missing_attendance) تظهر فقط إذا كان المستخدم يملك صلاحية إنشائها صراحةً
        // (عبر شاشة "تعديل الدور")، ليكون ظهور أي نوع طلب مرتبطاً بالصلاحيات فعلياً.
        $alwaysVisibleCodes = ['hr_leave', 'hr_advance'];
        $allActive = Database::query("SELECT * FROM approval_templates WHERE is_active = 1 ORDER BY name")->fetchAll();
        $templates = [];
        foreach ($allActive as $t) {
            $code = $t['code'] ?? '';
            $id = (int)$t['id'];
            if (in_array($code, ['hr_exceptional_advance','hr_long_advance'], true) && !\App\Core\AdvancePolicyService::canManageHr((int)$user['id'])) continue;
            if ($code === 'hr_advance' && !WorkflowEngine::getUserWithPosition((int)$user['id'])['employee_id']) continue;
            if (in_array($code, $alwaysVisibleCodes, true) || isset($allowedIds[$id])) {
                $templates[] = $t;
            }
        }

        // بعض القوالب تُخفى من تطبيق الموبايل وتبقى متاحة في الويب فقط
        $excludedMobileCodes = ['hr_adjustment', 'reservation_approval'];
        $templates = array_values(array_filter($templates, function ($t) use ($excludedMobileCodes) {
            return !in_array($t['code'] ?? '', $excludedMobileCodes, true);
        }));
        Response::json(['ok' => true, 'data' => $templates]);
    }

    /** تفاصيل قالب موافقة (الحقول + قائمة الموظفين) لإنشاء الطلب من الموبايل */
    public function workflowTemplateDetail(string $id): void
    {
        $user = ApiAuth::user();
        $roleId = (int)($user['role_id'] ?? 0);
        $template = WorkflowTemplateService::getTemplate((int)$id);
        if (!$template || empty($template['is_active'])) {
            Response::json(['ok' => false, 'error' => 'القالب غير موجود'], 404);
            return;
        }
        // بعض القوالب تُخفى من تطبيق الموبايل وتبقى متاحة في الويب فقط
        $excludedMobileCodes = ['hr_adjustment', 'reservation_approval'];
        if (in_array($template['code'] ?? '', $excludedMobileCodes, true)) {
            Response::json(['ok' => false, 'error' => 'القالب غير متاح في التطبيق'], 404);
            return;
        }
        $code = $template['code'] ?? '';
        if (in_array($code, ['hr_exceptional_advance','hr_long_advance'], true) && !\App\Core\AdvancePolicyService::canManageHr((int)$user['id'])) {
            Response::json(['ok'=>false,'error'=>'تقديم هذا الطلب للموارد البشرية فقط'],403); return;
        }
        // الإجازة (hr_leave) والسلفة (hr_advance) ونسيان البصمة (hr_missing_attendance) متاحة دائمًا للموظفين في الموبايل
        $alwaysCreatable = in_array($code, ['hr_leave', 'hr_advance', 'hr_missing_attendance'], true);
        if (!$alwaysCreatable && !WorkflowEngine::canCreate((int)$id, $roleId)) {
            Response::json(['ok' => false, 'error' => 'ليس لديك صلاحية إنشاء هذا الطلب'], 403);
            return;
        }
        if ($template['code'] === 'deduction_request' || $template['code'] === 'suspension_request') {
            $template['employees'] = WorkflowEngine::subordinateEmployees((int)$user['id']);
        } elseif ($code === 'hr_advance') {
            $template['employees'] = Database::query("SELECT id,full_name,emp_code FROM employees WHERE user_id=? AND status='active'", [(int)$user['id']])->fetchAll();
            if (!$template['employees']) { Response::json(['ok'=>false,'error'=>'الحساب غير مرتبط بموظف نشط'],403); return; }
        } else {
            $template['employees'] = Database::query(
                "SELECT id, full_name, emp_code FROM employees WHERE status = 'active' ORDER BY full_name"
            )->fetchAll();
        }
        if ($template['code'] === 'hiring_request' || $template['code'] === 'purchase_request') {
            $template['departments'] = Database::query(
                "SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name"
            )->fetchAll();
        }
        if ($template['code'] === 'hr_advance') {
            $template['advance_limit'] = WorkflowEngine::advanceLimitForUser((int)$user['id']);
        }
        Response::json(['ok' => true, 'data' => $template]);
    }

    /** حد السلفة المسموح لموظف محدد (للسلفة الاستثنائية) */
    public function employeeAdvanceLimit(string $id): void
    {
        $employeeId = (int)$id;
        if (!$employeeId) {
            Response::json(['ok' => false, 'error' => 'معرّف الموظف مطلوب'], 422);
            return;
        }
        if (!\App\Core\AdvancePolicyService::canViewFinancial($employeeId, (int)ApiAuth::id())) {
            Response::json(['ok'=>false,'error'=>'ليس لديك صلاحية عرض استحقاق هذا الموظف'],403); return;
        }
        $employee = Database::query("SELECT id FROM employees WHERE id = ?", [$employeeId])->fetch();
        if (!$employee) {
            Response::json(['ok' => false, 'error' => 'الموظف غير موجود'], 404);
            return;
        }
        Response::json(['ok' => true, 'data' => WorkflowEngine::advanceLimitForEmployee($employeeId)]);
    }

    public function workflowStoreRequest(): void
    {
        $b = $this->body();
        $user = ApiAuth::user();
        $_SESSION['user_id'] = (int)$user['id'];
        $userId = (int)$user['id'];
        $templateId = (int)($b['template_id'] ?? 0);
        if (!$templateId) {
            Response::json(['ok' => false, 'error' => 'template_id مطلوب'], 422);
            return;
        }
        $template = WorkflowTemplateService::getTemplate($templateId);
        if (!$template) {
            Response::json(['ok' => false, 'error' => 'القالب غير موجود'], 404);
            return;
        }
        $data = array_merge((array)($b['data'] ?? []), (array)($b['fields'] ?? []));
        // Older mobile versions used the exceptional template for both types.
        if (($template['code'] ?? '') === 'hr_exceptional_advance' && in_array($data['advance_type'] ?? '', ['long','طويلة الأجل'], true)) {
            $target = Database::query("SELECT id FROM approval_templates WHERE code='hr_long_advance' AND is_active=1")->fetchColumn();
            if (!$target) { Response::json(['ok'=>false,'error'=>'قالب السلفة طويلة الأجل غير متاح'],422); return; }
            $templateId = (int)$target;
            $template = WorkflowTemplateService::getTemplate($templateId);
        }
        if (empty($template['is_active'])) { Response::json(['ok'=>false,'error'=>'القالب غير متاح'],403); return; }
        $data['title'] = (string)($b['title'] ?? ($template['name'] . ' - ' . date('Y-m-d')));

        foreach ($template['fields'] as $f) {
            $name = $f['name'];
            $type = $f['type'] ?? 'text';
            if ($type === 'file' && !empty($data[$name]) && is_string($data[$name]) && str_starts_with($data[$name], 'data:')) {
                $saved = $this->saveBase64File($data[$name], $name);
                if ($saved) $data[$name] = $saved;
            }
        }

        $adjId = null;
        if (($template['code'] ?? '') === 'deduction_request') {
            $adjId = HrService::createAdjustmentFromWorkflowData($data, $userId, 'deduction');
            if (!$adjId) {
                Response::json(['ok' => false, 'error' => 'بيانات الخصم غير صالحة: يجب تحديد موظف ومبلغ'], 422);
                return;
            }
            $data['entity_id'] = $adjId;
            $data['entity_type'] = 'hr_adjustment';
        }

        $maId = null;
        if (($template['code'] ?? '') === 'hr_missing_attendance') {
            $empId = (int)($data['employee_id'] ?? 0);
            if (!$empId) {
                $up = WorkflowEngine::getUserWithPosition($userId) ?: [];
                $empId = (int)($up['employee_id'] ?? 0);
            }
            if (!$empId) {
                Response::json(['ok' => false, 'error' => 'بيانات طلب نسيان البصمة غير صالحة: يجب تحديد موظف'], 422);
                return;
            }
            if (empty($data['employee_name'])) {
                $empRow = Database::query("SELECT full_name FROM employees WHERE id = ?", [$empId])->fetch();
                $data['employee_name'] = $empRow['full_name'] ?? '';
            }
            try {
                $maId = HrService::createMissingAttendanceRequest($empId, [
                    'request_type' => (string)($data['request_type'] ?? 'in'),
                    'clock_in_at' => ($data['clock_in_at'] ?? '') ?: null,
                    'clock_out_at' => ($data['clock_out_at'] ?? '') ?: null,
                    'reason' => (string)($data['reason'] ?? ''),
                ]);
            } catch (\Throwable $e) {
                Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
                return;
            }
            $data['entity_id'] = $maId;
            $data['entity_type'] = 'missing_attendance_request';
        }

        try {
            $id = WorkflowEngine::createRequest($templateId, $userId, $data);
            WorkflowEngine::submitRequest($id, $userId);

            if ($adjId) {
                EntityRegistry::bind('hr_adjustment', $adjId, $id);
            }
            if ($maId) {
                EntityRegistry::bind('missing_attendance_request', $maId, $id);
                Database::query(
                    "UPDATE missing_attendance_requests SET workflow_request_id = ? WHERE id = ?",
                    [$id, $maId]
                );
            }

            // لطلبات الشراء: إنشاء سجل purchase_requests مع بنوده وربطه بـ workflow
            if (($template['code'] ?? '') === 'purchase_request') {
                $this->createPurchaseRequestFromWorkflow($id, $data, $userId);
            }

            $row = Database::query("SELECT request_no FROM requests WHERE id = ?", [$id])->fetch();
            Response::json(['ok' => true, 'id' => $id, 'request_no' => $row['request_no'] ?? null, 'message' => 'تم إرسال الطلب']);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    private function createPurchaseRequestFromWorkflow(int $requestId, array $data, int $userId): void
    {
        $items = $data['items'] ?? [];
        if (!is_array($items) || empty($items)) {
            throw new \RuntimeException('يجب إضافة صنف واحد على الأقل في طلب الشراء');
        }
        $departmentId = (int)($data['department_id'] ?? 0);
        $departmentName = $departmentId
            ? Database::query("SELECT name FROM departments WHERE id = ?", [$departmentId])->fetchColumn()
            : '';
        $no = Helpers::docNumber('PRQ', 'purchase_requests', 'request_no');
        $reason = (string)($data['reason'] ?? '');
        $attachment = (string)($data['attachment'] ?? '');
        Database::query(
            "INSERT INTO purchase_requests (request_no, department, notes, requested_by, workflow_request_id) VALUES (?,?,?,?,?)",
            [$no, (string)$departmentName, $reason, $userId, $requestId]
        );
        $prId = (int)Database::lastInsertId();
        foreach ($items as $it) {
            $itemId = (int)($it['item_id'] ?? 0);
            $qty = (float)($it['qty'] ?? 0);
            $notes = (string)($it['reason'] ?? '');
            if ($itemId <= 0 || $qty <= 0) continue;
            $itemName = Database::query("SELECT name FROM items WHERE id = ?", [$itemId])->fetchColumn();
            Database::query(
                "INSERT INTO purchase_request_items (request_id, item_id, new_item_name, qty, notes) VALUES (?,?,?,?,?)",
                [$prId, $itemId, $itemName, $qty, $notes]
            );
        }
        Database::query(
            "UPDATE requests SET amount = 0, data_json = ? WHERE id = ?",
            [json_encode(array_merge($data, ['purchase_request_id' => $prId, 'attachment' => $attachment, 'pr_no' => $no, 'department' => $departmentName]), JSON_UNESCAPED_UNICODE), $requestId]
        );
    }

    public function workflowMyRequests(): void
    {
        Response::json(['ok' => true, 'data' => WorkflowEngine::getMyRequests(ApiAuth::id())]);
    }

    public function workflowPending(): void
    {
        Response::json(['ok' => true, 'data' => WorkflowEngine::getPendingForUser(ApiAuth::id())]);
    }

    /** صندوق وارد المعتمد: عدادات + قائمة مرتبة (العاجل أولاً) */
    public function workflowInbox(): void
    {
        Response::json(['ok' => true, 'data' => \App\Core\Workflow\InboxService::forUser(ApiAuth::id())]);
    }

    /** تحويل مهمة معلقة لمستخدم آخر (تفويض لمرة واحدة) */
    public function workflowDelegate(string $id): void
    {
        $b = $this->body();
        $user = ApiAuth::user();
        $_SESSION['user_id'] = (int)$user['id'];
        $toId = (int)($b['to_user_id'] ?? 0);
        if (!$toId) { Response::json(['ok' => false, 'error' => 'to_user_id مطلوب'], 422); return; }
        $target = Database::query("SELECT id, full_name FROM users WHERE id = ?", [$toId])->fetch();
        if (!$target) { Response::json(['ok' => false, 'error' => 'المستخدم المستهدف غير موجود'], 404); return; }
        try {
            \App\Core\Workflow\Engine::delegateTask((int)$id, (int)$user['id'], $toId, (string)($b['notes'] ?? ''));
            Response::json(['ok' => true, 'message' => 'حُوّلت المهمة إلى ' . $target['full_name']]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** تسجيل اشتراك Push */
    public function pushSubscribe(): void
    {
        try {
            \App\Core\Workflow\PushService::subscribe(ApiAuth::id(), $this->body());
            Response::json(['ok' => true, 'message' => 'تم تسجيل الاشتراك']);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** إلغاء اشتراك Push */
    public function pushUnsubscribe(): void
    {
        \App\Core\Workflow\PushService::unsubscribe(ApiAuth::id(), (string)($this->body()['endpoint'] ?? ''));
        Response::json(['ok' => true, 'message' => 'تم إلغاء الاشتراك']);
    }

    // ===== PTT — الموبايل =====
    public function pttChannels(): void
    {
        Response::json(['ok' => true, 'data' => \App\Core\Ptt\PttService::channelsForUser(ApiAuth::id())]);
    }

    public function pttInbox(): void
    {
        $uid = ApiAuth::id();
        \App\Core\Ptt\PttService::markDeliveredOnFetch($uid);
        Response::json(['ok' => true, 'data' => [
            'inbox' => \App\Core\Ptt\PttService::inbox($uid),
            'unplayed' => \App\Core\Ptt\PttService::unplayedCounts($uid),
        ]]);
    }

    public function pttChannelMessages(string $id): void
    {
        $uid = ApiAuth::id();
        if (!\App\Core\Ptt\PttService::membership((int)$id, $uid)) {
            Response::json(['ok' => false, 'error' => 'لست عضواً في هذه القناة'], 403);
            return;
        }
        $channel = \App\Core\Ptt\PttService::channel((int)$id);
        $member = \App\Core\Ptt\PttService::membership((int)$id, $uid) ?: [];
        $canLivePublish = !empty($member['can_broadcast_live']) || ($member['role'] ?? '') === 'supervisor'
            || (Auth::can('ptt.broadcast_live') && \App\Core\Ptt\PttService::canSend((int)$id, $uid));
        Response::json(['ok' => true, 'data' => [
            'channel' => $channel,
            'messages' => \App\Core\Ptt\PttService::channelHistory((int)$id),
            'canSend' => \App\Core\Ptt\PttService::canSend((int)$id, $uid),
            'canEmergency' => Auth::can('ptt.emergency'),
            'priorities' => array_keys(\App\Core\Ptt\PttService::PRIORITIES),
            'maxDuration' => \App\Core\Ptt\PttService::maxDuration($channel),
            'liveConfigured' => \App\Core\Ptt\LiveKitService::isConfigured(),
            'canLivePublish' => $canLivePublish,
            'activeLive' => \App\Core\Ptt\LiveKitService::activeSession((int)$id),
        ]]);
    }

    public function pttLiveToken(string $id): void
    {
        $uid = ApiAuth::id();
        if (!\App\Core\Ptt\LiveKitService::isConfigured()) {
            Response::json(['ok' => false, 'error' => 'لم يُضبط LiveKit — راجع الإدارة'], 503);
            return;
        }
        $channel = \App\Core\Ptt\PttService::channel((int)$id);
        if (!$channel) { Response::json(['ok' => false, 'error' => 'القناة غير موجودة'], 404); return; }
        $member = \App\Core\Ptt\PttService::membership((int)$id, $uid);
        if (!$member) { Response::json(['ok' => false, 'error' => 'لست عضواً في هذه القناة'], 403); return; }

        $body = $this->body();
        $wantPublish = !empty($body['publish']);
        $canPublish = $wantPublish && (
            !empty($member['can_broadcast_live']) || $member['role'] === 'supervisor'
            || (Auth::can('ptt.broadcast_live') && \App\Core\Ptt\PttService::canSend((int)$id, $uid))
        );
        if ($wantPublish && !$canPublish) {
            Response::json(['ok' => false, 'error' => 'لا تملك صلاحية البث المباشر في هذه القناة'], 403);
            return;
        }

        if ($canPublish && !\App\Core\Ptt\PttService::isServiceActive()) {
            Response::json(['ok' => false, 'error' => 'خدمة PTT متوقفة جزئياً حالياً من قبل الإدارة — لا يمكن بدء بث جديد'], 423);
            return;
        }
        $user = ApiAuth::user();
        $name = (string)($user['full_name'] ?? 'user' . $uid);
        $token = \App\Core\Ptt\LiveKitService::token((int)$id, $uid, $name, $canPublish);
        $sessionId = null;
        if ($canPublish) {
            $sessionId = \App\Core\Ptt\LiveKitService::startSession((int)$id, $uid);
        }
        Response::json(['ok' => true, 'data' => [
            'token' => $token,
            'url' => \App\Core\Ptt\LiveKitService::wsUrl(),
            'room' => \App\Core\Ptt\LiveKitService::roomName((int)$id),
            'can_publish' => $canPublish,
            'session_id' => $sessionId,
        ]]);
    }

    public function pttLiveStop(string $id): void
    {
        \App\Core\Ptt\LiveKitService::endSession((int)$id);
        Response::json(['ok' => true]);
    }

    public function pttSend(): void
    {
        try {
            $audio = \App\Core\Ptt\PttService::saveAudio($_FILES['audio'] ?? []);
            $input = $_POST;
            $input['allow_emergency'] = \App\Core\Auth::can('ptt.emergency');
            $id = \App\Core\Ptt\PttService::sendMessage(ApiAuth::id(), $input, $audio);
            Response::json(['ok' => true, 'message_id' => $id]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function pttPlayed(string $id): void
    {
        Response::json(['ok' => \App\Core\Ptt\PttService::markPlayed((int)$id, ApiAuth::id())]);
    }

    public function pttAck(string $id): void
    {
        Response::json(['ok' => \App\Core\Ptt\PttService::acknowledge((int)$id, ApiAuth::id())]);
    }

    public function workflowShowRequest(string $id): void
    {
        $req = WorkflowEngine::getRequest((int)$id);
        if (!$req) { Response::json(['ok' => false, 'error' => 'الطلب غير موجود'], 404); return; }
        $canDecide = false;
        if ($req['status'] === 'pending') {
            if (!empty($req['workflow_version_id'])) {
                $canDecide = \App\Core\Workflow\Engine::userHasPendingTask((int)$id, (int)ApiAuth::id());
            } else {
                foreach (WorkflowEngine::resolveSteps((int)$req['template_id'], (int)$req['current_step'], (int)$id) as $step) {
                    if (WorkflowEngine::userCanDecideStep($step, $req)) { $canDecide = true; break; }
                }
            }
        }
        $isAdvance = \App\Core\AdvancePolicyService::isAdvance($req['template_code']);
        if (!$isAdvance && !Auth::canAny(['workflow.create','workflow.decide','approvals.decide','mobile.approvals'])) {
            Response::json(['ok'=>false,'error'=>'ليس لديك صلاحية عرض هذا الطلب'],403); return;
        }
        if ($isAdvance && (int)$req['requester_id'] !== (int)ApiAuth::id() && !$canDecide
            && !\App\Core\AdvancePolicyService::canViewFinancial((int)$req['requester_employee_id'], (int)ApiAuth::id())) {
            Response::json(['ok'=>false,'error'=>'ليس لديك صلاحية عرض هذا الطلب'],403); return;
        }
        if ($req['template_code'] === 'deduction_request' || $req['template_code'] === 'suspension_request') {
            $req['employees'] = WorkflowEngine::subordinateEmployees(ApiAuth::id());
        } elseif ($isAdvance) {
            $req['employees'] = Database::query('SELECT id,full_name,emp_code FROM employees WHERE id=?', [(int)$req['requester_employee_id']])->fetchAll();
        } else {
            $req['employees'] = Database::query(
                "SELECT id, full_name, emp_code FROM employees WHERE status = 'active' ORDER BY full_name"
            )->fetchAll();
        }
        if ($req['template_code'] === 'hiring_request') {
            $req['departments'] = Database::query(
                "SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name"
            )->fetchAll();
        }
        if (\App\Core\AdvancePolicyService::isAdvance($req['template_code'])) {
            $req['advance_payment'] = Database::query("SELECT a.status,a.paid_at,a.payment_method,a.accountant_approved_at FROM salary_advances a
                JOIN hr_workflow_links l ON l.entity_type='salary_advance' AND l.entity_id=a.id WHERE l.request_id=?",[(int)$id])->fetch() ?: null;
            $req['advance_limit'] = \App\Core\AdvancePolicyService::snapshot((int)$req['requester_employee_id'], (int)$req['id']);
        }
        // إثراء بيانات مقدم الطلب (القسم والمسمى الوظيفي) لشاشة التفاصيل
        if (!empty($req['requester_employee_id'])) {
            $emp = Database::query(
                "SELECT e.*, d.name AS department_name, j.title AS job_title, m.full_name AS manager_name
                 FROM employees e
                 LEFT JOIN departments d ON d.id = e.department_id
                 LEFT JOIN jobs j ON j.id = e.job_id
                 LEFT JOIN employees m ON m.id = e.manager_id
                 WHERE e.id = ?",
                [(int)$req['requester_employee_id']]
            )->fetch();
            if ($emp) {
                $req['requester_department'] = $emp['department_name'] ?? '';
                $req['requester_job_title'] = $emp['job_title'] ?? $emp['position'] ?? '';
                $req['requester_manager'] = $emp['manager_name'] ?? '';
            }
        }
        // للطلبات المرتبطة بـ purchase_requests: إرفاق بنود الشراء والمرفقات
        if ($req['template_code'] === 'purchase_request' && !empty($req['data']['purchase_request_id'])) {
            $prId = (int)$req['data']['purchase_request_id'];
            $pr = Database::query("SELECT * FROM purchase_requests WHERE id = ?", [$prId])->fetch();
            $prItems = Database::query(
                "SELECT pri.*, i.name AS item_name, i.unit_id, u.name AS unit_name
                 FROM purchase_request_items pri
                 LEFT JOIN items i ON i.id = pri.item_id
                 LEFT JOIN units u ON u.id = i.unit_id
                 WHERE pri.request_id = ?",
                [$prId]
            )->fetchAll();
            $req['purchase_request'] = $pr ?: null;
            $req['purchase_request_items'] = $prItems;
        }
        Response::json(['ok' => true, 'data' => [
            'request' => $req,
            'history' => WorkflowEngine::getHistory((int)$id),
            'can_decide' => $canDecide,
            'can_edit' => WorkflowEngine::canEditRequest((int)$id, ApiAuth::id()),
        ]]);
    }

    public function workflowDecide(string $id): void
    {
        $b = $this->body();
        $user = ApiAuth::user();
        $_SESSION['user_id'] = (int)$user['id'];
        try {
            WorkflowEngine::decide((int)$id, (string)($b['decision'] ?? ''), (string)($b['notes'] ?? ''), (int)$user['id']);
            Response::json(['ok' => true, 'message' => 'تم تسجيل القرار']);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function workflowResubmit(string $id): void
    {
        $b = $this->body();
        $user = ApiAuth::user();
        $_SESSION['user_id'] = (int)$user['id'];
        try {
            WorkflowEngine::resubmit((int)$id, (array)($b['data'] ?? []), (int)$user['id']);
            Response::json(['ok' => true, 'message' => 'تم إعادة إرسال الطلب']);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function workflowEditRequest(string $id): void
    {
        $b = $this->body();
        $user = ApiAuth::user();
        $_SESSION['user_id'] = (int)$user['id'];
        $req = WorkflowEngine::getRequest((int)$id);
        if (!$req) {
            Response::json(['ok' => false, 'error' => 'الطلب غير موجود'], 404);
            return;
        }
        $data = (array)($b['data'] ?? []);
        $data['title'] = (string)($b['title'] ?? ($req['title'] ?? ''));
        $data['notes'] = (string)($b['notes'] ?? '');
        foreach ($req['fields'] as $f) {
            if (($f['type'] ?? '') !== 'file') continue;
            $name = $f['name'];
            if (!empty($data[$name]) && is_string($data[$name]) && str_starts_with($data[$name], 'data:')) {
                $saved = $this->saveBase64File($data[$name], $name);
                if ($saved) $data[$name] = $saved;
            }
        }
        try {
            WorkflowEngine::editRequest((int)$id, $data, (int)$user['id']);
            Response::json(['ok' => true, 'message' => 'تم حفظ التعديل']);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function workflowCancelRequest(string $id): void
    {
        $user = ApiAuth::user();
        $_SESSION['user_id'] = (int)$user['id'];
        try {
            WorkflowEngine::cancelRequest((int)$id, (int)$user['id']);
            Response::json(['ok' => true, 'message' => 'تم إلغاء الطلب']);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function workflowComment(string $id): void
    {
        $b = $this->body();
        $req=WorkflowEngine::getRequest((int)$id);
        if (!$req) { Response::json(['ok'=>false,'error'=>'الطلب غير موجود'],404); return; }
        if (\App\Core\AdvancePolicyService::isAdvance($req['template_code']) && (int)$req['requester_id']!==(int)ApiAuth::id()
            && !\App\Core\AdvancePolicyService::canViewFinancial((int)$req['requester_employee_id'],(int)ApiAuth::id())
            && !\App\Core\Workflow\Engine::userHasPendingTask((int)$id,(int)ApiAuth::id())) {
            Response::json(['ok'=>false,'error'=>'ليس لديك صلاحية التعليق على هذا الطلب'],403); return;
        }
        WorkflowEngine::addComment((int)$id, (string)($b['notes'] ?? ''), ApiAuth::id());
        Response::json(['ok' => true, 'message' => 'تم إضافة التعليق']);
    }

    private function resolveApiItem($barcode, $itemId): ?array
    {
        $barcode = trim((string) ($barcode ?? ''));
        if ($barcode !== '') {
            return BarcodeService::findItemByBarcodeOrName($barcode);
        }
        $id = (int) ($itemId ?? 0);
        if ($id > 0) {
            return Database::query(
                "SELECT i.*, c.name AS category_name, c.type AS category_type, u.name AS unit_name
                 FROM items i
                 JOIN item_categories c ON c.id = i.category_id
                 JOIN units u ON u.id = i.unit_id
                 WHERE i.id = ? AND i.is_active = 1",
                [$id]
            )->fetch() ?: null;
        }
        return null;
    }

    // ===================== الورديات (Shifts) =====================

    private function canManageEmployee(int $employeeId): bool
    {
        $user = ApiAuth::user();
        if (!$user) {
            return false;
        }
        if (($user['role_name'] ?? '') === 'مدير النظام') {
            return true;
        }
        if (Auth::can('hr.manage')) {
            return true;
        }
        $subordinates = array_column(WorkflowEngine::subordinateEmployees((int)$user['id']), 'id');
        return in_array($employeeId, $subordinates, true);
    }

    public function listShifts(): void
    {
        Response::json(['ok' => true, 'data' => HrService::getShifts()]);
    }

    public function updateEmployeeShift(string $id): void
    {
        $employeeId = (int)$id;
        $b = $this->body();
        $shiftId = $b['shift_id'] ?? null;
        $shiftId = $shiftId !== '' && $shiftId !== null ? (int)$shiftId : null;

        if (!$this->canManageEmployee($employeeId)) {
            Response::json(['ok' => false, 'error' => 'ليس لديك صلاحية تعديل وردية هذا الموظف'], 403);
            return;
        }

        try {
            HrService::setEmployeeDefaultShift($employeeId, $shiftId);
            HrService::notifyEmployeeShiftChanged(
                $employeeId,
                'تم تحديث ورديتك الافتراضية',
                'تم تحديث ورديتك الافتراضية في النظام',
                '/hr/employees/' . $employeeId
            );
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
            return;
        }
        Response::json(['ok' => true]);
    }

    public function setEmployeeShiftOverride(): void
    {
        $b = $this->body();
        $employeeId = (int)($b['employee_id'] ?? 0);
        $shiftDate = (string)($b['shift_date'] ?? '');
        $shiftId = (int)($b['shift_id'] ?? 0);

        if (!$this->canManageEmployee($employeeId)) {
            Response::json(['ok' => false, 'error' => 'ليس لديك صلاحية تعديل وردية هذا الموظف'], 403);
            return;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $shiftDate)) {
            Response::json(['ok' => false, 'error' => 'تاريخ غير صالح'], 422);
            return;
        }

        try {
            HrService::setEmployeeShiftForDate($employeeId, $shiftDate, $shiftId, ApiAuth::id());
            HrService::notifyEmployeeShiftChanged(
                $employeeId,
                'تم تغيير ورديتك ليوم ' . $shiftDate,
                'تم تحديد وردية خاصة بك ليوم ' . $shiftDate,
                '/hr/employees/' . $employeeId
            );
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
            return;
        }
        Response::json(['ok' => true]);
    }

    // ===================== طلبات المدير (تعيين / إيقاف دوام) =====================

    private function apiCurrentEmployeeId(): ?int
    {
        $user = ApiAuth::user();
        if (empty($user['id'])) return null;
        $row = Database::query("SELECT id FROM employees WHERE user_id = ? LIMIT 1", [(int)$user['id']])->fetch();
        return $row ? (int)$row['id'] : null;
    }

    private function apiCurrentRoleLevel(): int
    {
        $user = ApiAuth::user();
        $roleId = (int)($user['role_id'] ?? 0);
        if (!$roleId) return 0;
        return (int)(Database::query("SELECT level FROM roles WHERE id = ?", [$roleId])->fetchColumn() ?? 0);
    }

    private function apiIsManager(): bool
    {
        $user = ApiAuth::user();
        if (($user['role_name'] ?? '') === 'مدير النظام') return true;
        return $this->apiCurrentRoleLevel() >= 60;
    }

    private function apiCanDecideHrRequest(string $level): bool
    {
        $user = ApiAuth::user();
        if (($user['role_name'] ?? '') === 'مدير النظام') return true;
        $roleLevel = $this->apiCurrentRoleLevel();
        $roleName = $user['role_name'] ?? '';
        if ($level === 'manager') return $roleLevel >= 60;
        if ($level === 'hr') return $roleLevel >= 60 && str_contains($roleName, 'موارد بشرية');
        if ($level === 'finance') return $roleLevel >= 60 && (str_contains($roleName, 'مالي') || str_contains($roleName, 'محاسب'));
        return false;
    }

    public function managerDashboard(): void
    {
        if (!$this->apiIsManager()) {
            Response::json(['ok' => false, 'error' => 'هذه الميزة متاحة للمدراء فقط'], 403);
            return;
        }
        $employeeId = $this->apiCurrentEmployeeId();
        if (!$employeeId) {
            Response::json(['ok' => false, 'error' => 'لم يتم ربط حسابك بملف موظف'], 404);
            return;
        }
        $subordinatesCount = HrService::getSubordinateCount($employeeId);
        $hiring = Database::query(
            "SELECT h.*, d.name AS department_name, j.title AS job_title
             FROM hiring_requests h
             LEFT JOIN departments d ON d.id = h.department_id
             LEFT JOIN jobs j ON j.id = h.job_id
             WHERE h.requester_id = ? ORDER BY h.id DESC LIMIT 50",
            [$employeeId]
        )->fetchAll();
        $suspensions = Database::query(
            "SELECT s.*, e.full_name AS employee_name
             FROM suspension_requests s
             LEFT JOIN employees e ON e.id = s.employee_id
             WHERE s.requester_id = ? ORDER BY s.id DESC LIMIT 50",
            [$employeeId]
        )->fetchAll();
        Response::json(['ok' => true, 'data' => [
            'subordinates_count' => $subordinatesCount,
            'hiring_requests' => $hiring,
            'suspension_requests' => $suspensions,
        ]]);
    }

    public function subordinates(): void
    {
        if (!$this->apiIsManager()) {
            Response::json(['ok' => false, 'error' => 'هذه الميزة متاحة للمدراء فقط'], 403);
            return;
        }
        $employeeId = $this->apiCurrentEmployeeId();
        if (!$employeeId) {
            Response::json(['ok' => false, 'error' => 'لم يتم ربط حسابك بملف موظف'], 404);
            return;
        }
        Response::json(['ok' => true, 'data' => HrService::getSubordinates($employeeId)]);
    }

    public function hiringRequestsApi(): void
    {
        if (!$this->apiIsManager()) {
            Response::json(['ok' => false, 'error' => 'هذه الميزة متاحة للمدراء فقط'], 403);
            return;
        }
        $employeeId = $this->apiCurrentEmployeeId();
        $sql = "SELECT h.*, d.name AS department_name, j.title AS job_title, req.full_name AS requester_name
                FROM hiring_requests h
                LEFT JOIN departments d ON d.id = h.department_id
                LEFT JOIN jobs j ON j.id = h.job_id
                LEFT JOIN employees req ON req.id = h.requester_id";
        $params = [];
        if ($employeeId) {
            $sql .= " WHERE h.requester_id = ?";
            $params[] = $employeeId;
        }
        $sql .= " ORDER BY h.id DESC LIMIT 200";
        Response::json(['ok' => true, 'data' => Database::query($sql, $params)->fetchAll()]);
    }

    public function storeHiringRequestApi(): void
    {
        if (!$this->apiIsManager()) {
            Response::json(['ok' => false, 'error' => 'هذه الميزة متاحة للمدراء فقط'], 403);
            return;
        }
        $employeeId = $this->apiCurrentEmployeeId();
        if (!$employeeId) {
            Response::json(['ok' => false, 'error' => 'لم يتم ربط حسابك بملف موظف'], 404);
            return;
        }
        $b = $this->body();
        $departmentId = (int)($b['department_id'] ?? 0) ?: null;
        $jobId = (int)($b['job_id'] ?? 0) ?: null;
        $jobTitle = (string)($b['job_title'] ?? '');
        if ($jobId && !$jobTitle) {
            $j = Database::query("SELECT title FROM jobs WHERE id = ?", [$jobId])->fetch();
            $jobTitle = $j['title'] ?? '';
        }
        if (!$jobTitle) {
            Response::json(['ok' => false, 'error' => 'المسمى الوظيفي مطلوب'], 422);
            return;
        }
        $departmentName = '';
        if ($departmentId) {
            $d = Database::query("SELECT name FROM departments WHERE id = ?", [$departmentId])->fetch();
            $departmentName = $d['name'] ?? '';
        }
        $userId = ApiAuth::id();
        $template = WorkflowTemplateService::getTemplateByCode('hiring_request');
        if (!$template) {
            Response::json(['ok' => false, 'error' => 'قالب طلب التعيين غير موجود'], 500);
            return;
        }
        $data = [
            'title' => (string)($b['title'] ?? ('طلب تعيين - ' . $jobTitle)),
            'job_title' => $jobTitle,
            'department_id' => $departmentId,
            'expected_salary' => (float)($b['expected_salary'] ?? 0),
            'reason' => (string)($b['reason'] ?? ''),
            'notes' => (string)($b['notes'] ?? ''),
        ];
        try {
            $requestId = WorkflowEngine::createRequest((int)$template['id'], $userId, $data);
            $requestNo = Helpers::docNumber('HIR', 'hiring_requests', 'request_no');
            Database::query(
                "INSERT INTO hiring_requests
                 (workflow_request_id, request_no, requester_id, manager_id, department_id, job_id, job_title, department_name, expected_salary, reason, notes, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $requestId, $requestNo, $employeeId, $employeeId, $departmentId, $jobId, $jobTitle, $departmentName,
                    $data['expected_salary'], $data['reason'], $data['notes'], $userId
                ]
            );
            WorkflowEngine::submitRequest($requestId, $userId);
            Response::json(['ok' => true, 'message' => 'تم إنشاء طلب التعيين', 'id' => (int)Database::lastInsertId(), 'workflow_id' => $requestId]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function decideHiringRequestApi(string $id): void
    {
        $b = $this->body();
        $decision = (string)($b['decision'] ?? '');
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            Response::json(['ok' => false, 'error' => 'القرار غير صحيح'], 422);
            return;
        }
        $request = Database::query("SELECT workflow_request_id FROM hiring_requests WHERE id = ?", [(int)$id])->fetch();
        if (!$request || !$request['workflow_request_id']) {
            Response::json(['ok' => false, 'error' => 'الطلب غير مرتبط بسير عمل'], 400);
            return;
        }
        try {
            WorkflowEngine::decide((int)$request['workflow_request_id'], $decision, (string)($b['notes'] ?? ''), ApiAuth::id());
            Response::json(['ok' => true, 'message' => 'تم تسجيل القرار']);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function suspensionRequestsApi(): void
    {
        if (!$this->apiIsManager()) {
            Response::json(['ok' => false, 'error' => 'هذه الميزة متاحة للمدراء فقط'], 403);
            return;
        }
        $employeeId = $this->apiCurrentEmployeeId();
        $sql = "SELECT s.*, e.full_name AS employee_name, req.full_name AS requester_name
                FROM suspension_requests s
                LEFT JOIN employees e ON e.id = s.employee_id
                LEFT JOIN employees req ON req.id = s.requester_id";
        $params = [];
        if ($employeeId) {
            $sql .= " WHERE s.requester_id = ?";
            $params[] = $employeeId;
        }
        $sql .= " ORDER BY s.id DESC LIMIT 200";
        Response::json(['ok' => true, 'data' => Database::query($sql, $params)->fetchAll()]);
    }

    public function storeSuspensionRequestApi(): void
    {
        if (!$this->apiIsManager()) {
            Response::json(['ok' => false, 'error' => 'هذه الميزة متاحة للمدراء فقط'], 403);
            return;
        }
        $employeeId = $this->apiCurrentEmployeeId();
        if (!$employeeId) {
            Response::json(['ok' => false, 'error' => 'لم يتم ربط حسابك بملف موظف'], 404);
            return;
        }
        $b = $this->body();
        $targetId = (int)($b['employee_id'] ?? 0);
        if (!$targetId) {
            Response::json(['ok' => false, 'error' => 'يجب اختيار الموظف'], 422);
            return;
        }
        $allowed = array_column(HrService::getSubordinates($employeeId), 'id');
        if (!in_array($targetId, $allowed, true)) {
            Response::json(['ok' => false, 'error' => 'الموظف المحدد ليس من تابعيك المباشرين'], 403);
            return;
        }
        $type = (string)($b['suspension_type'] ?? 'temporary');
        $typeMap = ['temporary' => 'مؤقت', 'permanent' => 'نهائي', 'مؤقت' => 'مؤقت', 'نهائي' => 'نهائي'];
        $typeAr = $typeMap[$type] ?? 'مؤقت';
        $userId = ApiAuth::id();
        $template = WorkflowTemplateService::getTemplateByCode('suspension_request');
        if (!$template) {
            Response::json(['ok' => false, 'error' => 'قالب طلب إيقاف الدوام غير موجود'], 500);
            return;
        }
        $data = [
            'title' => (string)($b['title'] ?? ('طلب إيقاف دوام - ' . $targetId)),
            'employee_id' => $targetId,
            'suspension_date' => (string)($b['suspension_date'] ?? date('Y-m-d')),
            'suspension_type' => $typeAr,
            'reason' => (string)($b['reason'] ?? ''),
            'notes' => (string)($b['notes'] ?? ''),
        ];
        try {
            $requestId = WorkflowEngine::createRequest((int)$template['id'], $userId, $data);
            $requestNo = Helpers::docNumber('SUS', 'suspension_requests', 'request_no');
            Database::query(
                "INSERT INTO suspension_requests
                 (workflow_request_id, request_no, requester_id, manager_id, employee_id, suspension_date, suspension_type, reason, notes, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $requestId, $requestNo, $employeeId, $employeeId, $targetId, $data['suspension_date'],
                    $type === 'permanent' ? 'permanent' : 'temporary', $data['reason'], $data['notes'], $userId
                ]
            );
            WorkflowEngine::submitRequest($requestId, $userId);
            Response::json(['ok' => true, 'message' => 'تم إنشاء طلب إيقاف الدوام', 'id' => (int)Database::lastInsertId(), 'workflow_id' => $requestId]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function decideSuspensionRequestApi(string $id): void
    {
        $b = $this->body();
        $decision = (string)($b['decision'] ?? '');
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            Response::json(['ok' => false, 'error' => 'القرار غير صحيح'], 422);
            return;
        }
        $request = Database::query("SELECT workflow_request_id FROM suspension_requests WHERE id = ?", [(int)$id])->fetch();
        if (!$request || !$request['workflow_request_id']) {
            Response::json(['ok' => false, 'error' => 'الطلب غير مرتبط بسير عمل'], 400);
            return;
        }
        try {
            WorkflowEngine::decide((int)$request['workflow_request_id'], $decision, (string)($b['notes'] ?? ''), ApiAuth::id());
            Response::json(['ok' => true, 'message' => 'تم تسجيل القرار']);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 400);
        }
    }

    private function syncHrRequestStatusApi(string $table, int $id): void
    {
        $row = Database::query("SELECT * FROM $table WHERE id = ?", [$id])->fetch();
        if (!$row) return;
        $statuses = [$row['manager_status'], $row['hr_status'], $row['finance_status']];
        if (in_array('rejected', $statuses, true)) {
            $overall = 'rejected';
        } elseif ($statuses === ['approved', 'approved', 'approved']) {
            $overall = 'approved';
        } else {
            $overall = 'pending';
        }
        Database::query("UPDATE $table SET overall_status = ? WHERE id = ?", [$overall, $id]);
    }

    // ─── طلبات الصيانة (Maintenance) ───────────────────────────────

    private const MAINT_PRIORITIES = [
        'low' => 'منخفضة',
        'normal' => 'عادية',
        'high' => 'عالية',
        'urgent' => 'عاجلة',
    ];

    private const MAINT_WORK_STATUSES = [
        'parts_requested' => 'تم طلب قطع الغيار',
        'in_maintenance' => 'قيد الصيانة',
        'external_maintenance' => 'صيانة خارجية',
        'completed' => 'إتمام الصيانة',
    ];

    /** موظفو قسم الصيانة الذين يُعتبرون "مدير الصيانة" (وظيفة تحتوي "مدير" أو لديهم مرؤوسون ضمن نفس القسم) */
    private function maintenanceManagers(int $deptId): array
    {
        return Database::query(
            "SELECT DISTINCT e.id, e.user_id
             FROM employees e
             LEFT JOIN jobs j ON j.id = e.job_id
             WHERE e.department_id = ? AND e.status = 'active' AND e.file_status = 'open'
               AND (
                 j.title LIKE '%مدير%' OR j.title LIKE '%Manager%'
                 OR e.id IN (SELECT manager_id FROM employees WHERE department_id = ? AND manager_id IS NOT NULL)
               )",
            [$deptId, $deptId]
        )->fetchAll();
    }

    private function isMaintenanceManagerEmp(?array $emp, ?array $dept): bool
    {
        if (ApiAuth::user()['role_name'] === 'مدير النظام') return true;
        if (!$emp || !$dept || (int)($emp['department_id'] ?? 0) !== (int)$dept['id']) return false;
        $managerIds = array_map('intval', array_column($this->maintenanceManagers((int)$dept['id']), 'id'));
        return in_array((int)$emp['id'], $managerIds, true);
    }

    public function maintenanceEmployees(): void
    {
        $dept = Database::query(
            "SELECT * FROM departments WHERE name LIKE '%صيانة%' OR name LIKE '%maintenance%' OR code = 'MAINT' LIMIT 1"
        )->fetch();
        if (!$dept) {
            Response::json(['ok' => true, 'data' => []]);
            return;
        }
        $employees = Database::query(
            "SELECT e.id, e.full_name, e.emp_code
             FROM employees e
             WHERE e.department_id = ? AND e.status = 'active' AND e.file_status = 'open'
             ORDER BY e.full_name",
            [(int)$dept['id']]
        )->fetchAll();
        Response::json(['ok' => true, 'data' => $employees]);
    }

    public function maintenanceList(): void
    {
        $emp = Database::query("SELECT * FROM employees WHERE user_id = ?", [ApiAuth::id()])->fetch();
        $dept = Database::query(
            "SELECT * FROM departments WHERE name LIKE '%صيانة%' OR name LIKE '%maintenance%' OR code = 'MAINT' LIMIT 1"
        )->fetch();
        $isMaint = $this->isMaintenanceManagerEmp($emp, $dept);
        $where = $isMaint ? '1=1' : 'r.requester_id = ? OR r.assigned_to = ?';
        $params = $isMaint ? [] : [$emp['id'] ?? 0, $emp['id'] ?? 0];
        $rows = Database::query(
            "SELECT r.*, req.full_name AS requester_name, a.full_name AS assigned_name
             FROM maintenance_requests r
             LEFT JOIN employees req ON req.id = r.requester_id
             LEFT JOIN employees a ON a.id = r.assigned_to
             WHERE $where ORDER BY r.created_at DESC LIMIT 200",
            $params
        )->fetchAll();
        $meId = (int)ApiAuth::id();
        $myEmpId = (int)($emp['id'] ?? 0);
        foreach ($rows as &$row) {
            $isRequester = (int)($row['requester_user_id'] ?? 0) === $meId;
            $isAssignedToMe = $myEmpId > 0 && (int)($row['assigned_to'] ?? 0) === $myEmpId;
            $row['is_requester'] = $isRequester;
            $row['is_assigned_to_me'] = $isAssignedToMe;
            $row['is_maintenance_manager'] = $isMaint;
            $row['needs_action'] = ($isMaint && $row['status'] === 'pending')
                || ($isAssignedToMe && in_array($row['status'], ['assigned', 'in_progress'], true))
                || ($isRequester && $row['status'] === 'completed');
        }
        unset($row);
        Response::json(['ok' => true, 'data' => $rows, 'is_maintenance_manager' => $isMaint]);
    }

    public function maintenanceShow(string $id): void
    {
        $row = $this->findMaintenanceRequest((int)$id);
        if (!$row) {
            Response::json(['ok' => false, 'error' => 'الطلب غير موجود'], 404);
            return;
        }
        $dept = Database::query(
            "SELECT * FROM departments WHERE name LIKE '%صيانة%' OR name LIKE '%maintenance%' OR code = 'MAINT' LIMIT 1"
        )->fetch();
        $emp = Database::query("SELECT id, department_id FROM employees WHERE user_id = ?", [ApiAuth::id()])->fetch();
        $isMaint = $this->isMaintenanceManagerEmp($emp, $dept);
        $isOwner = (int)$row['requester_user_id'] === (int)ApiAuth::id();
        $isAssigned = $emp && (int)$row['assigned_to'] === (int)$emp['id'];
        if (!$isMaint && !$isOwner && !$isAssigned) {
            Response::json(['ok' => false, 'error' => 'ليس لديك صلاحية عرض هذا الطلب'], 403);
            return;
        }
        $row['is_maintenance_manager'] = $isMaint;
        $row['is_assigned_to_me'] = $isAssigned;
        $row['is_requester'] = $isOwner;
        Response::json(['ok' => true, 'data' => $row]);
    }

    public function maintenanceStore(): void
    {
        $b = $this->body();
        $userId = ApiAuth::id();
        $emp = Database::query("SELECT * FROM employees WHERE user_id = ?", [$userId])->fetch();
        if (!$emp) {
            Response::json(['ok' => false, 'error' => 'لا يوجد ملف موظف مرتبط بحسابك'], 400);
            return;
        }
        $title = trim((string)($b['title'] ?? ''));
        $device = trim((string)($b['device_name'] ?? ''));
        if ($title === '' || $device === '') {
            Response::json(['ok' => false, 'error' => 'اسم العطل واسم الجهاز إجباريان'], 422);
            return;
        }
        $priority = (string)($b['priority'] ?? 'normal');
        if (!isset(self::MAINT_PRIORITIES[$priority])) $priority = 'normal';
        try {
            $image = ImageUpload::fromBase64($b['image_base64'] ?? null, 'maintenance');
        } catch (\RuntimeException $e) {
            Response::json(['ok' => false, 'error' => 'الصورة: ' . $e->getMessage()], 422);
            return;
        }
        if (!$image) {
            Response::json(['ok' => false, 'error' => 'يجب إرفاق صورة'], 422);
            return;
        }
        $dept = Database::query(
            "SELECT * FROM departments WHERE name LIKE '%صيانة%' OR name LIKE '%maintenance%' OR code = 'MAINT' LIMIT 1"
        )->fetch();
        $number = $this->generateMaintenanceNumber();
        try {
            Database::query(
                "INSERT INTO maintenance_requests
                 (request_number, requester_id, requester_user_id, title, device_name, description, priority, image_path, status, maintenance_department_id)
                 VALUES (?,?,?,?,?,?,?,?,?,?)",
                [$number, (int)$emp['id'], $userId, $title, $device,
                 (string)($b['description'] ?? '') ?: null, $priority, $image, 'pending', $dept['id'] ?? null]
            );
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => 'فشل حفظ الطلب، حاول مرة أخرى'], 500);
            return;
        }
        $id = (int)Database::lastInsertId();
        Database::query(
            "INSERT INTO maintenance_status_logs (maintenance_request_id, old_status, new_status, changed_by, notes)
             VALUES (?,?,?,?,?)",
            [$id, null, 'pending', $userId, 'تم إنشاء الطلب من التطبيق']
        );
        if ($dept) {
            $userIds = array_values(array_filter(array_map(
                fn($m) => $m['user_id'] ? (int)$m['user_id'] : null,
                $this->maintenanceManagers((int)$dept['id'])
            )));
            if ($userIds) {
                Helpers::notifyUsers($userIds, 'طلب صيانة جديد', $title, '/maintenance/' . $id, null, true);
            }
        }
        Response::json(['ok' => true, 'id' => $id, 'request_number' => $number]);
    }

    public function maintenanceAssign(string $id): void
    {
        $b = $this->body();
        $assignedTo = (int)($b['assigned_to'] ?? 0);
        if (!$assignedTo) {
            Response::json(['ok' => false, 'error' => 'يجب اختيار موظف'], 422);
            return;
        }
        $row = $this->findMaintenanceRequest((int)$id);
        if (!$row || $row['status'] !== 'pending') {
            Response::json(['ok' => false, 'error' => 'الطلب غير متاح للتعيين'], 400);
            return;
        }
        $dept = Database::query(
            "SELECT * FROM departments WHERE name LIKE '%صيانة%' OR name LIKE '%maintenance%' OR code = 'MAINT' LIMIT 1"
        )->fetch();
        $emp = Database::query("SELECT id, department_id FROM employees WHERE user_id = ?", [ApiAuth::id()])->fetch();
        $isMaint = $this->isMaintenanceManagerEmp($emp, $dept);
        if (!$isMaint) {
            Response::json(['ok' => false, 'error' => 'ليس لديك صلاحية التعيين'], 403);
            return;
        }
        $target = Database::query("SELECT department_id FROM employees WHERE id = ?", [$assignedTo])->fetch();
        if (!$dept || !$target || (int)$target['department_id'] !== (int)$dept['id']) {
            Response::json(['ok' => false, 'error' => 'يجب اختيار موظف من قسم الصيانة'], 422);
            return;
        }
        Database::query(
            "UPDATE maintenance_requests SET assigned_to = ?, assigned_by = ?, assigned_at = NOW(), status = 'assigned', updated_at = NOW() WHERE id = ?",
            [$assignedTo, $emp['id'] ?? null, (int)$id]
        );
        Database::query(
            "INSERT INTO maintenance_status_logs (maintenance_request_id, old_status, new_status, changed_by, notes)
             VALUES (?,?,?,?,?)",
            [(int)$id, 'pending', 'assigned', ApiAuth::id(), 'تم تعيين الموظف المسؤول']
        );
        $target = Database::query("SELECT user_id FROM employees WHERE id = ?", [$assignedTo])->fetch();
        if ($target && $target['user_id']) {
            NotificationService::sendToUser(
                (int)$target['user_id'], 'مهمة صيانة معينة', 'تم تعيين طلب صيانة لك', '/maintenance/' . $id, ['push']
            );
        }
        Response::json(['ok' => true, 'message' => 'تم التعيين']);
    }

    public function maintenanceStart(string $id): void
    {
        $this->transitionMaintenanceStatus((int)$id, 'assigned', 'in_progress', 'بدء التنفيذ', true);
    }

    public function maintenanceFinish(string $id): void
    {
        $b = $this->body();
        $workStatus = (string)($b['work_status'] ?? '');
        if (!isset(self::MAINT_WORK_STATUSES[$workStatus])) {
            Response::json(['ok' => false, 'error' => 'يجب اختيار حالة صحيحة'], 422);
            return;
        }
        $row = $this->findMaintenanceRequest((int)$id);
        if (!$row || $row['status'] !== 'in_progress') {
            Response::json(['ok' => false, 'error' => 'الطلب غير متاح للتحديث'], 400);
            return;
        }
        $emp = Database::query("SELECT id FROM employees WHERE user_id = ?", [ApiAuth::id()])->fetch();
        if (!$emp || (int)$row['assigned_to'] !== (int)$emp['id']) {
            Response::json(['ok' => false, 'error' => 'هذه المهمة غير مخصصة لك'], 403);
            return;
        }
        $notes = trim((string)($b['resolution_notes'] ?? ''));
        $cost = isset($b['cost']) && $b['cost'] !== '' && $b['cost'] !== null ? (float)$b['cost'] : null;
        $isDone = $workStatus === 'completed';
        Database::query(
            "UPDATE maintenance_requests
             SET work_status = ?, resolution_notes = ?, cost = ?,
                 status = ?, finished_at = ?, updated_at = NOW()
             WHERE id = ?",
            [$workStatus, $notes !== '' ? $notes : null, $cost,
             $isDone ? 'completed' : $row['status'], $isDone ? date('Y-m-d H:i:s') : $row['finished_at'], (int)$id]
        );
        Database::query(
            "INSERT INTO maintenance_status_logs (maintenance_request_id, old_status, new_status, changed_by, notes)
             VALUES (?,?,?,?,?)",
            [(int)$id, $row['status'], $isDone ? 'completed' : $workStatus, ApiAuth::id(), self::MAINT_WORK_STATUSES[$workStatus]]
        );
        $this->notifyMaintenanceStatusChange((int)$id, $isDone ? 'completed' : $workStatus);
        Response::json(['ok' => true, 'message' => $isDone ? 'تم إنهاء المهمة' : 'تم تحديث حالة الصيانة']);
    }

    public function maintenanceConfirm(string $id): void
    {
        $row = $this->findMaintenanceRequest((int)$id);
        if (!$row || $row['status'] !== 'completed') {
            Response::json(['ok' => false, 'error' => 'الطلب غير متاح للتأكيد'], 400);
            return;
        }
        if ((int)$row['requester_user_id'] !== (int)ApiAuth::id()) {
            Response::json(['ok' => false, 'error' => 'لست صاحب الطلب'], 403);
            return;
        }
        Database::query(
            "UPDATE maintenance_requests SET status = 'confirmed', confirmed_at = NOW(), updated_at = NOW() WHERE id = ?",
            [(int)$id]
        );
        Database::query(
            "INSERT INTO maintenance_status_logs (maintenance_request_id, old_status, new_status, changed_by, notes)
             VALUES (?,?,?,?,?)",
            [(int)$id, 'completed', 'confirmed', ApiAuth::id(), 'تم التأكيد من صاحب الطلب']
        );
        $this->notifyMaintenanceStatusChange((int)$id, 'confirmed');
        Response::json(['ok' => true, 'message' => 'تم التأكيد']);
    }

    private function generateMaintenanceNumber(): string
    {
        $prefix = 'MAINT-' . date('Ymd') . '-';
        $last = Database::query(
            "SELECT request_number FROM maintenance_requests WHERE request_number LIKE ? ORDER BY id DESC LIMIT 1",
            [$prefix . '%']
        )->fetch();
        $n = 1;
        if ($last) {
            $parts = explode('-', (string)$last['request_number']);
            $n = ((int)end($parts)) + 1;
        }
        return $prefix . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
    }

    private function findMaintenanceRequest(int $id): ?array
    {
        return Database::query(
            "SELECT r.*, req.full_name AS requester_name, a.full_name AS assigned_name, a.user_id AS assigned_user_id
             FROM maintenance_requests r
             LEFT JOIN employees req ON req.id = r.requester_id
             LEFT JOIN employees a ON a.id = r.assigned_to
             WHERE r.id = ?",
            [$id]
        )->fetch() ?: null;
    }

    private function transitionMaintenanceStatus(int $id, string $from, string $to, string $notes, bool $mustBeAssigned): void
    {
        $row = $this->findMaintenanceRequest($id);
        if (!$row || $row['status'] !== $from) {
            Response::json(['ok' => false, 'error' => 'الطلب غير متاح لهذه العملية'], 400);
            return;
        }
        if ($mustBeAssigned) {
            $emp = Database::query("SELECT id FROM employees WHERE user_id = ?", [ApiAuth::id()])->fetch();
            if (!$emp || (int)$row['assigned_to'] !== (int)$emp['id']) {
                Response::json(['ok' => false, 'error' => 'هذه المهمة غير مخصصة لك'], 403);
                return;
            }
        }
        Database::query(
            "UPDATE maintenance_requests SET status = ?, updated_at = NOW() WHERE id = ?",
            [$to, $id]
        );
        if ($to === 'in_progress') {
            Database::query("UPDATE maintenance_requests SET started_at = NOW() WHERE id = ?", [$id]);
        } elseif ($to === 'completed') {
            Database::query("UPDATE maintenance_requests SET finished_at = NOW() WHERE id = ?", [$id]);
        }
        Database::query(
            "INSERT INTO maintenance_status_logs (maintenance_request_id, old_status, new_status, changed_by, notes)
             VALUES (?,?,?,?,?)",
            [$id, $from, $to, ApiAuth::id(), $notes]
        );
        $this->notifyMaintenanceStatusChange($id, $to);
        Response::json(['ok' => true, 'message' => 'تم التحديث']);
    }

    private function notifyMaintenanceStatusChange(int $id, string $status): void
    {
        $labels = ['pending' => 'معلق', 'assigned' => 'معين', 'in_progress' => 'قيد التنفيذ', 'completed' => 'منتهي', 'confirmed' => 'تم التأكيد', 'cancelled' => 'ملغى'];
        $label = $labels[$status] ?? self::MAINT_WORK_STATUSES[$status] ?? $status;
        $row = $this->findMaintenanceRequest($id);
        if (!$row) return;
        if ($row['requester_user_id']) {
            NotificationService::sendToUser(
                (int)$row['requester_user_id'], 'تحديث طلب صيانة', 'الحالة: ' . $label, '/maintenance/' . $id, ['push']
            );
        }
        if ($row['assigned_user_id'] && (int)$row['assigned_user_id'] !== (int)$row['requester_user_id']) {
            NotificationService::sendToUser(
                (int)$row['assigned_user_id'], 'تحديث طلب صيانة', 'الحالة: ' . $label, '/maintenance/' . $id, ['push']
            );
        }
    }

}
