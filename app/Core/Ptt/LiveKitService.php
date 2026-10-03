<?php
namespace App\Core\Ptt;

use App\Core\{Database, Settings};

/**
 * تكامل LiveKit للبث الصوتي المباشر.
 * توليد JWT (HS256) يدوياً — بلا مكتبات خارجية.
 * الإعدادات: livekit_url / livekit_api_key / livekit_api_secret
 */
final class LiveKitService
{
    public static function isConfigured(): bool
    {
        return (string)Settings::get('livekit_url', '') !== ''
            && (string)Settings::get('livekit_api_key', '') !== ''
            && (string)Settings::get('livekit_api_secret', '') !== '';
    }

    public static function wsUrl(): string
    {
        return (string)Settings::get('livekit_url', '');
    }

    public static function roomName(int $channelId): string
    {
        return 'ptt-channel-' . $channelId;
    }

    /**
     * توكن دخول غرفة — JWT بمنح LiveKit القياسية.
     * canPublish: الباث (Walkie-Talkie ميكروفون) / المستمع: اشتراك فقط.
     */
    public static function token(int $channelId, int $userId, string $name, bool $canPublish, int $ttlSec = 7200): string
    {
        $now = time();
        $header = self::b64(['alg' => 'HS256', 'typ' => 'JWT']);
        $claims = self::b64([
            'iss' => (string)Settings::get('livekit_api_key'),
            'sub' => 'u' . $userId,
            'name' => $name,
            'iat' => $now,
            'nbf' => $now - 10,
            'exp' => $now + $ttlSec,
            'video' => [
                'roomJoin' => true,
                'room' => self::roomName($channelId),
                'canPublish' => $canPublish,
                'canSubscribe' => true,
                'canPublishData' => true,
            ],
            'metadata' => json_encode(['user_id' => $userId], JSON_UNESCAPED_UNICODE),
        ]);
        $unsigned = $header . '.' . $claims;
        $sig = hash_hmac('sha256', $unsigned, (string)Settings::get('livekit_api_secret'), true);
        return $unsigned . '.' . rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');
    }

    // ===== جلسات البث (للتدقيق والعرض) =====
    public static function startSession(int $channelId, int $userId): int
    {
        $active = self::activeSession($channelId);
        if ($active) return (int)$active['id'];
        Database::query(
            "INSERT INTO ptt_live_sessions (channel_id, room_name, started_by, started_at)
             VALUES (?,?,?,NOW())",
            [$channelId, self::roomName($channelId), $userId]
        );
        return (int)Database::lastInsertId();
    }

    public static function endSession(int $channelId): void
    {
        Database::query(
            "UPDATE ptt_live_sessions SET ended_at = NOW() WHERE channel_id = ? AND ended_at IS NULL",
            [$channelId]
        );
    }

    public static function activeSession(int $channelId): ?array
    {
        return Database::query(
            "SELECT * FROM ptt_live_sessions WHERE channel_id = ? AND ended_at IS NULL ORDER BY id DESC LIMIT 1",
            [$channelId]
        )->fetch() ?: null;
    }

    private static function b64(array $data): string
    {
        return rtrim(strtr(base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
    }
}
