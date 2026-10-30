<?php
namespace App\Core;

/**
 * منطق مشترك للبريد الداخلي بين الويب (MailController) وتطبيق الموبايل
 * (ApiController) لتفادي ازدواج الكود: حل المستلمين (أفراد/أقسام/مجموعات)،
 * إنشاء الرسالة ومستلميها وأهدافها، وحفظ المرفقات.
 */
class MailService
{
    /** نتيجة مُخزَّنة مؤقتاً (لكل طلب) لوجود جداول migration 172 */
    private static ?bool $migrationReady = null;

    /**
     * يتحقق من وجود جداول migration 172 (مرفقات/أهداف/مجموعات البريد). تُستخدم
     * لحماية صفحات البريد الأساسية (الوارد/الصادر/الإنشاء) من كسر كامل بخطأ
     * قاعدة بيانات غير مُعالَج على أي بيئة لم يُشغَّل عليها migrate.php بعد —
     * بنفس نمط حماية unreadCount() من migration 171 سابقاً.
     */
    public static function migrationReady(): bool
    {
        if (self::$migrationReady === null) {
            try {
                Database::query("SELECT 1 FROM internal_mail_attachments LIMIT 1")->fetch();
                self::$migrationReady = true;
            } catch (\Throwable $e) {
                self::$migrationReady = false;
            }
        }
        return self::$migrationReady;
    }

    /**
     * يحل قائمة المستلمين النهائية (معرّفات مستخدمين فريدة، باستثناء المُرسل)
     * من مستخدمين أفراد + أقسام كاملة + مجموعات بريدية، ويُرجع أيضاً "الأهداف"
     * الأصلية (قسم/مجموعة) لعرضها لاحقاً في صندوق الصادر.
     *
     * @return array{recipients: int[], targets: array<int, array{type:string,id:int,label:string}>}
     */
    public static function resolveRecipients(array $userIds, array $departmentIds, array $groupIds, int $excludeUserId): array
    {
        $recipients = array_map('intval', $userIds);
        $targets = [];

        foreach (array_unique(array_filter(array_map('intval', $departmentIds))) as $did) {
            $dept = Database::query("SELECT id, name FROM departments WHERE id = ? AND is_active = 1", [$did])->fetch();
            if (!$dept) continue;
            $targets[] = ['type' => 'department', 'id' => $did, 'label' => $dept['name']];
            $rows = Database::query(
                "SELECT u.id FROM employees e JOIN users u ON u.id = e.user_id
                 WHERE e.department_id = ? AND u.is_active = 1 AND (e.status IS NULL OR e.status = 'active')",
                [$did]
            )->fetchAll();
            foreach ($rows as $r) $recipients[] = (int)$r['id'];
        }

        foreach (array_unique(array_filter(array_map('intval', $groupIds))) as $gid) {
            $grp = Database::query("SELECT id, name FROM mail_groups WHERE id = ? AND is_active = 1", [$gid])->fetch();
            if (!$grp) continue;
            $targets[] = ['type' => 'group', 'id' => $gid, 'label' => $grp['name']];
            $rows = Database::query("SELECT user_id FROM mail_group_members WHERE group_id = ?", [$gid])->fetchAll();
            foreach ($rows as $r) $recipients[] = (int)$r['user_id'];
        }

        $recipients = array_values(array_unique(array_filter(
            $recipients,
            fn($id) => $id > 0 && $id !== $excludeUserId
        )));

        return ['recipients' => $recipients, 'targets' => $targets];
    }

    /** ينشئ الرسالة ومستلميها وأهدافها، ويُرجع معرّف الرسالة الجديد */
    public static function createMail(int $senderId, string $subject, string $body, array $recipients, array $targets): int
    {
        Database::query("INSERT INTO internal_mails (sender_id, subject, body) VALUES (?,?,?)", [$senderId, $subject, $body]);
        $mailId = (int)Database::lastInsertId();

        $recipients = array_unique(array_filter(array_map('intval', $recipients),
            fn($id) => $id > 0 && $id !== $senderId));
        foreach ($recipients as $rid) {
            Database::query("INSERT INTO internal_mail_recipients (mail_id, recipient_id) VALUES (?,?)", [$mailId, $rid]);
        }
        foreach ($targets as $t) {
            Database::query(
                "INSERT INTO internal_mail_targets (mail_id, target_type, target_id, target_label) VALUES (?,?,?,?)",
                [$mailId, $t['type'], $t['id'], $t['label']]
            );
        }
        // Both web and mobile callers wrap mail, attachments and these queue rows
        // in the same transaction: failed sends cannot produce phantom alerts.
        foreach ($recipients as $rid) {
            Helpers::notify($rid, 'رسالة داخلية جديدة', $subject, '/mail/' . $mailId,
                $senderId, null, [], null, 'internal_mail', $mailId, 'new_message', [], true);
        }
        return $mailId;
    }

    /** يحفظ ملف مرفق واحد من مصفوفة بنمط $_FILES القياسي؛ يتجاهل الإدخالات الفارغة بصمت */
    public static function saveAttachmentFromFilesArray(int $mailId, array $file): void
    {
        if (empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return;
        }
        // يجب قراءة نوع الملف قبل النقل؛ DocumentUpload::fromFilesArray ينقل
        // الملف من tmp_name فيصبح غير قابل للقراءة بعد استدعائها.
        $mime = mime_content_type($file['tmp_name']) ?: null;
        $path = DocumentUpload::fromFilesArray($file, 'mail');
        if ($path) {
            Database::query(
                "INSERT INTO internal_mail_attachments (mail_id, file_path, original_name, mime_type, file_size) VALUES (?,?,?,?,?)",
                [$mailId, $path, $file['name'] ?? null, $mime, (int)($file['size'] ?? 0)]
            );
        }
    }

    /** يحفظ كل مرفقات الطلب الحالي من حقل $_FILES[$field] متعدد الملفات (اسم[] في النموذج) */
    public static function saveAttachmentsFromRequest(int $mailId, string $field = 'attachments'): void
    {
        if (empty($_FILES[$field]['tmp_name']) || !is_array($_FILES[$field]['tmp_name'])) {
            return;
        }
        foreach (array_keys($_FILES[$field]['tmp_name']) as $i) {
            self::saveAttachmentFromFilesArray($mailId, [
                'tmp_name' => $_FILES[$field]['tmp_name'][$i],
                'error'    => $_FILES[$field]['error'][$i],
                'size'     => $_FILES[$field]['size'][$i],
                'name'     => $_FILES[$field]['name'][$i],
            ]);
        }
    }

    public static function attachments(int $mailId): array
    {
        if (!self::migrationReady()) return [];
        try {
            return Database::query(
                "SELECT id, file_path, original_name, mime_type, file_size FROM internal_mail_attachments WHERE mail_id = ? ORDER BY id",
                [$mailId]
            )->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function targets(int $mailId): array
    {
        if (!self::migrationReady()) return [];
        try {
            return Database::query(
                "SELECT target_type, target_id, target_label FROM internal_mail_targets WHERE mail_id = ? ORDER BY id",
                [$mailId]
            )->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
