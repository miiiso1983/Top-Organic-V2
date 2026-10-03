<?php
namespace App\Core\Push;

use App\Core\Settings;

/**
 * إرسال إشعارات Firebase Cloud Messaging (FCM).
 * يدعم HTTP v1 (Service Account — OAuth2 JWT RS256 يدوي بلا مكتبات)
 * وLegacy API (Server Key) كاحتياط.
 *
 * الإعدادات: fcm_service_account_json | fcm_server_key
 */
final class FcmService
{
    public static function isConfigured(): bool
    {
        return self::serviceAccount() !== null || (string)Settings::get('fcm_server_key', '') !== '';
    }

    public static function serviceAccount(): ?array
    {
        $json = (string)Settings::get('fcm_service_account_json', '');
        if ($json === '') return null;
        $sa = json_decode($json, true);
        return (is_array($sa) && !empty($sa['project_id']) && !empty($sa['private_key']) && !empty($sa['client_email'])) ? $sa : null;
    }

    /** إرسال لمجموعة توكنات — تعيد ['sent'=>n,'failed'=>n,'errors'=>[]] */
    public static function send(array $tokens, string $title, string $body = '', ?string $link = null, array $data = []): array
    {
        $tokens = array_values(array_unique(array_filter(array_map('strval', $tokens))));
        if (!$tokens) return ['sent' => 0, 'failed' => 0, 'errors' => ['لا توكنات']];
        $data = array_merge($data, array_filter(['link' => $link, 'title' => $title, 'body' => $body], fn($v) => $v !== null));

        $sa = self::serviceAccount();
        if ($sa) return self::sendV1($sa, $tokens, $title, $body, $data);
        if ((string)Settings::get('fcm_server_key', '') !== '') {
            return self::sendLegacy($tokens, $title, $body, $data);
        }
        return ['sent' => 0, 'failed' => count($tokens), 'errors' => ['FCM غير مضبوط']];
    }

    // ===== HTTP v1 =====
    private static function sendV1(array $sa, array $tokens, string $title, string $body, array $data): array
    {
        $accessToken = self::accessToken($sa);
        if (!$accessToken) return ['sent' => 0, 'failed' => count($tokens), 'errors' => ['فشل مصادقة OAuth2']];
        $url = 'https://fcm.googleapis.com/v1/projects/' . $sa['project_id'] . '/messages:send';
        $sent = 0; $failed = 0; $errors = [];
        foreach ($tokens as $token) {
            $payload = ['message' => [
                'token' => $token,
                'notification' => ['title' => $title, 'body' => $body],
                'data' => array_map('strval', $data),
                'android' => ['priority' => 'HIGH', 'notification' => ['sound' => 'default', 'channel_id' => 'ptt_channel']],
                'apns' => ['payload' => ['aps' => ['sound' => 'default', 'content-available' => 1]]],
            ]];
            $res = self::httpPost($url, $payload, ['Authorization: Bearer ' . $accessToken]);
            if ($res['code'] >= 200 && $res['code'] < 300) $sent++;
            else { $failed++; $errors[] = 'HTTP ' . $res['code'] . ': ' . mb_substr($res['body'], 0, 120); }
        }
        return ['sent' => $sent, 'failed' => $failed, 'errors' => array_slice($errors, 0, 3)];
    }

    /** OAuth2 access token من Service Account (JWT RS256 يدوي) مع تخزين مؤقت */
    private static function accessToken(array $sa): ?string
    {
        $cached = (string)Settings::get('fcm_access_token', '');
        $exp = (int)Settings::get('fcm_access_token_exp', 0);
        if ($cached !== '' && $exp > time() + 60) return $cached;

        $now = time();
        $header = self::b64(['alg' => 'RS256', 'typ' => 'JWT']);
        $claims = self::b64([
            'iss' => $sa['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]);
        $unsigned = $header . '.' . $claims;
        $key = openssl_pkey_get_private($sa['private_key']);
        if (!$key || !openssl_sign($unsigned, $sig, $key, OPENSSL_ALGO_SHA256)) return null;
        $jwt = $unsigned . '.' . rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');

        $res = self::httpPost('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ], [], true);
        $json = json_decode($res['body'], true);
        $token = $json['access_token'] ?? null;
        if ($token) {
            Settings::set('fcm_access_token', $token);
            Settings::set('fcm_access_token_exp', (string)($now + (int)($json['expires_in'] ?? 3600)));
        }
        return $token;
    }

    // ===== Legacy API =====
    private static function sendLegacy(array $tokens, string $title, string $body, array $data): array
    {
        $res = self::httpPost('https://fcm.googleapis.com/fcm/send', [
            'registration_ids' => $tokens,
            'notification' => ['title' => $title, 'body' => $body, 'sound' => 'default'],
            'data' => array_map('strval', $data),
            'priority' => 'high',
        ], ['Authorization: key=' . Settings::get('fcm_server_key')]);
        $json = json_decode($res['body'], true) ?: [];
        return [
            'sent' => (int)($json['success'] ?? 0),
            'failed' => (int)($json['failure'] ?? count($tokens)),
            'errors' => [],
        ];
    }

    // ===== أدوات =====
    private static function b64(array $data): string
    {
        return rtrim(strtr(base64_encode(json_encode($data, JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
    }

    private static function httpPost(string $url, array $payload, array $headers = [], bool $form = false): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => array_merge(
                [$form ? 'Content-Type: application/x-www-form-urlencoded' : 'Content-Type: application/json'],
                $headers
            ),
            CURLOPT_POSTFIELDS => $form ? http_build_query($payload) : json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 10,
        ]);
        $body = (string)curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['code' => $code, 'body' => $body];
    }
}
