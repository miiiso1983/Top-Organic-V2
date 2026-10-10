<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Response, Auth, MailService};

/**
 * البريد الداخلي — مراسلات حقيقية بين مستخدمي النظام (إرسال/استقبال/رد)،
 * متاح لكل مستخدم مسجّل دخول بلا شرط صلاحية (مثل مركز الإشعارات).
 */
class MailController extends Controller
{
    /** صندوق الوارد */
    public function index(): void
    {
        $uid = (int)Auth::id();
        $rows = Database::query(
            "SELECT m.id, m.subject, m.body, m.created_at, u.full_name AS sender_name, r.read_at,
                    (SELECT COUNT(*) FROM internal_mail_attachments WHERE mail_id = m.id) AS attachments_count
             FROM internal_mail_recipients r
             JOIN internal_mails m ON m.id = r.mail_id
             JOIN users u ON u.id = m.sender_id
             WHERE r.recipient_id = ? AND r.deleted_by_recipient = 0
             ORDER BY (r.read_at IS NULL) DESC, m.id DESC",
            [$uid]
        )->fetchAll();
        $this->view('mail/index', [
            'title' => 'البريد الداخلي',
            'rows' => $rows,
            'unread' => self::unreadCount($uid),
        ]);
    }

    /** صندوق الصادر */
    public function sent(): void
    {
        $uid = (int)Auth::id();
        $rows = Database::query(
            "SELECT m.id, m.subject, m.body, m.created_at,
                    (SELECT COUNT(*) FROM internal_mail_recipients WHERE mail_id = m.id) AS recipients_count,
                    (SELECT COUNT(*) FROM internal_mail_attachments WHERE mail_id = m.id) AS attachments_count,
                    (SELECT GROUP_CONCAT(u2.full_name SEPARATOR '، ')
                       FROM internal_mail_recipients r2 JOIN users u2 ON u2.id = r2.recipient_id
                      WHERE r2.mail_id = m.id) AS recipients_names
             FROM internal_mails m
             WHERE m.sender_id = ? AND m.deleted_by_sender = 0
             ORDER BY m.id DESC",
            [$uid]
        )->fetchAll();
        $this->view('mail/sent', ['title' => 'البريد المُرسل', 'rows' => $rows]);
    }

    /** نموذج إنشاء رسالة جديدة (يدعم رداً مسبقاً عبر ?to=&subject=) */
    public function composeForm(): void
    {
        $uid = (int)Auth::id();
        $users = Database::query(
            "SELECT id, full_name FROM users WHERE id != ? AND is_active = 1 ORDER BY full_name",
            [$uid]
        )->fetchAll();
        $departments = Database::query(
            "SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name"
        )->fetchAll();
        $groups = Database::query(
            "SELECT g.id, g.name, (SELECT COUNT(*) FROM mail_group_members WHERE group_id = g.id) AS members_count
             FROM mail_groups g WHERE g.is_active = 1 ORDER BY g.name"
        )->fetchAll();
        $this->view('mail/compose', [
            'title' => 'رسالة جديدة',
            'users' => $users,
            'departments' => $departments,
            'groups' => $groups,
            'prefillTo' => (int)$this->input('to', 0),
            'prefillSubject' => (string)$this->input('subject', ''),
        ]);
    }

    public function store(): void
    {
        $uid = (int)Auth::id();
        $subject = trim((string)($_POST['subject'] ?? ''));
        $body = trim((string)($_POST['body'] ?? ''));
        $resolved = MailService::resolveRecipients(
            (array)($_POST['recipients'] ?? []),
            (array)($_POST['department_ids'] ?? []),
            (array)($_POST['group_ids'] ?? []),
            $uid
        );

        if (!$resolved['recipients']) {
            $this->flash('error', 'يجب اختيار مستلم واحد أو قسم أو مجموعة على الأقل');
            Response::redirect('/mail/compose');
            return;
        }
        if ($subject === '' || $body === '') {
            $this->flash('error', 'الموضوع ونص الرسالة مطلوبان');
            Response::redirect('/mail/compose');
            return;
        }

        // معاملة واحدة لضمان عدم إرسال الرسالة فعلياً للمستلمين إذا فشل
        // رفع أحد المرفقات (مثلاً نوع ملف غير مدعوم)
        Database::beginTransaction();
        try {
            $mailId = MailService::createMail($uid, $subject, $body, $resolved['recipients'], $resolved['targets']);
            MailService::saveAttachmentsFromRequest($mailId);
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->flash('error', 'تعذر إرسال الرسالة: ' . $e->getMessage());
            Response::redirect('/mail/compose');
            return;
        }
        $this->flash('success', 'تم إرسال الرسالة بنجاح');
        Response::redirect('/mail/sent');
    }

    /** عرض رسالة واردة — تُعلَّم كمقروءة تلقائياً */
    public function show(string $id): void
    {
        $uid = (int)Auth::id();
        $row = Database::query(
            "SELECT m.id, m.subject, m.body, m.created_at, m.sender_id, u.full_name AS sender_name,
                    r.id AS recipient_row_id, r.read_at
             FROM internal_mail_recipients r
             JOIN internal_mails m ON m.id = r.mail_id
             JOIN users u ON u.id = m.sender_id
             WHERE m.id = ? AND r.recipient_id = ? AND r.deleted_by_recipient = 0",
            [(int)$id, $uid]
        )->fetch();
        if (!$row) Response::abort(404, 'الرسالة غير موجودة');
        if (!$row['read_at']) {
            Database::query("UPDATE internal_mail_recipients SET read_at = NOW() WHERE id = ?", [(int)$row['recipient_row_id']]);
            $row['read_at'] = date('Y-m-d H:i:s');
        }
        $row['attachments'] = MailService::attachments((int)$row['id']);
        $this->view('mail/show', ['title' => 'رسالة', 'row' => $row]);
    }

    /** عرض رسالة مُرسلة مع حالة القراءة عند كل مستلم */
    public function showSent(string $id): void
    {
        $uid = (int)Auth::id();
        $row = Database::query(
            "SELECT * FROM internal_mails WHERE id = ? AND sender_id = ? AND deleted_by_sender = 0",
            [(int)$id, $uid]
        )->fetch();
        if (!$row) Response::abort(404, 'الرسالة غير موجودة');
        $recipients = Database::query(
            "SELECT u.full_name, r.read_at
             FROM internal_mail_recipients r JOIN users u ON u.id = r.recipient_id
             WHERE r.mail_id = ? ORDER BY u.full_name",
            [(int)$id]
        )->fetchAll();
        $row['attachments'] = MailService::attachments((int)$row['id']);
        $targets = MailService::targets((int)$row['id']);
        $this->view('mail/sent_show', ['title' => 'رسالة مُرسلة', 'row' => $row, 'recipients' => $recipients, 'targets' => $targets]);
    }

    public function delete(string $id): void
    {
        $uid = (int)Auth::id();
        Database::query(
            "UPDATE internal_mail_recipients SET deleted_by_recipient = 1 WHERE mail_id = ? AND recipient_id = ?",
            [(int)$id, $uid]
        );
        $this->flash('success', 'تم حذف الرسالة');
        Response::redirect('/mail');
    }

    public function deleteSent(string $id): void
    {
        $uid = (int)Auth::id();
        Database::query(
            "UPDATE internal_mails SET deleted_by_sender = 1 WHERE id = ? AND sender_id = ?",
            [(int)$id, $uid]
        );
        $this->flash('success', 'تم حذف الرسالة');
        Response::redirect('/mail/sent');
    }

    /** عدّاد غير المقروء — لتحديث أيقونة البريد في الشريط العلوي دورياً */
    public function countJson(): void
    {
        Response::json(['ok' => true, 'unread' => self::unreadCount((int)Auth::id())]);
    }

    public static function unreadCount(int $userId): int
    {
        // محمي بـ try/catch لأن جداول البريد الداخلي قد تكون غير موجودة بعد
        // (قبل تشغيل migration 171) على بيئات لم تُحدَّث بعد؛ يجب ألا يؤدي
        // ذلك لكسر الصفحة بالكامل عبر layout الرئيسي الذي يستدعي هذه الدالة.
        try {
            return (int)Database::query(
                "SELECT COUNT(*) FROM internal_mail_recipients WHERE recipient_id = ? AND deleted_by_recipient = 0 AND read_at IS NULL",
                [$userId]
            )->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
