<?php
namespace App\Core\Push;

use App\Core\Database;

/**
 * عامل إرسال طابور الإشعارات (notification_queue قناة push):
 * يوجّه كل رسالة حسب منصة الاشتراك — FCM للموبايل، Web Push للمتصفح (المرحلة ج).
 */
final class PushSender
{
    /** معالجة دفعة من الطابور — تعيد إحصائية */
    public static function processQueue(int $limit = 50): array
    {
        $stats = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];
        $rows = Database::query(
            "SELECT * FROM notification_queue WHERE channel = 'push' AND status = 'pending' ORDER BY id LIMIT ?",
            [$limit]
        )->fetchAll();

        foreach ($rows as $row) {
            $stats['processed']++;
            $userId = (int)$row['user_id'];
            if (!$userId) { self::mark((int)$row['id'], 'failed', 'بلا مستخدم'); $stats['skipped']++; continue; }

            // توكنات الموبايل (FCM): endpoint = FCM registration token
            $tokens = Database::query(
                "SELECT endpoint FROM push_subscriptions
                 WHERE user_id = ? AND is_active = 1 AND platform IN ('android','ios')",
                [$userId]
            )->fetchAll(\PDO::FETCH_COLUMN);

            $sentAny = false;
            $lastError = null;

            if ($tokens && FcmService::isConfigured()) {
                // بيانات إضافية (data payload) تمكّن تطبيق الموبايل من فتح الواجهة المناسبة
                // تلقائياً عند الضغط على الإشعار (Deep Linking)
                $data = array_filter([
                    'entity_type' => $row['entity_type'] ?? null,
                    'entity_id' => isset($row['entity_id']) && $row['entity_id'] !== null ? (string)$row['entity_id'] : null,
                    'action_type' => $row['action_type'] ?? null,
                ], fn($v) => $v !== null && $v !== '');
                $res = FcmService::send($tokens, (string)$row['subject'], (string)$row['body'], !empty($row['link']) ? (string)$row['link'] : null, $data);
                $sentAny = $res['sent'] > 0;
                $lastError = $res['errors'][0] ?? null;
            }

            // Web Push (المرحلة ج): يُعاد بناؤه عند توفر الخدمة
            if (class_exists(WebPushService::class) && WebPushService::isConfigured()) {
                $webSent = WebPushService::sendToUser($userId, (string)$row['subject'], (string)$row['body']);
                $sentAny = $sentAny || $webSent > 0;
            }

            if ($sentAny) {
                self::mark((int)$row['id'], 'sent');
                $stats['sent']++;
            } else {
                // لا اشتراكات فعّالة إطلاقاً (لا موبايل ولا ويب) — فشل نهائي، لا فائدة من إعادة المحاولة
                if (!$tokens && !self::hasWebSubs($userId)) {
                    self::mark((int)$row['id'], 'failed', 'لا اشتراكات فعّالة');
                    $stats['failed']++;
                    continue;
                }
                // توجد اشتراكات لكن القناة المناسبة لها غير مُهيّأة بعد من الإدارة
                // (FCM لتوكنات الموبايل، أو VAPID لاشتراكات الويب) — أبقِها pending
                // حتى تُضبط الإعدادات بدل تفويتها نهائياً بعلامة "فشل" مضللة.
                $mobileNeedsFcm = $tokens && !FcmService::isConfigured();
                $webNeedsVapid = self::hasWebSubs($userId) && !(class_exists(WebPushService::class) && WebPushService::isConfigured());
                if ($mobileNeedsFcm || $webNeedsVapid) {
                    $stats['skipped']++; // إعدادات لم تُضبط بعد — أبقِها pending
                } else {
                    self::mark((int)$row['id'], 'failed', (string)$lastError);
                    $stats['failed']++;
                }
            }
        }
        return $stats;
    }

    private static function hasWebSubs(int $userId): bool
    {
        return (bool)Database::query(
            "SELECT 1 FROM push_subscriptions WHERE user_id = ? AND is_active = 1 AND platform = 'web' LIMIT 1",
            [$userId]
        )->fetch();
    }

    private static function mark(int $id, string $status, ?string $error = null): void
    {
        Database::query(
            "UPDATE notification_queue SET status = ?, error = ?, sent_at = IF(? = 'sent', NOW(), sent_at) WHERE id = ?",
            [$status, $error, $status, $id]
        );
    }
}
