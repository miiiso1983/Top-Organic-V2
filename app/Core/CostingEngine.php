<?php
namespace App\Core;

use App\Models\ItemPriceHistory;
use App\Models\ItemCostSheet;

/**
 * CostingEngine
 * - ingredientUnitCost(): يعتمد على purchase_prices تاريخياً للمواد الخام،
 *   مع رجوع احتياطي إلى item_price_history ثم items.cost_price
 * - recipeCost(): يحسب تكلفة الوحدة لوصفة/صنف محضّر بالاعتماد على سطور قالب التكلفة
 *   ويستخدم ingredientUnitCost لكل مكوّن، مع هدر تراكمي وزيادة ناتج وكشف دورات.
 *
 * ملاحظة: يدعم طرق التقييم (moving_average | standard | fifo) شكلياً فقط الآن.
 * - standard: يستخدم items.standard_cost إن وُجد (>0)
 * - moving_average: يحاول قراءة cost_history (إن وُجد)، وإلا يرجع لسجل الأسعار
 * - fifo: غير مُطبّق بالكامل في هذه المرحلة، يُعامل مثل moving_average (fallback)
 */
class CostingEngine
{
    /** أقصى عمق للتعشيش في شجرة قوالب التكلفة */
    private const MAX_DEPTH = 20;

    /** مسار الأصناف الجاري حسابها — لكشف الدورات */
    private static array $resolving = [];

    /** تكلفة وحدة مكوّن في تاريخ معيّن */
    public static function ingredientUnitCost(int $itemId, ?string $atDate = null, string $method = 'moving_average', ?int $warehouseId = null): float
    {
        $date = $atDate ?: date('Y-m-d');

        // 1) معلومات الصنف ونوعه ووحدته وتكلفة المعيار
        $item = Database::query(
            "SELECT i.id, i.unit_id, i.cost_price, COALESCE(i.standard_cost,0) AS standard_cost, c.type AS category_type
             FROM items i JOIN item_categories c ON c.id = i.category_id WHERE i.id = ?",
            [$itemId]
        )->fetch() ?: [];
        $categoryType = $item['category_type'] ?? 'raw';

        // 2) طريقة Standard
        if ($method === 'standard') {
            $std = (float) ($item['standard_cost'] ?? 0);
            if ($std > 0) {
                return $std;
            }
            // لا يوجد standard => نكمل على fallback
        }

        // 3) طريقة Moving Average / FIFO: جرّب cost_history أولاً
        $hist = self::lastCostHistory($itemId, $date, $warehouseId, $method);
        if ($hist !== null && $hist > 0) {
            return (float) $hist;
        }

        // 4) إن كان الصنف محضّراً، احسب تكلفة الوصفة
        if ($categoryType === 'prepared') {
            $rc = self::recipeCost($itemId, $date, $method, $warehouseId);
            if ($rc !== null) {
                return (float) $rc;
            }
        }

        // 5) خلاف ذلك (مواد خام): استخدم purchase_prices تاريخياً، ثم ItemPriceHistory، ثم items.cost_price
        $pp = self::lastPurchasePrice($itemId, $date);
        if ($pp !== null && $pp > 0) {
            return (float) $pp;
        }

        $ph = ItemPriceHistory::priceAt($itemId, $date);
        if ($ph > 0) {
            return (float) $ph;
        }

        return (float) ($item['cost_price'] ?? 0);
    }

    /**
     * تكلفة الوحدة لصنف محضّر (حسب قالب التكلفة)، وإلا null إن لم يوجد قالب.
     * يطبّق الهدر التراكمي وزيادة الناتج، ويكشف الدورات في الوصفات المتداخلة.
     */
    public static function recipeCost(int $itemId, ?string $atDate = null, string $method = 'moving_average', ?int $warehouseId = null): ?float
    {
        if (isset(self::$resolving[$itemId]) || count(self::$resolving) >= self::MAX_DEPTH) {
            return null; // دورة في الوصفات أو عمق مفرط — أوقف التعشيش
        }
        $date = $atDate ?: date('Y-m-d');
        $sheet = ItemCostSheet::getByItemId($itemId);
        if (!$sheet) { return null; }

        self::$resolving[$itemId] = true;
        try {
            $yieldQty = (float) ($sheet['yield_qty'] ?? 1);
            $headerWaste = (float) ($sheet['waste_rate'] ?? 0);
            $lines = Database::query(
                "SELECT ingredient_item_id, qty, unit_id, COALESCE(waste_rate,0) AS waste_rate,
                        COALESCE(yield_gain_rate,0) AS yield_gain_rate
                 FROM item_cost_sheet_lines WHERE cost_sheet_id = ? ORDER BY sort_order, id",
                [(int) $sheet['id']]
            )->fetchAll();

            $total = 0.0;
            foreach ($lines as $ln) {
                $netQty = UnitConversionService::toBaseUnit((int) $ln['unit_id'], (float) $ln['qty']);
                $grossQty = CostMath::grossQty(
                    $netQty,
                    (float) $ln['waste_rate'],
                    $headerWaste,
                    (float) $ln['yield_gain_rate']
                );
                $uCost = self::ingredientUnitCost((int) $ln['ingredient_item_id'], $date, $method, $warehouseId);
                $total += CostMath::lineCost($grossQty, $uCost);
            }

            return round(CostMath::unitCost($total, $yieldQty), 4);
        } finally {
            unset(self::$resolving[$itemId]);
        }
    }

    /** استرجاع آخر سعر شراء تاريخي من purchase_prices <= التاريخ */
    private static function lastPurchasePrice(int $itemId, string $date): ?float
    {
        // قد لا تكون الجدول موجودة في جميع البيئات — استرجاع آمن
        try {
            $row = Database::query(
                "SELECT purchase_price FROM purchase_prices WHERE item_id = ? AND purchase_date <= ?
                 ORDER BY purchase_date DESC, id DESC LIMIT 1",
                [$itemId, $date]
            )->fetch();
            return $row ? (float) $row['purchase_price'] : null;
        } catch (\Throwable $e) {
            return null; // جدول غير موجود أو خطأ — تجاهل كـ fallback
        }
    }

    /** استرجاع آخر قيمة من cost_history <= التاريخ لنفس الطريقة والمخزن (إن وُجد) */
    private static function lastCostHistory(int $itemId, string $date, ?int $warehouseId, string $method): ?float
    {
        try {
            $params = [$itemId, $method, $date];
            $whClause = is_null($warehouseId) ? 'warehouse_id IS NULL' : 'warehouse_id = ?';
            if (!is_null($warehouseId)) { array_splice($params, 1, 0, [$warehouseId]); }
            $sql = "SELECT cost_value FROM cost_history
                    WHERE item_id = ? AND $whClause AND cost_method = ? AND for_date <= ?
                    ORDER BY for_date DESC, id DESC LIMIT 1";
            $row = Database::query($sql, $params)->fetch();
            return $row ? (float) $row['cost_value'] : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** تسجيل لقطة تكلفة في cost_history (اختياري — غير مستخدم تلقائياً الآن) */
    public static function recordSnapshot(int $itemId, float $value, string $forDate, string $method = 'moving_average', ?int $warehouseId = null, ?string $source = null, $sourceId = null): void
    {
        try {
            Database::query(
                "INSERT INTO cost_history (item_id, warehouse_id, cost_method, cost_value, cost_currency, for_date, source, source_id)
                 VALUES (?,?,?,?, 'IQD', ?, ?, ?)
                 ON DUPLICATE KEY UPDATE cost_value = VALUES(cost_value), source = VALUES(source), source_id = VALUES(source_id)",
                [$itemId, $warehouseId, $method, $value, $forDate, $source, $sourceId]
            );
        } catch (\Throwable $e) {
            // تجاهل أخطاء الجدول/القيود في هذه المرحلة — Skeleton
        }
    }
}
