<?php
namespace App\Core;

/**
 * دوال مساعدة عامة
 */
class Helpers
{
    /** توليد رقم مستند تسلسلي مثل ORD-20260716-0001 */
    public static function docNumber(string $prefix, string $table, string $column): string
    {
        $date = date('Ymd');
        $fullPrefix = "$prefix-$date-";
        $like = "$fullPrefix%";
        // نعتمد على أعلى رقم تسلسلي فعلي (MAX) وليس عدد الصفوف (COUNT)، لتفادي تصادم
        // الأرقام عند وجود فجوات في التسلسل (مثلاً عند حذف سجل سابق)
        $maxSuffix = (int) Database::query(
            "SELECT COALESCE(MAX(CAST(SUBSTRING($column, ?) AS UNSIGNED)), 0) AS m FROM $table WHERE $column LIKE ?",
            [strlen($fullPrefix) + 1, $like]
        )->fetch()['m'];
        return sprintf('%s-%s-%04d', $prefix, $date, $maxSuffix + 1);
    }

    public static function money($amount): string
    {
        return number_format((float)$amount, 0) . ' ' . App::config('currency');
    }

    public static function notify(int $userId, string $title, string $body = '', ?string $link = null, ?int $senderId = null, ?string $targetType = null, array $target = [], ?int $broadcastId = null, ?string $entityType = null, ?int $entityId = null, ?string $actionType = null, array $metadata = [], bool $pushToo = false): void
    {
        Database::query(
            "INSERT INTO notifications (user_id, title, body, link, sender_id, is_announcement, target_type, target_level, target_role_id, target_branch_id, target_department_id, broadcast_id, entity_type, entity_id, action_type, metadata_json)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [$userId, $title, $body, $link, $senderId, $targetType ? 1 : 0, $targetType ?? 'user',
             $target['level'] ?? null, $target['role_id'] ?? null, $target['branch_id'] ?? null, $target['department_id'] ?? null, $broadcastId,
             $entityType, $entityId, $actionType, $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null]
        );
        // إشعار Push لتطبيق الموبايل/المتصفح (بالإضافة لإشعار الويب أعلاه) عند الطلب فقط
        if ($pushToo) {
            try {
                \App\Core\Workflow\PushService::queue($userId, $title, $body, $link, $entityType, $entityId, $actionType);
            } catch (\Throwable $e) {
                error_log('Helpers::notify push queue failed: ' . $e->getMessage());
            }
        }
    }

    /** إنشاء تعميم لمجموعة من المستخدمين مع رابط `broadcast_id` مشترك */
    private static function sendBroadcast(string $title, string $body, array $users, ?string $link = null, ?int $senderId = null, string $targetType = 'all', array $target = [], bool $pushToo = false): void
    {
        if (!$users) {
            return;
        }
        // إنشاء صف قالب يحمل `broadcast_id` الخاص به
        $first = (int)$users[0]['id'];
        self::notify($first, $title, $body, $link, $senderId, $targetType, $target, null, null, null, null, [], $pushToo);
        $broadcastId = (int)Database::lastInsertId();
        Database::query("UPDATE notifications SET broadcast_id = ? WHERE id = ?", [$broadcastId, $broadcastId]);
        // إرسال النسخ الباقية
        for ($i = 1; $i < count($users); $i++) {
            self::notify((int)$users[$i]['id'], $title, $body, $link, $senderId, $targetType, $target, $broadcastId, null, null, null, [], $pushToo);
        }
    }

    /** إشعار كل مستخدمي دور معيّن */
    public static function notifyRole(int $roleId, string $title, string $body = '', ?string $link = null, ?int $senderId = null, bool $pushToo = false): void
    {
        $users = Database::query("SELECT id FROM users WHERE role_id = ? AND is_active = 1", [$roleId])->fetchAll();
        self::sendBroadcast($title, $body, $users, $link, $senderId, 'role', ['role_id' => $roleId], $pushToo);
    }

    /** إشعار كل مستخدمين مستوى إداري معيّن أو أعلى */
    public static function notifyByLevel(int $minLevel, string $title, string $body = '', ?string $link = null, ?int $senderId = null, bool $pushToo = false): void
    {
        $users = Database::query(
            "SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.level >= ? AND u.is_active = 1",
            [$minLevel]
        )->fetchAll();
        self::sendBroadcast($title, $body, $users, $link, $senderId, 'level', ['level' => $minLevel], $pushToo);
    }

    /** إشعار كل مستخدمين فرع معيّن */
    public static function notifyByBranch(int $branchId, string $title, string $body = '', ?string $link = null, ?int $senderId = null, bool $pushToo = false): void
    {
        $users = Database::query("SELECT id FROM users WHERE branch_id = ? AND is_active = 1", [$branchId])->fetchAll();
        self::sendBroadcast($title, $body, $users, $link, $senderId, 'branch', ['branch_id' => $branchId], $pushToo);
    }

    /** إشعار كل مستخدمين قسم معيّن (عبر جدول الموظفين) */
    public static function notifyByDepartment(int $departmentId, string $title, string $body = '', ?string $link = null, ?int $senderId = null, bool $pushToo = false): void
    {
        $users = Database::query(
            "SELECT DISTINCT u.id FROM users u
             JOIN employees e ON e.user_id = u.id
             WHERE e.department_id = ? AND u.is_active = 1",
            [$departmentId]
        )->fetchAll();
        self::sendBroadcast($title, $body, $users, $link, $senderId, 'department', ['department_id' => $departmentId], $pushToo);
    }

    /** إشعار مجموعة مستخدمين محددين بالمعرّف (مع broadcast_id مشترك عند تعدد المستلمين) */
    public static function notifyUsers(array $userIds, string $title, string $body = '', ?string $link = null, ?int $senderId = null, bool $pushToo = false): void
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (!$userIds) {
            return;
        }
        $users = array_map(function ($id) { return ['id' => $id]; }, $userIds);
        self::sendBroadcast($title, $body, $users, $link, $senderId, 'user', ['user_ids' => $userIds], $pushToo);
    }

    /** تعميم لكل المستخدمين النشطين */
    public static function broadcastAnnouncement(string $title, string $body = '', ?string $link = null, ?int $senderId = null, bool $pushToo = false): void
    {
        $users = Database::query("SELECT id FROM users WHERE is_active = 1")->fetchAll();
        self::sendBroadcast($title, $body, $users, $link, $senderId, 'all', [], $pushToo);
    }

    /** عدد الإشعارات غير المقروءة لمستخدم */
    public static function unreadCount(int $userId): int
    {
        return (int)Database::query(
            "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0",
            [$userId]
        )->fetchColumn();
    }

    /** تأكيد استلام/قراءة إشعار وتسجيل IP */
    public static function markNotificationRead(int $notificationId, ?int $userId = null): void
    {
        $uid = $userId ?? Auth::id();
        if (!$uid) {
            return;
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        Database::query(
            "UPDATE notifications SET is_read = 1, read_at = NOW(), read_ip = ? WHERE id = ? AND user_id = ?",
            [$ip, $notificationId, $uid]
        );
        Database::query(
            "INSERT INTO notification_read_receipts (notification_id, user_id, read_ip) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE read_at = NOW(), read_ip = VALUES(read_ip)",
            [$notificationId, $uid, $ip]
        );
    }

    public static function audit(string $action, string $entity = '', $entityId = null, string $details = '', ?int $userId = null): void
    {
        Database::query(
            "INSERT INTO audit_log (user_id, action, entity, entity_id, details) VALUES (?,?,?,?,?)",
            [$userId ?? Auth::id(), $action, $entity, $entityId, $details]
        );
    }

    /** إضافة سجل لطابور المزامنة (النمط الهجين أوف لاين/أون لاين) */
    public static function queueSync(string $entity, $entityId, string $action, array $payload = []): void
    {
        Database::query(
            "INSERT INTO sync_queue (entity, entity_id, action, payload) VALUES (?,?,?,?)",
            [$entity, $entityId, $action, json_encode($payload, JSON_UNESCAPED_UNICODE)]
        );
    }

    public static function e(?string $v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }

    /**
     * إزالة كلمة "طاولة" أو "طلب" من بداية الاسم لتجنب التكرار في الواجهة
     * مثال: "طاولة 2" → "2"، "طلب ORD-..." → "ORD-..."
     */
    public static function cleanName(?string $v): string
    {
        $v = (string)$v;
        $v = preg_replace('/^(طاولة|طلب)\s+/u', '', $v);
        return $v;
    }
}
