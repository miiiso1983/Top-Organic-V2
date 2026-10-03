<?php
namespace App\Core;

/**
 * المصادقة والصلاحيات (جلسات الويب)
 */
class Auth
{
    public static function attempt(string $username, string $password): bool
    {
        $user = Database::query(
            "SELECT u.*, r.name AS role_name FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.username = ? AND u.is_active = 1",
            [$username]
        )->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['role_id']   = $user['role_id'];
            $_SESSION['role_name'] = $user['role_name'];
            $_SESSION['branch_id'] = $user['branch_id'];
            $_SESSION['perms']     = self::effectivePermissions((int)$user['role_id'], (string)$user['role_name']);
            return true;
        }
        return false;
    }

    public static function loadPermissions(int $roleId): array
    {
        $rows = Database::query(
            "SELECT p.code FROM role_permissions rp
             JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id = ?",
            [$roleId]
        )->fetchAll();
        return array_column($rows, 'code');
    }

    /** الصلاحيات الفعلية لدور معيّن: كل الصلاحيات لمدير النظام، وإلا صلاحيات الدور فقط */
    public static function effectivePermissions(int $roleId, string $roleName): array
    {
        if ($roleName === 'مدير النظام') {
            $rows = Database::query("SELECT code FROM permissions")->fetchAll();
            return array_column($rows, 'code');
        }
        return self::loadPermissions($roleId);
    }

    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function id(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    public static function branchId(): ?int
    {
        return $_SESSION['branch_id'] ?? null;
    }

    public static function name(): string
    {
        return $_SESSION['user_name'] ?? '';
    }

    public static function role(): string
    {
        return $_SESSION['role_name'] ?? '';
    }

    public static function can(string $permission): bool
    {
        if (($_SESSION['role_name'] ?? '') === 'مدير النظام') {
            return true;
        }
        return in_array($permission, $_SESSION['perms'] ?? [], true);
    }

    /**
     * التحقق من امتلاك أي صلاحية من قائمة (للربط بين صلاحيات الويب والموبايل)
     */
    public static function canAny(array $permissions): bool
    {
        if (($_SESSION['role_name'] ?? '') === 'مدير النظام') {
            return true;
        }
        $perms = $_SESSION['perms'] ?? [];
        foreach ($permissions as $permission) {
            if (in_array($permission, $perms, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * إعادة تحميل بيانات المستخدم والدور والصلاحيات من قاعدة البيانات
     * يتم استدعاءها في بداية كل طلب لمستخدم مصدق
     */
    public static function refresh(): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return;
        }
        $user = Database::query(
            "SELECT u.*, r.name AS role_name FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.id = ? AND u.is_active = 1",
            [(int)$userId]
        )->fetch();
        if (!$user) {
            self::logout();
            return;
        }
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['role_id']   = $user['role_id'];
        $_SESSION['role_name'] = $user['role_name'];
        $_SESSION['branch_id'] = $user['branch_id'];
        $_SESSION['perms']     = self::effectivePermissions((int)$user['role_id'], (string)$user['role_name']);
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }
}
