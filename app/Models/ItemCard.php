<?php
namespace App\Models;

use App\Core\Database;
use App\Core\Helpers;
use App\Core\UnitConversionService;

/**
 * نموذج بيانات بطاقة تعريف الصنف
 */
class ItemCard
{
    /** قائمة الأصناف مع بيانات البطاقة */
    public static function list(string $search = ''): array
    {
        $params = [];
        $where = 'WHERE i.is_active = 1';
        if ($search !== '') {
            $where .= ' AND (i.name LIKE ? OR i.barcode LIKE ? OR i.card_no LIKE ?)';
            $like = '%' . $search . '%';
            $params = [$like, $like, $like];
        }
        $items = Database::query(
            "SELECT i.*, c.name AS category_name, c.type AS category_type,
                    u.name AS unit_name, MAX(w.name) AS warehouse_name,
                    MAX(ks.name) AS kitchen_name,
                    COALESCE(SUM(s.qty), 0) AS total_qty
             FROM items i
             JOIN item_categories c ON c.id = i.category_id
             JOIN units u ON u.id = i.unit_id
             LEFT JOIN warehouses w ON w.id = i.main_warehouse_id
             LEFT JOIN kitchen_sections ks ON ks.id = i.kitchen_section_id
             LEFT JOIN stock s ON s.item_id = i.id
             $where
             GROUP BY i.id
             ORDER BY i.name",
            $params
        )->fetchAll();
        foreach ($items as &$it) {
            $it['total_qty'] = UnitConversionService::fromBaseUnit((int) $it['unit_id'], (float) $it['total_qty']);
        }
        unset($it);
        return $items;
    }

    /** تفاصيل الصنف الكاملة */
    public static function get(int $id): ?array
    {
        $item = Database::query(
            "SELECT i.*, c.name AS category_name, c.type AS category_type,
                    u.name AS unit_name,
                    pu.name AS purchase_unit_name,
                    ru.name AS recipe_unit_name,
                    w.name AS warehouse_name,
                    ks.name AS kitchen_name,
                    (SELECT COALESCE(SUM(qty), 0) FROM stock WHERE item_id = i.id) AS total_qty
             FROM items i
             JOIN item_categories c ON c.id = i.category_id
             JOIN units u ON u.id = i.unit_id
             LEFT JOIN units pu ON pu.id = i.purchase_unit_id
             LEFT JOIN units ru ON ru.id = i.recipe_unit_id
             LEFT JOIN warehouses w ON w.id = i.main_warehouse_id
             LEFT JOIN kitchen_sections ks ON ks.id = i.kitchen_section_id
             WHERE i.id = ?",
            [$id]
        )->fetch();
        if ($item) {
            $item['total_qty'] = UnitConversionService::fromBaseUnit((int) $item['unit_id'], (float) $item['total_qty']);
        }
        return $item ?: null;
    }

    /** حركات المخزون الخاصة بالصنف */
    public static function movements(int $id): array
    {
        return Database::query(
            "SELECT m.*, w.name AS warehouse_name,
                    dw.name AS destination_warehouse_name, u.full_name
             FROM stock_movements m
             JOIN warehouses w ON w.id = m.warehouse_id
             LEFT JOIN warehouses dw ON dw.id = m.destination_warehouse_id
             LEFT JOIN users u ON u.id = m.created_by
             WHERE m.item_id = ?
             ORDER BY m.id DESC",
            [$id]
        )->fetchAll();
    }

    /** تحديث بيانات البطاقة */
    public static function update(int $id, array $data): void
    {
        Database::query(
            "UPDATE items SET
                description = ?,
                main_warehouse_id = ?,
                kitchen_section_id = ?,
                prep_time_minutes = ?,
                card_no = ?
             WHERE id = ?",
            [
                $data['description'] ?? null,
                !empty($data['main_warehouse_id']) ? (int) $data['main_warehouse_id'] : null,
                !empty($data['kitchen_section_id']) ? (int) $data['kitchen_section_id'] : null,
                isset($data['prep_time_minutes']) ? (int) $data['prep_time_minutes'] : null,
                $data['card_no'] ?? null,
                $id,
            ]
        );
    }

    /** توليد رقم بطاقة جديد */
    public static function generateCardNo(int $id): string
    {
        return Helpers::docNumber('IIC', 'items', 'card_no');
    }

    /** توليد وتعيين رقم البطاقة إذا لم يكن موجوداً */
    public static function ensureCardNo(int $id): string
    {
        $item = Database::query("SELECT card_no FROM items WHERE id = ?", [$id])->fetch();
        if ($item && !empty($item['card_no'])) {
            return $item['card_no'];
        }
        $cardNo = self::generateCardNo($id);
        Database::query("UPDATE items SET card_no = ? WHERE id = ?", [$cardNo, $id]);
        return $cardNo;
    }
}
