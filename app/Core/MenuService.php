<?php
namespace App\Core;

/**
 * تحديد المنيو المناسب حسب الطابق/الصالة
 */
class MenuService
{
    /** يُرجع معرّفات المنيو المرتبطة بطابق معيّن (أو المنيو الافتراضي إن لم يوجد تعيين) */
    public static function menuIdsForFloor(?int $floorId): array
    {
        if ($floorId) {
            $rows = Database::query(
                "SELECT menu_id FROM menu_floor_assignments WHERE floor_id = ?",
                [$floorId]
            )->fetchAll();
            if ($rows) {
                return array_map(fn($r) => (int)$r['menu_id'], $rows);
            }
        }
        $default = Database::query("SELECT id FROM menus WHERE is_default = 1 LIMIT 1")->fetch();
        if ($default) {
            return [(int)$default['id']];
        }
        $any = Database::query("SELECT id FROM menus ORDER BY id LIMIT 1")->fetch();
        return $any ? [(int)$any['id']] : [];
    }

    /** يُرجع الأقسام والأصناف الخاصة بمنيو طابق معيّن */
    public static function menuForFloor(?int $floorId): array
    {
        // إذا لم يُحدد طابق، نعرض جميع الأقسام والأصناف النشطة من كل المنيوهات
        // بحيث يظهر أي صنف يُضاف من صفحة إدارة المنيو في شاشة المبيعات مباشرة.
        if (!$floorId) {
            $categories = Database::query(
                "SELECT * FROM menu_categories WHERE is_active = 1 ORDER BY sort_order, name"
            )->fetchAll();
            $items = Database::query(
                "SELECT mi.*, mc.name AS category_name FROM menu_items mi
                 JOIN menu_categories mc ON mc.id = mi.category_id
                 WHERE mi.is_active = 1 AND mc.is_active = 1
                 ORDER BY mc.sort_order, mi.name"
            )->fetchAll();
            return ['menu_id' => 0, 'categories' => $categories, 'items' => $items];
        }

        $menuIds = self::menuIdsForFloor($floorId);
        if (!$menuIds) {
            return ['menu_id' => 0, 'categories' => [], 'items' => []];
        }
        $placeholders = implode(',', array_fill(0, count($menuIds), '?'));
        $categories = Database::query(
            "SELECT * FROM menu_categories WHERE is_active=1 AND menu_id IN ($placeholders) ORDER BY sort_order",
            $menuIds
        )->fetchAll();
        $items = Database::query(
            "SELECT mi.*, mc.name AS category_name FROM menu_items mi
             JOIN menu_categories mc ON mc.id = mi.category_id
             WHERE mi.is_active = 1 AND mc.menu_id IN ($placeholders) ORDER BY mc.sort_order, mi.name",
            $menuIds
        )->fetchAll();
        return ['menu_id' => $menuIds[0], 'categories' => $categories, 'items' => $items];
    }
}
