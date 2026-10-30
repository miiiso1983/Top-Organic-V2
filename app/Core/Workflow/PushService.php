<?php
namespace App\Core\Workflow;

use App\Core\Database;

/**
 * إشعارات Push — تخزين الاشتراكات وطابور الإرسال.
 * ملاحظة تشغيلية: الإرسال الفعلي (Web Push/VAPID أو FCM) يتطلب تفعيل مكتبة إرسال —
 * حتى يتم ذلك تُجمع الرسائل في notification_queue بقناة push ولا تضيع.
 */
final class PushService
{
    /** تسجيل اشتراك جديد (idempotent على endpoint) */
    public static function subscribe(int $userId, array $data): void
    {
        $endpoint = substr((string)($data['endpoint'] ?? ''), 0, 2000);
        if ($endpoint === '') throw new \RuntimeException('endpoint مطلوب');
        $keys = (array)($data['keys'] ?? []);
        $platform = in_array($data['platform'] ?? '', ['web','android','ios'], true) ? $data['platform'] : 'web';
        $db = Database::connect();
        // Serialize even the first registration (there is no endpoint unique index).
        $lock = $db->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql' ? hash('sha256', $endpoint) : null;
        if ($lock && !(int)Database::query('SELECT GET_LOCK(?, 10)', [$lock])->fetchColumn()) {
            throw new \RuntimeException('تعذر تحديث اشتراك الإشعارات، أعد المحاولة');
        }
        $ownTransaction = !$db->inTransaction();
        try {
            if ($ownTransaction) $db->beginTransaction();
            $existing = Database::query('SELECT id FROM push_subscriptions WHERE user_id=? AND endpoint=? ORDER BY id DESC LIMIT 1', [$userId, $endpoint])->fetchColumn();
            // One device endpoint belongs to its currently signed-in account only.
            // Also collapse duplicate records created by concurrent old clients.
            Database::query('UPDATE push_subscriptions SET is_active=0 WHERE endpoint=?', [$endpoint]);
            $values = [
                substr((string)($keys['p256dh'] ?? ''), 0, 255) ?: null,
                substr((string)($keys['auth'] ?? ''), 0, 255) ?: null,
                $platform,
                substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
            ];
            if ($existing) {
                Database::query('UPDATE push_subscriptions SET p256dh=?, auth=?, platform=?, user_agent=?, is_active=1 WHERE id=?', [...$values, (int)$existing]);
            } else {
                Database::query('INSERT INTO push_subscriptions (p256dh,auth,platform,user_agent,user_id,endpoint) VALUES (?,?,?,?,?,?)', [...$values, $userId, $endpoint]);
            }
            if ($ownTransaction) $db->commit();
        } catch (\Throwable $e) {
            if ($ownTransaction && $db->inTransaction()) $db->rollBack();
            throw $e;
        } finally {
            if ($lock) Database::query('SELECT RELEASE_LOCK(?)', [$lock]);
        }
    }

    /** Never guess ownership of old endpoints shared by multiple accounts.
     * A fresh authenticated registration safely claims them for the current user.
     */
    public static function subscriptions(int $userId, array $platforms = ['web', 'android', 'ios']): array
    {
        if (!$platforms) return [];
        $marks = implode(',', array_fill(0, count($platforms), '?'));
        return Database::query("SELECT s.* FROM push_subscriptions s
            WHERE s.user_id=? AND s.is_active=1 AND s.platform IN ($marks)
            AND NOT EXISTS (SELECT 1 FROM push_subscriptions other
                WHERE other.endpoint=s.endpoint AND other.is_active=1 AND other.user_id<>s.user_id)",
            [$userId, ...$platforms])->fetchAll();
    }

    public static function unsubscribe(int $userId, string $endpoint): void
    {
        Database::query(
            "UPDATE push_subscriptions SET is_active = 0 WHERE user_id = ? AND endpoint = ?",
            [$userId, $endpoint]
        );
    }

    /** هل للمستخدم اشتراك فعّال؟ (لقرارات الإرسال) */
    public static function hasSubscription(int $userId): bool
    {
        try {
            return (bool)self::subscriptions($userId);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * إدراج رسالة push في الطابور (تُستهلك لاحقاً بواسطة مرسل web-push/FCM)
     * $entityType/$entityId/$actionType تُمرَّر ضمن بيانات FCM (data payload) ليتمكن
     * تطبيق الموبايل من فتح الواجهة المناسبة تلقائياً عند الضغط على الإشعار (Deep Linking).
     */
    public static function queue(
        int $userId,
        string $title,
        string $body = '',
        ?string $link = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $actionType = null
    ): void {
        if (!self::hasSubscription($userId)) return;
        try {
            Database::query(
                "INSERT INTO notification_queue (channel, user_id, recipient, subject, body, link, entity_type, entity_id, action_type)
                 VALUES ('push', ?, ?, ?, ?, ?, ?, ?, ?)",
                [$userId, (string)$userId, $title, $body, $link, $entityType, $entityId, $actionType]
            );
        } catch (\Throwable $e) {
            error_log('PushService::queue failed: ' . $e->getMessage());
        }
    }
}
