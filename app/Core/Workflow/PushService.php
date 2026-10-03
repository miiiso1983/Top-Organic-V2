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
        $existing = Database::query(
            "SELECT id FROM push_subscriptions WHERE user_id = ? AND endpoint = ? LIMIT 1",
            [$userId, $endpoint]
        )->fetch();
        if ($existing) {
            Database::query(
                "UPDATE push_subscriptions SET p256dh = ?, auth = ?, is_active = 1, user_agent = ? WHERE id = ?",
                [
                    substr((string)($keys['p256dh'] ?? ''), 0, 255) ?: null,
                    substr((string)($keys['auth'] ?? ''), 0, 255) ?: null,
                    substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
                    (int)$existing['id'],
                ]
            );
            return;
        }
        Database::query(
            "INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth, platform, user_agent)
             VALUES (?,?,?,?,?,?)",
            [
                $userId, $endpoint,
                substr((string)($keys['p256dh'] ?? ''), 0, 255) ?: null,
                substr((string)($keys['auth'] ?? ''), 0, 255) ?: null,
                in_array($data['platform'] ?? '', ['web','android','ios'], true) ? $data['platform'] : 'web',
                substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
            ]
        );
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
            return (bool)Database::query(
                "SELECT 1 FROM push_subscriptions WHERE user_id = ? AND is_active = 1 LIMIT 1",
                [$userId]
            )->fetch();
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
