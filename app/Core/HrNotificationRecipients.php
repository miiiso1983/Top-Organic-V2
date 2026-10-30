<?php
namespace App\Core;

/** Employee events must use responsibility, never general visibility or administrative level. */
final class HrNotificationRecipients
{
    public static function manager(int $employeeId): ?int
    {
        $employee = Database::query('SELECT user_id, manager_id FROM employees WHERE id = ?', [$employeeId])->fetch();
        if (!$employee) return null;
        if (!empty($employee['manager_id'])) {
            $id = Database::query("SELECT u.id FROM employees m JOIN users u ON u.id=m.user_id AND u.is_active=1 WHERE m.id=? AND m.file_status='open'", [(int)$employee['manager_id']])->fetchColumn();
        } else {
            // Only the assigned parent position, never another manager in the same branch.
            $id = Database::query("SELECT u.id FROM org_positions child
                JOIN org_positions parent ON parent.id=child.parent_id AND parent.is_active=1
                JOIN employees m ON m.id=parent.employee_id AND m.file_status='open'
                JOIN users u ON u.id=m.user_id AND u.is_active=1
                WHERE child.employee_id=? AND child.is_active=1 ORDER BY child.id LIMIT 1", [$employeeId])->fetchColumn();
        }
        return $id && (int)$id !== (int)($employee['user_id'] ?? 0) ? (int)$id : null;
    }

    public static function teamEmployeeIds(int $managerUserId): array
    {
        $rows = Database::query("SELECT e.id FROM employees e
            WHERE e.status='active' AND e.file_status='open' AND (
                EXISTS (SELECT 1 FROM employees m JOIN users u ON u.id=m.user_id AND u.is_active=1
                    WHERE m.id=e.manager_id AND m.user_id=? AND m.file_status='open')
                OR ((e.manager_id IS NULL OR e.manager_id=0) AND EXISTS (
                    SELECT 1 FROM org_positions child
                    JOIN org_positions parent ON parent.id=child.parent_id AND parent.is_active=1
                    JOIN employees m ON m.id=parent.employee_id AND m.file_status='open'
                    JOIN users u ON u.id=m.user_id AND u.is_active=1
                    WHERE child.employee_id=e.id AND child.is_active=1 AND m.user_id=?))
            ) AND (e.user_id IS NULL OR e.user_id<>?) ORDER BY e.id",
            [$managerUserId, $managerUserId, $managerUserId])->fetchAll(\PDO::FETCH_COLUMN);
        return array_map('intval', $rows);
    }

    public static function hr(): array
    {
        return array_map('intval', Database::query("SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id
            WHERE u.is_active=1 AND r.name IN ('مدير قسم الموارد البشرية','مدير موارد بشرية','مدير الموارد البشرية')")->fetchAll(\PDO::FETCH_COLUMN));
    }
}
