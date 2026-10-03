<?php
namespace App\Core\Workflow;

use App\Core\Database;

/**
 * سجل التدقيق — يكتب في request_history مع العقدة والـ IP والجهاز.
 */
final class AuditLogger
{
    public static function log(int $requestId, string $action, ?int $nodeId, int $actorId, string $notes = ''): void
    {
        $actor = $actorId ? Database::query(
            "SELECT u.full_name, op.title AS position_title
             FROM users u
             LEFT JOIN employees e ON e.user_id = u.id
             LEFT JOIN org_positions op ON op.employee_id = e.id
             WHERE u.id = ?",
            [$actorId]
        )->fetch() : null;

        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        Database::query(
            "INSERT INTO request_history
                (request_id, step_id, node_id, action, actor_id, actor_name, actor_position, notes, ip, user_agent, device)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            [
                $requestId, null, $nodeId, $action, $actorId ?: null,
                $actor['full_name'] ?? null, $actor['position_title'] ?? null,
                $notes,
                substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
                $ua ?: null,
                self::parseDevice($ua),
            ]
        );
    }

    private static function parseDevice(string $ua): ?string
    {
        if ($ua === '') return null;
        $lower = strtolower($ua);
        return match (true) {
            str_contains($lower, 'iphone')  => 'iPhone',
            str_contains($lower, 'android') => 'Android',
            str_contains($lower, 'ipad')    => 'iPad',
            str_contains($lower, 'windows') => 'Windows',
            str_contains($lower, 'mac')     => 'Mac',
            str_contains($lower, 'linux')   => 'Linux',
            default                         => 'Web',
        };
    }
}
