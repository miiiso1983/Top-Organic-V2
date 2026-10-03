<?php
namespace App\Core\Push;

use App\Core\{Database, Settings};
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;

/**
 * إشعارات Web Push للمتصفح عبر مكتبة minishlink/web-push (VAPID).
 * المفاتيح تُولّد مرة وتُخزن في الإعدادات: push_vapid_public / push_vapid_private / push_vapid_subject
 */
final class WebPushService
{
    public static function isConfigured(): bool
    {
        return class_exists(WebPush::class)
            && (string)Settings::get('push_vapid_public', '') !== ''
            && (string)Settings::get('push_vapid_private', '') !== '';
    }

    /** توليد المفاتيح عند الحاجة — تعيد المفتاح العام (لواجهة الاشتراك) */
    public static function vapidPublic(): string
    {
        self::ensureVapidKeys();
        return (string)Settings::get('push_vapid_public', '');
    }

    public static function ensureVapidKeys(): void
    {
        if ((string)Settings::get('push_vapid_public', '') !== '' && (string)Settings::get('push_vapid_private', '') !== '') {
            return;
        }
        if (!class_exists(VAPID::class)) return;
        $keys = VAPID::createVapidKeys();
        Settings::set('push_vapid_public', $keys['publicKey']);
        Settings::set('push_vapid_private', $keys['privateKey']);
        if ((string)Settings::get('push_vapid_subject', '') === '') {
            Settings::set('push_vapid_subject', 'mailto:admin@toporganic.rest');
        }
    }

    /** إرسال لكل اشتراكات المتصفح لمستخدم — تعيد عدد الناجحة */
    public static function sendToUser(int $userId, string $title, string $body = '', ?string $link = null): int
    {
        if (!self::isConfigured()) return 0;
        $subs = Database::query(
            "SELECT * FROM push_subscriptions WHERE user_id = ? AND is_active = 1 AND platform = 'web'",
            [$userId]
        )->fetchAll();
        if (!$subs) return 0;

        $webPush = new WebPush(['VAPID' => [
            'subject' => (string)Settings::get('push_vapid_subject', 'mailto:admin@toporganic.rest'),
            'publicKey' => (string)Settings::get('push_vapid_public'),
            'privateKey' => (string)Settings::get('push_vapid_private'),
        ]]);
        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'link' => $link ?: '/notifications',
            'icon' => '/assets/icons/icon-192.png',
        ], JSON_UNESCAPED_UNICODE);

        $sent = 0;
        foreach ($subs as $s) {
            if (empty($s['endpoint']) || empty($s['p256dh']) || empty($s['auth'])) continue;
            try {
                $subscription = Subscription::create([
                    'endpoint' => $s['endpoint'],
                    'keys' => ['p256dh' => $s['p256dh'], 'auth' => $s['auth']],
                ]);
                $report = $webPush->sendOneNotification($subscription, $payload);
                if ($report->isSuccess()) {
                    $sent++;
                } else {
                    // 404/410 = اشتراك منتهي — عطّله
                    $code = $report->getResponse() ? $report->getResponse()->getStatusCode() : 0;
                    if (in_array($code, [404, 410], true)) {
                        Database::query("UPDATE push_subscriptions SET is_active = 0 WHERE id = ?", [(int)$s['id']]);
                    }
                }
            } catch (\Throwable $e) {
                error_log('WebPush send failed: ' . $e->getMessage());
            }
        }
        return $sent;
    }
}
