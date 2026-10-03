<?php
declare(strict_types=1);

namespace App\Core;

/**
 * خدمة البحث عن المواد بالباركود
 */
class BarcodeService
{
    /**
     * بحث سريع عن مادة بالباركود.
     * يعود المواد مع التصنيف والوحدة والرصيد المخزني.
     */
    public static function findItem(string $barcode, ?int $warehouseId = null): ?array
    {
        $item = Database::query(
            "SELECT i.*, c.name AS category_name, c.type AS category_type,
                    u.name AS unit_name, u.base_unit_id, u.factor
             FROM items i
             JOIN item_categories c ON c.id = i.category_id
             JOIN units u ON u.id = i.unit_id
             WHERE i.barcode = ? AND i.is_active = 1",
            [$barcode]
        )->fetch();
        if (!$item) {
            return null;
        }
        $item['id'] = (int) $item['id'];
        $item['category_id'] = (int) $item['category_id'];
        $item['unit_id'] = (int) $item['unit_id'];
        $item['base_unit_id'] = $item['base_unit_id'] ? (int) $item['base_unit_id'] : null;
        $item['factor'] = (float) $item['factor'];
        if ($warehouseId !== null) {
            $item['warehouse_balance'] = UnitConversionService::fromBaseUnit($item['unit_id'], StockService::balance($warehouseId, $item['id']));
        }
        return $item;
    }

    /**
     * بحث بالباركود أو بالاسم في حالة فشل الباركود
     */
    public static function findItemByBarcodeOrName(string $search): ?array
    {
        $item = self::findItem($search);
        if ($item) {
            return $item;
        }
        $row = Database::query(
            "SELECT i.*, c.name AS category_name, c.type AS category_type,
                    u.name AS unit_name, u.base_unit_id, u.factor
             FROM items i
             JOIN item_categories c ON c.id = i.category_id
             JOIN units u ON u.id = i.unit_id
             WHERE i.name LIKE ? AND i.is_active = 1
             LIMIT 1",
            ['%' . $search . '%']
        )->fetch();
        if (!$row) {
            return null;
        }
        $row['id'] = (int) $row['id'];
        $row['category_id'] = (int) $row['category_id'];
        $row['unit_id'] = (int) $row['unit_id'];
        $row['base_unit_id'] = $row['base_unit_id'] ? (int) $row['base_unit_id'] : null;
        $row['factor'] = (float) $row['factor'];
        return $row;
    }
}
