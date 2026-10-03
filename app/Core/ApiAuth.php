<?php
namespace App\Core;

/**
 * مصادقة API لتطبيق الموبايل عبر رموز (Tokens) موقعة HMAC
 */
class ApiAuth
{
    private static ?array $user = null;

    public static function issueToken(int $userId): string
    {
        $payload = base64_encode(json_encode([
            'uid' => $userId,
            'exp' => time() + 60 * 60 * 24 * 30, // صلاحية 30 يوماً
        ]));
        $sig = hash_hmac('sha256', $payload, App::config('api_secret'));
        return $payload . '.' . $sig;
    }

    public static function check(): bool
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/Bearer\s+(.+)/', $header, $m)) {
            return false;
        }
        return self::validateToken($m[1]) !== null;
    }

    public static function validateToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) return null;

        [$payload, $sig] = $parts;
        $expected = hash_hmac('sha256', $payload, App::config('api_secret'));
        if (!hash_equals($expected, $sig)) return null;

        $data = json_decode(base64_decode($payload), true);
        if (!$data || ($data['exp'] ?? 0) < time()) return null;

        $user = Database::query(
            "SELECT u.*, r.name AS role_name FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.id = ? AND u.is_active = 1",
            [$data['uid']]
        )->fetch();

        if (!$user) return null;
        self::$user = $user;
        return $user;
    }

    public static function user(): ?array
    {
        return self::$user;
    }

    public static function id(): ?int
    {
        return self::$user['id'] ?? null;
    }
}
