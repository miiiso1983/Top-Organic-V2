<?php
namespace App\Models;

use App\Core\Database;

/**
 * سجل تاريخ أسعار المكونات واسترجاع السعر الفعّال لتاريخ معين
 */
class ItemPriceHistory
{
    /** تسجيل سعر جديد لصنف مع تاريخ فعّال. إذا وُجد سجل بنفس التاريخ يتم تحديثه. */
    public static function record(int $itemId, float $price, ?string $effectiveDate = null, ?string $notes = null, ?int $userId = null): void
    {
        $date = $effectiveDate ?: date('Y-m-d');
        if ($price <= 0) { return; }
        $createdBy = ($userId !== null && $userId > 0) ? $userId : null;
        Database::query(
            "INSERT INTO item_price_history (item_id, price, effective_date, notes, created_by)
             VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE price = VALUES(price), notes = VALUES(notes), created_by = VALUES(created_by)",
            [$itemId, $price, $date, $notes, $createdBy]
        );
    }

    /** السعر الفعّال لصنف في تاريخ معين (أحدث سعر بتاريخ <= التاريخ). */
    public static function priceAt(int $itemId, ?string $date = null): float
    {
        $date = $date ?: date('Y-m-d');
        $row = Database::query(
            "SELECT price FROM item_price_history
             WHERE item_id = ? AND effective_date <= ?
             ORDER BY effective_date DESC, id DESC LIMIT 1",
            [$itemId, $date]
        )->fetch();
        if ($row) { return (float) $row['price']; }
        $item = Database::query("SELECT cost_price FROM items WHERE id = ?", [$itemId])->fetch();
        return (float) ($item['cost_price'] ?? 0);
    }

    /** سجل أسعار الصنف */
    public static function list(int $itemId): array
    {
        return Database::query(
            "SELECT * FROM item_price_history WHERE item_id = ? ORDER BY effective_date DESC, id DESC",
            [$itemId]
        )->fetchAll();
    }

    /** حذف سجل سعر */
    public static function delete(int $historyId): void
    {
        Database::query("DELETE FROM item_price_history WHERE id = ?", [$historyId]);
    }
}
