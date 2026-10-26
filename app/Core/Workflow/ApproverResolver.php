<?php
namespace App\Core\Workflow;

use App\Core\Database;

/**
 * محدد المعتمدين — يحوّل إعداد عقدة الموافقة إلى قائمة user_id.
 *
 * الإعداد (config):
 *   {"type":"user","user_id":5}
 *   {"type":"role","role_id":2}
 *   {"type":"position","position_id":7}
 *   {"type":"direct_manager"}  | {"type":"manager_of_manager"}
 *   {"type":"department_manager"} | {"type":"branch_manager"}
 *   {"type":"permission","permission":"workflow.decide"}
 *   {"type":"group","user_ids":[1,2]}  | {"type":"committee","user_ids":[..]}
 */
final class ApproverResolver
{
    public const TYPES = [
        'user', 'role', 'position', 'permission', 'group', 'committee',
        'direct_manager', 'manager_of_manager', 'department_manager', 'branch_manager',
    ];

    /** تعيد قائمة user_id فريدة بعد تطبيق التفويض */
    public static function resolve(array $config, array $request): array
    {
        $frozen = \App\Core\HrTransferSnapshot::get((int)($request['id'] ?? 0), \App\Core\HrTransferSnapshot::key($config));
        if ($frozen !== null) return $frozen;
        $type = (string)($config['type'] ?? '');
        $users = match ($type) {
            'user'               => [(int)($config['user_id'] ?? 0)],
            'group', 'committee' => array_map('intval', (array)($config['user_ids'] ?? [])),
            'role'               => self::byRole((int)($config['role_id'] ?? 0)),
            'permission'         => self::byPermission((string)($config['permission'] ?? '')),
            'position'           => self::byPosition((int)($config['position_id'] ?? 0), $request),
            'direct_manager'     => self::byManagerChain($request, 1),
            'manager_of_manager' => self::byManagerChain($request, 2),
            'department_manager' => self::byDepartmentManager($request),
            'branch_manager'     => self::byBranchManager($request),
            default              => [],
        };
        $users = array_values(array_unique(array_filter($users)));
        return self::applyDelegation($users, (string)($request['template_code'] ?? ''));
    }

    private static function byRole(int $roleId): array
    {
        if (!$roleId) return [];
        return Database::query("SELECT id FROM users WHERE role_id = ?", [$roleId])->fetchAll(\PDO::FETCH_COLUMN);
    }

    private static function byPermission(string $code): array
    {
        if ($code === '') return [];
        return Database::query(
            "SELECT DISTINCT u.id FROM users u
             JOIN role_permissions rp ON rp.role_id = u.role_id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE p.code = ?",
            [$code]
        )->fetchAll(\PDO::FETCH_COLUMN);
    }

    /** شاغل منصب محدد؛ مع التسلق من منصب مقدم الطلب إن لم يُعثر على شاغل مباشر */
    private static function byPosition(int $positionId, array $request): array
    {
        if (!$positionId) return [];
        $users = self::usersOfPosition($positionId);
        if ($users) return $users;
        // تسلق شجرة المناصب من منصب مقدم الطلب حتى الوصول للمنصب المطلوب
        $current = (int)($request['requester_position_id'] ?? 0);
        $guard = 0;
        while ($current && $guard++ < 20) {
            if ($current === $positionId) return self::usersOfPosition($current);
            $current = (int)(Database::query(
                "SELECT parent_id FROM org_positions WHERE id = ?", [$current]
            )->fetchColumn() ?: 0);
        }
        return [];
    }

    private static function usersOfPosition(int $positionId): array
    {
        return Database::query(
            "SELECT u.id FROM org_positions op
             JOIN employees e ON e.id = op.employee_id
             JOIN users u ON u.id = e.user_id
             WHERE op.id = ? AND op.is_active = 1",
            [$positionId]
        )->fetchAll(\PDO::FETCH_COLUMN);
    }

    /** المدير المباشر (levels=1) أو مدير المدير (levels=2) عبر employees.manager_id ثم شجرة المناصب */
    private static function byManagerChain(array $request, int $levels): array
    {
        $originalLevels = $levels;
        $empId = (int)($request['requester_employee_id'] ?? 0);
        $guard = 0;
        while ($empId && $levels-- > 0 && $guard++ < 10) {
            $empId = (int)(Database::query(
                "SELECT manager_id FROM employees WHERE id = ?", [$empId]
            )->fetchColumn() ?: 0);
        }
        if ($empId) {
            $uid = (int)(Database::query(
                "SELECT user_id FROM employees WHERE id = ? AND user_id IS NOT NULL", [$empId]
            )->fetchColumn() ?: 0);
            if ($uid) return [$uid];
        }
        // fallback: التسلق في شجرة المناصب
        $pos = (int)($request['requester_position_id'] ?? 0);
        $guard = 0;
        while ($pos && $originalLevels-- > 0 && $guard++ < 20) {
            $pos = (int)(Database::query(
                "SELECT parent_id FROM org_positions WHERE id = ?", [$pos]
            )->fetchColumn() ?: 0);
        }
        return $pos ? self::usersOfPosition($pos) : [];
    }

    private static function byDepartmentManager(array $request): array
    {
        $dept = trim((string)($request['data']['department'] ?? ''));
        if ($dept !== '') {
            $pos = Database::query(
                "SELECT id, parent_id FROM org_positions WHERE is_active = 1 AND title LIKE ? LIMIT 1",
                ['%' . $dept . '%']
            )->fetch();
            if ($pos) {
                $target = (int)($pos['parent_id'] ?: $pos['id']);
                $users = self::usersOfPosition($target);
                if ($users) return $users;
            }
        }
        return self::byManagerChain($request, 1);
    }

    private static function byBranchManager(array $request): array
    {
        $branchId = (int)($request['branch_id'] ?? 0);
        return Database::query(
            "SELECT DISTINCT u.id FROM users u
             JOIN roles r ON r.id = u.role_id
             LEFT JOIN employees e ON e.user_id = u.id
             WHERE r.name IN ('مدير فرع','مدير مطعم','مدير عام')
               AND (? = 0 OR e.branch_id = ? OR e.branch_id IS NULL)
             ORDER BY r.level DESC",
            [$branchId, $branchId]
        )->fetchAll(\PDO::FETCH_COLUMN);
    }

    /** استبدال المفوَّض عنهم بالمفوَّض إليهم خلال فترة التفويض */
    private static function applyDelegation(array $userIds, string $requestType): array
    {
        if (!$userIds) return [];
        $out = [];
        foreach ($userIds as $uid) {
            $delegate = Database::query(
                "SELECT to_user_id FROM approval_delegations
                 WHERE from_user_id = ? AND is_active = 1
                   AND CURDATE() BETWEEN valid_from AND valid_to
                   AND (scope = 'all' OR scope = ?)
                 ORDER BY id DESC LIMIT 1",
                [$uid, $requestType]
            )->fetchColumn();
            $out[] = $delegate ? (int)$delegate : $uid;
        }
        return array_values(array_unique($out));
    }
}
