<?php
namespace App\Models;

use App\Core\{Database, UnitConversionService, CostingEngine, CostMath};
use App\Models\ItemPriceHistory;

/**
 * نموذج قالب تكلفة الصنف (Item Cost Sheet)
 */
class ItemCostSheet
{
    /** قائمة الأصناف التي لها قالب تكلفة */
    public static function list(string $search = ''): array
    {
        $params = [];
        $where = '';
        if ($search !== '') {
            $where = 'WHERE i.name LIKE ? OR i.barcode LIKE ?';
            $like = '%' . $search . '%';
            $params = [$like, $like];
        }
        return Database::query(
            "SELECT cs.*, i.name AS item_name, i.barcode, i.cost_price,
                    u.name AS yield_unit_name, iu.name AS item_unit_name,
                    (SELECT COALESCE(SUM(line_cost), 0) FROM item_cost_sheet_lines WHERE cost_sheet_id = cs.id) AS total_cost
             FROM item_cost_sheets cs
             JOIN items i ON i.id = cs.item_id
             LEFT JOIN units u ON u.id = cs.yield_unit_id
             LEFT JOIN units iu ON iu.id = i.unit_id
             $where
             ORDER BY i.name",
            $params
        )->fetchAll();
    }

    /** الحصول على قالب تكلفة صنف معن */
    public static function getByItemId(int $itemId): ?array
    {
        $sheet = Database::query(
            "SELECT cs.*, i.name AS item_name, i.barcode, i.cost_price,
                    i.unit_id AS item_unit_id, iu.name AS item_unit_name,
                    u.name AS yield_unit_name
             FROM item_cost_sheets cs
             JOIN items i ON i.id = cs.item_id
             LEFT JOIN units u ON u.id = cs.yield_unit_id
             LEFT JOIN units iu ON iu.id = i.unit_id
             WHERE cs.item_id = ?",
            [$itemId]
        )->fetch();
        return $sheet ?: null;
    }

    /** سطور قالب التكلفة مع حساباتها. يمكن تمرير تاريخ لاستخدام أسعار المكونات في ذلك التاريخ. */
    public static function getLines(int $sheetId, ?string $date = null): array
    {
        $headerWaste = (float) (Database::query(
            "SELECT COALESCE(waste_rate, 0) AS waste_rate FROM item_cost_sheets WHERE id = ?",
            [$sheetId]
        )->fetch()['waste_rate'] ?? 0);
        $lines = Database::query(
            "SELECT l.*, i.name AS ingredient_name, i.cost_price AS ingredient_cost,
                    i.unit_id AS ingredient_unit_id,
                    iu.name AS ingredient_unit_name, u.name AS line_unit_name
             FROM item_cost_sheet_lines l
             JOIN items i ON i.id = l.ingredient_item_id
             LEFT JOIN units iu ON iu.id = i.unit_id
             LEFT JOIN units u ON u.id = l.unit_id
             WHERE l.cost_sheet_id = ?
             ORDER BY l.sort_order, l.id",
            [$sheetId]
        )->fetchAll();
        foreach ($lines as &$line) {
            $line = self::calculateLine($line, $date, $headerWaste);
        }
        unset($line);
        return $lines;
    }

    /**
     * حساب كلفة سطر واحد وفق المعادلة الموحّدة:
     * gross = net / (1 - هدر المادة) / (1 - هدر القسم) / (1 + زيادة الناتج)
     */
    public static function calculateLine(array $line, ?string $date = null, float $headerWaste = 0.0): array
    {
        $unitId = (int) $line['unit_id'];
        $ingredientId = (int) $line['ingredient_item_id'];
        self::assertUnitCompatible($unitId, $ingredientId, $line['ingredient_unit_id'] ?? null);
        $qty = (float) $line['qty'];
        // الكمية الصافية والإجمالية بالوحدة المدخلة (وحدة السطر) لعرض منطقي للمستخدم
        $netQty = $qty;
        $grossQty = CostMath::grossQty(
            $netQty,
            (float) ($line['waste_rate'] ?? 0),
            $headerWaste,
            (float) ($line['yield_gain_rate'] ?? 0)
        );
        // حساب الكلفة بالوحدة الأساسية للمادة
        $grossQtyBase = UnitConversionService::toBaseUnit($unitId, $grossQty);
        $cost = self::ingredientCost($ingredientId, $date);
        $line['net_qty'] = $netQty;
        $line['gross_qty'] = $grossQty;
        // actual_qty مُبقاة بالوحدة الأساسية للتوافق مع التقارير القديمة
        $line['actual_qty'] = $grossQtyBase;
        $line['unit_cost'] = $cost;
        $line['line_cost'] = CostMath::lineCost($grossQtyBase, $cost);
        return $line;
    }

    /** رفض الوحدات من عائلة مختلفة (وزن مقابل حجم مثلاً) */
    private static function assertUnitCompatible(int $unitId, int $ingredientId, $ingredientUnitId = null): void
    {
        if ($unitId <= 0) {
            return;
        }
        if ($ingredientUnitId === null) {
            $ingredientUnitId = Database::query("SELECT unit_id FROM items WHERE id = ?", [$ingredientId])->fetch()['unit_id'] ?? null;
        }
        $ingredientUnitId = (int) $ingredientUnitId;
        if ($ingredientUnitId <= 0) {
            return;
        }
        if (!UnitConversionService::sameFamily($unitId, $ingredientUnitId)) {
            throw new \RuntimeException(
                'وحدة غير متوافقة للمادة: ' . UnitConversionService::getUnitName($unitId)
                . ' مقابل ' . UnitConversionService::getUnitName($ingredientUnitId)
            );
        }
    }

    /** كلفة الوحدة لصنف في تاريخ معين (بناءً على سجل الأسعار) */
    public static function costAtDate(int $itemId, string $date): ?float
    {
        $sheet = self::getByItemId($itemId);
        if (!$sheet) { return null; }
        $lines = self::getLines((int) $sheet['id'], $date);
        $totalCost = array_sum(array_column($lines, 'line_cost'));
        return CostMath::unitCost($totalCost, (float) ($sheet['yield_qty'] ?? 1));
    }

    /** حفظ أو تحديث قالب التكلفة */
    public static function save(int $itemId, array $header, array $lines, ?int $userId = null): int
    {
        Database::beginTransaction();
        try {
            $existing = Database::query("SELECT id FROM item_cost_sheets WHERE item_id = ?", [$itemId])->fetch();
            $sheetId = $existing ? (int) $existing['id'] : 0;
            $yieldUnitId = !empty($header['yield_unit_id']) ? (int) $header['yield_unit_id'] : null;
            $salePrice = is_numeric($header['sale_price'] ?? '') ? (float) $header['sale_price'] : 0;
            $yieldQty = is_numeric($header['yield_qty'] ?? '') ? (float) $header['yield_qty'] : 1;
            if ($yieldQty <= 0) {
                $yieldQty = 1;
            }
            $headerWaste = CostMath::normalizeWaste(
                is_numeric($header['waste_rate'] ?? '') ? (float) $header['waste_rate'] : 0
            );
            if ($sheetId) {
                Database::query(
                    "UPDATE item_cost_sheets SET yield_qty = ?, yield_unit_id = ?, waste_rate = ?, sale_price = ?, notes = ? WHERE id = ?",
                    [$yieldQty, $yieldUnitId, $headerWaste, $salePrice, $header['notes'] ?? null, $sheetId]
                );
                Database::query("DELETE FROM item_cost_sheet_lines WHERE cost_sheet_id = ?", [$sheetId]);
            } else {
                Database::query(
                    "INSERT INTO item_cost_sheets (item_id, yield_qty, yield_unit_id, waste_rate, sale_price, notes, created_by)
                     VALUES (?,?,?,?,?,?,?)",
                    [$itemId, $yieldQty, $yieldUnitId, $headerWaste, $salePrice, $header['notes'] ?? null, $userId]
                );
                $sheetId = (int) Database::lastInsertId();
            }

            $sort = 0;
            foreach ($lines as $line) {
                $ingredientId = (int) ($line['ingredient_item_id'] ?? 0);
                $qty = (float) ($line['qty'] ?? 0);
                if ($ingredientId <= 0 || $qty <= 0) {
                    continue;
                }
                $unitId = (int) ($line['unit_id'] ?? 0);
                $wasteRate = CostMath::normalizeWaste(
                    is_numeric($line['waste_rate'] ?? '') ? (float) $line['waste_rate'] : 0
                );
                $yieldGain = CostMath::normalizeGain(
                    is_numeric($line['yield_gain_rate'] ?? '') ? (float) $line['yield_gain_rate'] : 0
                );
                $calculated = self::calculateLine([
                    'unit_id' => $unitId,
                    'qty' => $qty,
                    'waste_rate' => $wasteRate,
                    'yield_gain_rate' => $yieldGain,
                    'ingredient_item_id' => $ingredientId,
                ], null, $headerWaste);
                Database::query(
                    "INSERT INTO item_cost_sheet_lines
                     (cost_sheet_id, ingredient_item_id, qty, unit_id, waste_rate, yield_gain_rate, gross_qty, line_cost, sort_order)
                     VALUES (?,?,?,?,?,?,?,?,?)",
                    [$sheetId, $ingredientId, $qty, $unitId, $wasteRate, $yieldGain, $calculated['gross_qty'], $calculated['line_cost'], $sort]
                );
                $sort++;
            }

            // تحديث سعر كلفة الصنف الناتج بالقيمة المحسوبة للوحدة (cost_price + standard_cost)
            $totalCost = (float) Database::query(
                "SELECT COALESCE(SUM(line_cost), 0) AS total FROM item_cost_sheet_lines WHERE cost_sheet_id = ?",
                [$sheetId]
            )->fetch()['total'];
            $unitCost = CostMath::unitCost($totalCost, $yieldQty);
            Database::query(
                "UPDATE items SET cost_price = ?, standard_cost = ?, item_type = COALESCE(NULLIF(item_type, ''), 'prepared'), is_prepared = 1 WHERE id = ?",
                [$unitCost, $unitCost, $itemId]
            );
            if ($unitCost > 0) {
                ItemPriceHistory::record($itemId, $unitCost, date('Y-m-d'), 'تحديث من قالب تكلفة', $userId);
            }

            Database::commit();
            return $sheetId;
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    /** حذف قالب التكلفة */
    public static function delete(int $sheetId): void
    {
        Database::query("DELETE FROM item_cost_sheets WHERE id = ?", [$sheetId]);
    }

    /** كلفة المادة المستخدمة في تاريخ معين (أو السعر الحالي) */
    private static function ingredientCost(int $itemId, ?string $date = null): float
    {
        try {
            // توحيد مصدر التسعير عبر CostingEngine (يدعم purchase_prices / cost_history)
            $cost = CostingEngine::ingredientUnitCost($itemId, $date, 'moving_average');
            if ($cost > 0) { return (float) $cost; }
        } catch (\Throwable $e) {
            // تجاهل أي خطأ غير متوقع والرجوع للاحتياطي
        }
        // احتياطي قديم: سجل الأسعار التاريخي ثم سعر الكلفة الحالي
        return ItemPriceHistory::priceAt($itemId, $date);
    }
}
