<?php
namespace App\Core;

use App\Models\ItemCostSheet;

/**
 * خدمة تحديث تكاليف الأصناف المحضرة (Cost Roll-up)
 * تعيد حساب قوالب التكلفة للأصناف المحضرة بشكل متكرر لضمان نشر التكاليف
 * عبر مستويات التداخل (مكوّن محضر يستخدم كمادة أولية في محضر آخر).
 */
class PreparedItemCostService
{
    /** إعادة حساب كل الأصناف المحضرة */
    public static function recalculateAll(?int $userId = null): void
    {
        $maxPasses = 5;
        for ($pass = 0; $pass < $maxPasses; $pass++) {
            $sheets = Database::query(
                "SELECT cs.id, cs.item_id, cs.yield_qty, cs.yield_unit_id, cs.waste_rate, cs.sale_price, cs.notes
                 FROM item_cost_sheets cs
                 JOIN items i ON i.id = cs.item_id
                 WHERE i.item_type = 'prepared' OR i.category_id IN (SELECT id FROM item_categories WHERE type = 'prepared')"
            )->fetchAll();

            if (!$sheets) { break; }

            foreach ($sheets as $s) {
                self::saveExistingSheet((int) $s['id'], $userId);
            }
        }
    }

    /** إعادة حساب صنف محضر واحد */
    public static function recalculate(int $itemId, ?int $userId = null): void
    {
        $sheet = Database::query("SELECT id FROM item_cost_sheets WHERE item_id = ?", [$itemId])->fetch();
        if ($sheet) {
            self::saveExistingSheet((int) $sheet['id'], $userId);
        }
    }

    /** إعادة حفظ قالب تكلفة موجود باستخدام سطوره الحالية لتحديث التكلفة */
    private static function saveExistingSheet(int $sheetId, ?int $userId): void
    {
        $sheet = Database::query(
            "SELECT id, item_id, yield_qty, yield_unit_id, waste_rate, sale_price, notes
             FROM item_cost_sheets WHERE id = ?",
            [$sheetId]
        )->fetch();
        if (!$sheet) { return; }

        $lines = Database::query(
            "SELECT ingredient_item_id, qty, unit_id, waste_rate, yield_gain_rate
             FROM item_cost_sheet_lines WHERE cost_sheet_id = ? ORDER BY sort_order, id",
            [$sheetId]
        )->fetchAll();

        if (!$lines) { return; }

        $header = [
            'yield_qty' => $sheet['yield_qty'],
            'yield_unit_id' => $sheet['yield_unit_id'],
            'waste_rate' => $sheet['waste_rate'],
            'sale_price' => $sheet['sale_price'],
            'notes' => $sheet['notes'],
        ];
        ItemCostSheet::save((int) $sheet['item_id'], $header, $lines, $userId);
    }
}
