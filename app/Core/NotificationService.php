<?php
namespace App\Core;

/**
 * إرسال الإشعارات عبر Email/WhatsApp مع دعم طابور الانتظار
 */
class NotificationService
{
    public static function sendEmail(string $to, string $subject, string $body, ?int $userId = null): array
    {
        $method = self::setting('email_method', 'queue');
        if ($method === 'php_mail') {
            $headers = "Content-Type: text/plain; charset=UTF-8\r\n";
            $ok = @mail($to, $subject, $body, $headers);
            return $ok
                ? ['success' => true, 'queued' => false, 'error' => null]
                : ['success' => false, 'queued' => false, 'error' => 'فشل إرسال البريد عبر php_mail'];
        }
        self::queue('email', $to, $subject, $body, $userId);
        return ['success' => false, 'queued' => true, 'error' => 'تم إضافتها لطابور البريد'];
    }

    public static function sendWhatsApp(string $to, string $message, ?int $userId = null): array
    {
        $url = self::setting('whatsapp_api_url', '');
        $token = self::setting('whatsapp_token', '');
        if ($url && $token) {
            return self::callHttpApi($url, $token, $to, $message);
        }
        self::queue('whatsapp', $to, '', $message, $userId);
        return ['success' => false, 'queued' => true, 'error' => 'لم يُضبط API الواتساب؛ تم إضافتها للطابور'];
    }

    public static function sendSms(string $to, string $message, ?int $userId = null): array
    {
        $url = self::setting('sms_api_url', '');
        $token = self::setting('sms_token', '');
        if ($url && $token) {
            return self::callHttpApi($url, $token, $to, $message);
        }
        self::queue('sms', $to, '', $message, $userId);
        return ['success' => false, 'queued' => true, 'error' => 'لم يُضبط API SMS؛ تم إضافتها للطابور'];
    }

    public static function processQueue(int $limit = 50): array
    {
        $stats = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];
        $rows = Database::query(
            "SELECT * FROM notification_queue WHERE channel IN ('email','whatsapp','sms') AND status = 'pending' ORDER BY id LIMIT ?",
            [$limit]
        )->fetchAll();

        foreach ($rows as $row) {
            $stats['processed']++;
            $channel = $row['channel'];
            $to = (string)$row['recipient'];
            $body = (string)$row['body'];
            $subject = (string)($row['subject'] ?? '');
            $userId = $row['user_id'] ? (int)$row['user_id'] : null;

            $result = ['success' => false, 'error' => 'قناة غير معروفة'];
            if ($channel === 'email') {
                $result = self::sendEmailDirect($to, $subject, $body);
            } elseif ($channel === 'whatsapp' || $channel === 'sms') {
                $url = self::setting($channel . '_api_url', '');
                $token = self::setting($channel . '_token', '');
                if (!$url || !$token) {
                    $result = ['success' => false, 'error' => 'لم يُضبط API ' . $channel];
                } else {
                    $result = self::callHttpApi($url, $token, $to, $body);
                }
            }

            if (!empty($result['queued'])) {
                $stats['skipped']++;
                continue;
            }

            self::markQueue((int)$row['id'], $result['success'] ? 'sent' : 'failed', $result['error'] ?? null);
            if ($result['success']) {
                $stats['sent']++;
            } else {
                $stats['failed']++;
            }
        }
        return $stats;
    }

    private static function sendEmailDirect(string $to, string $subject, string $body): array
    {
        $headers = "Content-Type: text/plain; charset=UTF-8\r\n";
        $ok = @mail($to, $subject, $body, $headers);
        return $ok
            ? ['success' => true, 'queued' => false, 'error' => null]
            : ['success' => false, 'queued' => false, 'error' => 'فشل إرسال البريد المباشر'];
    }

    private static function callHttpApi(string $url, string $token, string $to, string $message): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['to' => $to, 'message' => $message, 'token' => $token]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['success' => false, 'queued' => false, 'error' => 'cURL: ' . $curlError];
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            return ['success' => false, 'queued' => false, 'error' => 'HTTP ' . $httpCode . ' - ' . substr((string)$response, 0, 200)];
        }

        $json = json_decode((string)$response, true);
        if (is_array($json) && isset($json['success']) && !$json['success']) {
            $error = $json['message'] ?? $json['error'] ?? 'فشل غير معروف من API';
            return ['success' => false, 'queued' => false, 'error' => $error];
        }

        return ['success' => true, 'queued' => false, 'error' => null];
    }

    private static function markQueue(int $id, string $status, ?string $error = null): void
    {
        Database::query(
            "UPDATE notification_queue SET status = ?, error = ?, sent_at = IF(? = 'sent', NOW(), sent_at) WHERE id = ?",
            [$status, $error, $status, $id]
        );
    }

    public static function queue(string $channel, string $recipient, ?string $subject, string $body, ?int $userId = null): void
    {
        Database::query(
            "INSERT INTO notification_queue (channel, user_id, recipient, subject, body, status) VALUES (?,?,?,?,?,'pending')",
            [$channel, $userId, $recipient, $subject, $body]
        );
    }

    public static function sendToUser(int $userId, string $title, string $body, ?string $link = null, array $channels = ['push','email','whatsapp']): array
    {
        $result = ['sent' => 0, 'queued' => 0, 'failed' => 0, 'errors' => []];
        $u = Database::query(
            "SELECT u.email, u.phone, u.notify_email, u.notify_whatsapp, u.notify_sms, e.phone AS emp_phone, e.email AS emp_email
             FROM users u
             LEFT JOIN employees e ON e.user_id = u.id
             WHERE u.id = ? AND u.is_active = 1",
            [$userId]
        )->fetch();
        if (!$u) return $result;
        $email = $u['email'] ?: $u['emp_email'];
        $phone = $u['phone'] ?: $u['emp_phone'];
        $fullBody = $body . ($link ? "\n" . $link : '');
        if (in_array('push', $channels, true)) {
            Helpers::notify($userId, $title, $body, $link);
            \App\Core\Workflow\PushService::queue($userId, $title, $body, $link);
            $result['queued']++;
        }
        if (in_array('email', $channels, true) && $email && $u['notify_email']) {
            $r = self::sendEmail($email, $title, $fullBody, $userId);
            self::aggregateResult($result, $r);
        }
        if (in_array('whatsapp', $channels, true) && $phone && $u['notify_whatsapp']) {
            $r = self::sendWhatsApp($phone, $fullBody, $userId);
            self::aggregateResult($result, $r);
        }
        if (in_array('sms', $channels, true) && $phone && $u['notify_sms']) {
            $r = self::sendSms($phone, $fullBody, $userId);
            self::aggregateResult($result, $r);
        }
        return $result;
    }

    private static function aggregateResult(array &$result, array $r): void
    {
        if (!empty($r['success'])) {
            $result['sent']++;
        } elseif (!empty($r['queued'])) {
            $result['queued']++;
        } else {
            $result['failed']++;
        }
        if (!empty($r['error'])) {
            $result['errors'][] = $r['error'];
        }
    }

    private static function setting(string $key, string $default = ''): string
    {
        $row = Database::query("SELECT svalue FROM settings WHERE skey = ?", [$key])->fetch();
        return (string)($row['svalue'] ?? $default);
    }

    // ==================== أحداث دورة المشتريات والمخزون ====================

    public static function mrCreated(int $mrId, int $requesterId, string $mrNo): void
    {
        self::sendToUser($requesterId, 'تم إنشاء طلب مواد', "طلب المواد $mrNo تم إنشاؤه وإرساله للموافقة", '/material-requests/' . $mrId);
    }

    public static function mrApproved(int $mrId, int $requesterId, string $mrNo): void
    {
        self::sendToUser($requesterId, 'تم اعتماد طلب مواد', "طلب المواد $mrNo تم اعتماده", '/material-requests/' . $mrId);
    }

    public static function mrRejected(int $mrId, int $requesterId, string $mrNo, string $reason): void
    {
        self::sendToUser($requesterId, 'تم رفض طلب مواد', "طلب المواد $mrNo تم رفضه" . ($reason ? " - $reason" : ''), '/material-requests/' . $mrId);
    }

    public static function poIssued(int $poId, int $requesterId, string $poNo): void
    {
        self::sendToUser($requesterId, 'تم إصدار أمر شراء', "أمر الشراء $poNo تم إصداره", '/purchasing/orders/' . $poId);
    }

    public static function receiptDone(int $receiptId, int $requesterId, string $receiptNo, string $itemName): void
    {
        self::sendToUser($requesterId, 'تم استلام مادة', "تم استلام $itemName في سند الاستلام $receiptNo", '/purchasing/receipts');
    }

    public static function transferPrepared(int $transferId, int $requesterId, string $transferNo): void
    {
        self::sendToUser($requesterId, 'تم تجهيز المناقلة', "المناقلة $transferNo تم تجهيزها للتسليم", '/material-requests/transfer/' . $transferId . '/signature');
    }

    public static function transferDelivered(int $transferId, int $requesterId, string $transferNo): void
    {
        self::sendToUser($requesterId, 'تم تسليم المناقلة', "تم تسليم المواد عبر المناقلة $transferNo", '/material-requests/transfer/' . $transferId . '/signature');
    }

    public static function invoiceCreated(int $invoiceId, int $requesterId, string $invoiceNo, float $amount): void
    {
        self::sendToUser($requesterId, 'تم إنشاء فاتورة شراء', "فاتورة $invoiceNo بمبلغ $amount تم إنشاؤها وإرسالها للحسابات", '/purchasing/invoices/' . $invoiceId);
    }

    public static function paymentReady(int $invoiceId, int $requesterId, string $invoiceNo, float $remaining): void
    {
        self::sendToUser($requesterId, 'فاتورة جاهزة للدفع', "فاتورة $invoiceNo بمبلغ متبقي $remaining جاهزة للدفع", '/purchasing/invoices/' . $invoiceId);
    }
}
