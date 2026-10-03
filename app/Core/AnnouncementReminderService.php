<?php
namespace App\Core;

/**
 * تذكيرات التعميمات: إنشاء/إدارة تذكير لمرة واحدة أو روتيني (يومي/أسبوعي/شهري)
 * يعيد إرسال نفس التعميم تلقائياً عبر cron حتى موعد الاستحقاق التالي.
 */
class AnnouncementReminderService
{
    /** إنشاء تذكير جديد، يعيد المعرّف الجديد */
    public static function create(array $data): int
    {
        Database::query(
            "INSERT INTO announcement_reminders
                (created_by, title, body, link, target_type, target_level, target_role_id,
                 target_branch_id, target_department_id, target_user_ids, recurrence_type, next_run_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                $data['created_by'], $data['title'], $data['body'] ?? '', $data['link'] ?? null,
                $data['target_type'], $data['target_level'] ?? null, $data['target_role_id'] ?? null,
                $data['target_branch_id'] ?? null, $data['target_department_id'] ?? null,
                !empty($data['target_user_ids']) ? json_encode(array_values($data['target_user_ids'])) : null,
                $data['recurrence_type'] ?? 'once', $data['next_run_at'],
            ]
        );
        return (int) Database::lastInsertId();
    }

    /** تحديث تذكير موجود (محتوى/مستلمين/موعد/تكرار) دون إنشاء سجل جديد */
    public static function update(int $id, array $data): void
    {
        Database::query(
            "UPDATE announcement_reminders SET
                title = ?, body = ?, link = ?, target_type = ?, target_level = ?, target_role_id = ?,
                target_branch_id = ?, target_department_id = ?, target_user_ids = ?, recurrence_type = ?, next_run_at = ?
             WHERE id = ?",
            [
                $data['title'], $data['body'] ?? '', $data['link'] ?? null,
                $data['target_type'], $data['target_level'] ?? null, $data['target_role_id'] ?? null,
                $data['target_branch_id'] ?? null, $data['target_department_id'] ?? null,
                !empty($data['target_user_ids']) ? json_encode(array_values($data['target_user_ids'])) : null,
                $data['recurrence_type'] ?? 'once', $data['next_run_at'], $id,
            ]
        );
    }

    /** قائمة كل التذكيرات (الفعّالة أولاً) مع تسميات المستلمين */
    public static function listAll(int $limit = 50): array
    {
        return Database::query(
            "SELECT ar.*, u.full_name AS created_by_name
             FROM announcement_reminders ar
             LEFT JOIN users u ON u.id = ar.created_by
             ORDER BY ar.is_active DESC, ar.next_run_at ASC
             LIMIT ?",
            [$limit]
        )->fetchAll();
    }

    public static function toggle(int $id): void
    {
        Database::query("UPDATE announcement_reminders SET is_active = NOT is_active WHERE id = ?", [$id]);
    }

    public static function delete(int $id): void
    {
        Database::query("DELETE FROM announcement_reminders WHERE id = ?", [$id]);
    }

    /** نقطة التشغيل الدورية (cron): يرسل كل التذكيرات المستحقة ويجدول التالية */
    public static function processDue(): int
    {
        $due = Database::query(
            "SELECT * FROM announcement_reminders WHERE is_active = 1 AND next_run_at <= NOW()"
        )->fetchAll();
        $count = 0;
        foreach ($due as $row) {
            try {
                self::dispatch($row);
                $count++;
            } catch (\Throwable $e) {
                error_log('Announcement reminder #' . $row['id'] . ' failed: ' . $e->getMessage());
            }
            if ($row['recurrence_type'] === 'once') {
                Database::query(
                    "UPDATE announcement_reminders SET is_active = 0, last_sent_at = NOW() WHERE id = ?",
                    [$row['id']]
                );
            } else {
                $next = self::nextRun((string)$row['next_run_at'], (string)$row['recurrence_type']);
                Database::query(
                    "UPDATE announcement_reminders SET last_sent_at = NOW(), next_run_at = ? WHERE id = ?",
                    [$next, $row['id']]
                );
            }
        }
        return $count;
    }

    private static function nextRun(string $from, string $recurrenceType): string
    {
        $interval = ['daily' => '+1 day', 'weekly' => '+1 week', 'monthly' => '+1 month'][$recurrenceType] ?? '+1 day';
        $ts = strtotime($from) ?: time();
        // تقدّم بخطوة واحدة على الأقل، وإذا كان الموعد ما زال في الماضي (مثلاً بعد توقف طويل) استمر للأمام حتى يتجاوز الآن
        do {
            $ts = strtotime($interval, $ts);
        } while ($ts <= time());
        return date('Y-m-d H:i:s', $ts);
    }

    private static function dispatch(array $row): void
    {
        $title = (string)$row['title'];
        $body = (string)$row['body'];
        $link = $row['link'] ?: null;
        $senderId = (int)$row['created_by'] ?: null;
        switch ($row['target_type']) {
            case 'all':
                Helpers::broadcastAnnouncement($title, $body, $link, $senderId, true);
                break;
            case 'level':
                Helpers::notifyByLevel((int)$row['target_level'], $title, $body, $link, $senderId, true);
                break;
            case 'role':
                if ($row['target_role_id']) {
                    Helpers::notifyRole((int)$row['target_role_id'], $title, $body, $link, $senderId, true);
                }
                break;
            case 'branch':
                if ($row['target_branch_id']) {
                    Helpers::notifyByBranch((int)$row['target_branch_id'], $title, $body, $link, $senderId, true);
                }
                break;
            case 'department':
                if ($row['target_department_id']) {
                    Helpers::notifyByDepartment((int)$row['target_department_id'], $title, $body, $link, $senderId, true);
                }
                break;
            case 'user':
                $userIds = json_decode((string)$row['target_user_ids'], true) ?: [];
                if ($userIds) {
                    Helpers::notifyUsers(array_map('intval', $userIds), $title, $body, $link, $senderId, true);
                }
                break;
        }
    }
}
