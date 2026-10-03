<?php
declare(strict_types=1);

namespace App\Core;

/**
 * تنبيهات النواقص والأحداث المخزنية
 */
class AlertService
{
    /**
     * تحقق من إشعار مواد أولية ناقصة
     */
    public static function checkLowStock(int $itemId): void
    {
        $item = Database::query(
            "SELECT i.*, c.name AS category_name, c.type AS category_type, u.name AS unit_name
             FROM items i
             JOIN item_categories c ON c.id = i.category_id
             JOIN units u ON u.id = i.unit_id
             WHERE i.id = ? AND i.is_active = 1 AND c.type = 'raw'",
            [$itemId]
        )->fetch();
        if (!$item) {
            return;
        }
        $min = (float) $item['min_qty'];
        if ($min <= 0) {
            return;
        }
        $total = (float) Database::query(
            "SELECT COALESCE(SUM(qty), 0) AS total FROM stock WHERE item_id = ?",
            [$itemId]
        )->fetch()['total'];
        if ($total > $min) {
            return;
        }
        $title = 'تنبيه: نقص في المواد الأولية';
        $body = "المادة: {$item['name']} — الرصيد: " . number_format($total, 2) . " {$item['unit_name']} — الحد الأدنى: " . number_format($min, 2) . " {$item['unit_name']}";
        self::notifyInventoryUsers($title, $body, '/inventory');
    }

    /**
     * إشعار مستخدمي المخزن النشطين
     */
    private static function notifyInventoryUsers(string $title, string $body, string $link): void
    {
        $users = Database::query(
            "SELECT DISTINCT u.id FROM users u
             JOIN role_permissions rp ON rp.role_id = u.role_id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE u.is_active = 1 AND p.code IN ('inventory.view','inventory.manage','inventory.mobile')"
        )->fetchAll();
        foreach ($users as $u) {
            Helpers::notify((int) $u['id'], $title, $body, $link);
        }
    }
}
