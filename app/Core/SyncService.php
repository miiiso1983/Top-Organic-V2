<?php
namespace App\Core;

/**
 * خدمة المزامنة الهجينة: ترسل عمليات الفرع المحلية إلى الخادم المركزي (السحابة)
 * عبر HTTP، وتستقبل ما لدى المركز من عمليات معلّقة.
 * تعمل بشكل آمن حتى بدون اتصال إنترنت: أي فشل بالاتصال لا يوقف عمل النظام محلياً.
 */
class SyncService
{
    private const BATCH_SIZE = 200;

    public static function isEnabled(): bool
    {
        return (bool) (App::config('sync')['enabled'] ?? false)
            && (string) (App::config('sync')['remote_url'] ?? '') !== '';
    }

    /** حالة المزامنة لعرضها في واجهة الإدارة */
    public static function status(): array
    {
        $pending = (int) Database::query("SELECT COUNT(*) AS c FROM sync_queue WHERE synced = 0")->fetch()['c'];
        $last = Database::query("SELECT MAX(synced_at) AS t FROM sync_queue WHERE synced = 1")->fetch();
        return [
            'enabled'     => self::isEnabled(),
            'remote_url'  => App::config('sync')['remote_url'] ?? '',
            'pending'     => $pending,
            'last_sync_at'=> $last['t'] ?? null,
        ];
    }

    /**
     * دفع العمليات المعلّقة محلياً إلى الخادم المركزي، واستقبال عمليات المركز المعلّقة.
     * يعيد مصفوفة بنتيجة العملية (نجاح/فشل + عدد ما تم إرساله/استقباله).
     */
    public static function push(): array
    {
        $cfg = App::config('sync');
        if (!self::isEnabled()) {
            return ['ok' => false, 'error' => 'المزامنة السحابية غير مفعّلة في الإعدادات'];
        }

        $rows = Database::query(
            "SELECT id, entity, entity_id, action, payload FROM sync_queue WHERE synced = 0 ORDER BY id LIMIT ?",
            [self::BATCH_SIZE]
        )->fetchAll();

        $operations = array_map(function ($r) {
            return [
                'entity'    => $r['entity'],
                'entity_id' => (int) $r['entity_id'],
                'action'    => $r['action'],
                'payload'   => json_decode($r['payload'] ?? '{}', true) ?: [],
            ];
        }, $rows);

        $result = self::request(rtrim($cfg['remote_url'], '/') . '/api/sync', [
            'operations' => $operations,
        ], $cfg['remote_key'] ?? '', (int) ($cfg['timeout'] ?? 15));

        if (!$result['ok']) {
            return $result;
        }

        if ($rows) {
            $ids = array_column($rows, 'id');
            $in = implode(',', array_fill(0, count($ids), '?'));
            Database::query("UPDATE sync_queue SET synced = 1, synced_at = NOW() WHERE id IN ($in)", $ids);
        }

        // العمليات المعلّقة لدى المركز تُسجَّل في سجل وارد منفصل للمراجعة اليدوية
        // (لا تُطبَّق تلقائياً على قاعدة البيانات المحلية تفادياً لتعارض البيانات)
        $remotePending = $result['data']['pending'] ?? [];
        foreach ($remotePending as $op) {
            Database::query(
                "INSERT INTO sync_incoming (entity, entity_id, action, payload) VALUES (?,?,?,?)",
                [
                    (string) ($op['entity'] ?? 'unknown'),
                    (int) ($op['entity_id'] ?? 0),
                    in_array($op['action'] ?? '', ['insert', 'update', 'delete'], true) ? $op['action'] : 'insert',
                    json_encode($op['payload'] ?? [], JSON_UNESCAPED_UNICODE),
                ]
            );
        }

        return [
            'ok'             => true,
            'pushed'         => count($rows),
            'remote_pending' => count($remotePending),
        ];
    }

    /** تنفيذ طلب HTTP بسيط عبر cURL مع معالجة آمنة للأخطاء (لا يوقف تشغيل النظام أوفلاين) */
    private static function request(string $url, array $body, string $key, int $timeout): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'error' => 'امتداد cURL غير متوفر في PHP'];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-Sync-Key: ' . $key,
            ],
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
        ]);

        $response = curl_exec($ch);
        $errNo = curl_errno($ch);
        $errMsg = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errNo !== 0) {
            return ['ok' => false, 'error' => 'تعذر الاتصال بالخادم المركزي: ' . $errMsg];
        }
        if ($status !== 200) {
            return ['ok' => false, 'error' => "الخادم المركزي أعاد الحالة $status"];
        }

        $data = json_decode((string) $response, true);
        if (!is_array($data) || empty($data['ok'])) {
            return ['ok' => false, 'error' => 'استجابة غير صالحة من الخادم المركزي'];
        }

        return ['ok' => true, 'data' => $data];
    }
}
