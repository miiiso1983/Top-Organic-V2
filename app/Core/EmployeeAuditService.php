<?php
declare(strict_types=1);

namespace App\Core;

class EmployeeAuditService
{
    public static function log(int $employeeId, string $action, ?string $field = null, $oldValue = null, $newValue = null, ?int $changedBy = null): void
    {
        Database::query(
            "INSERT INTO employee_audit_logs (employee_id, action, field_name, old_value, new_value, changed_by)
             VALUES (?,?,?,?,?,?)",
            [$employeeId, $action, $field, self::stringify($oldValue), self::stringify($newValue), $changedBy ?? Auth::id()]
        );
    }

    public static function logSnapshot(int $employeeId, array $old, array $new, array $fields, ?int $changedBy = null): void
    {
        foreach ($fields as $f) {
            $ov = $old[$f] ?? null;
            $nv = $new[$f] ?? null;
            if ((string) ($ov ?? '') !== (string) ($nv ?? '')) {
                self::log($employeeId, 'update', $f, $ov, $nv, $changedBy);
            }
        }
    }

    public static function getByEmployee(int $employeeId, int $limit = 200): array
    {
        return Database::query(
            "SELECT l.*, u.full_name AS changed_by_name
             FROM employee_audit_logs l
             LEFT JOIN users u ON u.id = l.changed_by
             WHERE l.employee_id = ?
             ORDER BY l.id DESC
             LIMIT ?",
            [$employeeId, $limit]
        )->fetchAll();
    }

    private static function stringify($v): ?string
    {
        if ($v === null) {
            return null;
        }
        return is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE);
    }
}
