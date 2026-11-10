<?php
declare(strict_types=1);
namespace App\Core;

/** Request metadata only: never captures request bodies, cookies, tokens or query strings. */
final class SystemActivityService
{
    private static ?array $context = null;
    private static ?int $actor = null;
    private static ?string $outcome = null;

    public static function begin(): void
    {
        if (self::$context !== null || PHP_SAPI === 'cli') return;
        $api = str_starts_with((string)($_SERVER['REQUEST_URI'] ?? ''), '/api/');
        self::$context = ['route' => '[unmatched]', 'operation' => 'unmatched', 'entity_id' => null,
            'channel' => $api ? 'mobile_api' : 'web', 'started' => microtime(true),
            'actor' => $api ? null : Auth::id(), 'branch' => $api ? null : Auth::branchId()];
        register_shutdown_function([self::class, 'finish']);
    }

    public static function route(string $path, $handler, array $params): void
    {
        if (self::$context === null) return;
        self::$context['route'] = $path;
        self::$context['operation'] = is_array($handler)
            ? basename(str_replace('\\', '/', $handler[0])) . '.' . $handler[1] : $path;
        foreach ($params as $value) {
            if (ctype_digit((string)$value)) { self::$context['entity_id'] = (int)$value; break; }
        }
    }

    public static function identify(int $id): void { self::$actor = $id; }
    public static function outcome(string $value): void
    {
        if (in_array($value, ['rejected', 'accepted'], true)) self::$outcome = $value;
    }

    public static function finish(): void
    {
        if (self::$context === null) return;
        $c = self::$context;
        self::$context = null;
        try {
            $apiUser = ApiAuth::user();
            $actor = self::$actor ?? ($c['channel'] === 'mobile_api' ? ($apiUser['id'] ?? null) : Auth::id()) ?? $c['actor'];
            $branch = $c['channel'] === 'mobile_api' ? ($apiUser['branch_id'] ?? null) : (Auth::branchId() ?? $c['branch']);
            $status = http_response_code() ?: 200;
            $last = error_get_last();
            if ($last && in_array($last['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) $status = 500;
            $result = $status >= 500 ? 'error' : ($status >= 400 ? 'rejected' : (self::$outcome ?? 'response'));
            $db = Database::connect();
            // An unfinished business transaction must neither swallow the request event nor be committed by it.
            if ($db->inTransaction()) {
                $cfg = App::config('db');
                $db = new \PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $cfg['host'], $cfg['port'], $cfg['name'], $cfg['charset']), $cfg['user'], $cfg['pass'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
                $db->exec("SET time_zone = '+03:00'");
            }
            $stmt = $db->prepare('INSERT INTO system_activity_events (user_id, branch_id, channel, action, entity_id, route, method, result, http_status, ip, device, duration_ms) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
            $ua = strtolower((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
            $device = match(true) {str_contains($ua, 'iphone'), str_contains($ua, 'ipad') => 'iOS', str_contains($ua, 'android') => 'Android', str_contains($ua, 'windows') => 'Windows', str_contains($ua, 'mac') => 'Mac', str_contains($ua, 'dart') => 'Mobile', default => 'Other'};
            $stmt->execute([$actor, $branch, $c['channel'], $c['operation'], $c['entity_id'], $c['route'], substr((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'), 0, 10), $result, $status, substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45), $device, (int)round((microtime(true) - $c['started']) * 1000)]);
        } catch (\Throwable $e) {
            // Logging failure must not break attendance, checkout or other operational requests.
            error_log('System activity event could not be recorded (' . get_class($e) . ')');
        }
    }

    public static function safeDetails(?string $value, ?string $field = null): string
    {
        if ($field && preg_match('/password|secret|token|credential|authorization|cookie|selfie|face_image|private_key/i', $field)) return '[بيانات محجوبة]';
        $text = (string)$value;
        if (preg_match('/-----BEGIN [^-]*PRIVATE KEY-----|Bearer\s+|(?:password|password_hash|secret|token|api_key|authorization|cookie)["\x27]?\s*[:=]/i', $text)) return '[تفاصيل حساسة محجوبة]';
        return $text;
    }
}
