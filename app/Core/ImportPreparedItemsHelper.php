<?php
namespace App\Core;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use App\Models\ItemCostSheet;
use App\Models\ItemPriceHistory;

/**
 * استيراد الأصناف المحضرة ومكوناتها من ملف Excel
 * الورقة المتوقعة: "كلفة المحضر"
 * الأعمدة: المادة المصنعة | المادة | الرمز | الكمية | الوحدة | اخر شراء | نسبة الهدر | نسبة الهدر حسب الناتج
 */
class ImportPreparedItemsHelper
{
    public static function process(string $filePath, int $userId): array
    {
        $errors = [];
        $createdSheets = 0;
        $createdItems = 0;
        $updatedItems = 0;

        try {
            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);
        } catch (\Throwable $e) {
            return ['created_sheets' => 0, 'created_items' => 0, 'updated_items' => 0, 'errors' => ['فشل قراءة الملف: ' . $e->getMessage()]];
        }

        $targetSheet = null;
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            if (self::similar($sheet->getTitle(), 'كلفة المحضر') || self::similar($sheet->getTitle(), 'محضر')) {
                $targetSheet = $sheet;
                break;
            }
        }
        if (!$targetSheet) {
            return ['created_sheets' => 0, 'created_items' => 0, 'updated_items' => 0, 'errors' => ['لم يتم العثور على ورقة الأصناف المحضرة']];
        }

        $rows = self::sheetRows($targetSheet);
        if (!$rows) {
            return ['created_sheets' => 0, 'created_items' => 0, 'updated_items' => 0, 'errors' => ['الورقة فارغة']];
        }

        $groups = [];
        foreach ($rows as $r) {
            $preparedName = trim((string) ($r['المادة المصنعة'] ?? ''));
            if ($preparedName === '') continue;
            $groups[$preparedName][] = $r;
        }

        $preparedItemIds = [];
        $preparedCatId = self::findOrCreateCategory('محضر', 'prepared');
        $defaultUnitId = self::findOrCreateUnit('كغ');

        foreach (array_keys($groups) as $preparedName) {
            $itemId = self::findItemId($preparedName);
            if (!$itemId) {
                $itemId = self::createItem($preparedName, $preparedCatId, $defaultUnitId, 0, null, 1);
                $createdItems++;
            } else {
                Database::query(
                    "UPDATE items SET category_id = ?, item_type = COALESCE(NULLIF(item_type, ''), 'prepared'), is_prepared = 1 WHERE id = ?",
                    [$preparedCatId, $itemId]
                );
                $updatedItems++;
            }
            $preparedItemIds[$preparedName] = $itemId;
        }

        foreach ($groups as $preparedName => $groupRows) {
            $itemId = $preparedItemIds[$preparedName];
            $item = Database::query("SELECT unit_id FROM items WHERE id = ?", [$itemId])->fetch();

            $lines = [];
            $headerWaste = 0.0;
            foreach ($groupRows as $r) {
                $ingName = trim((string) ($r['المادة'] ?? ''));
                if ($ingName === '' || $ingName === $preparedName) continue;

                $qty = (float) ($r['الكمية'] ?? 0);
                if ($qty <= 0) continue;

                $unitName = trim((string) ($r['الوحدة'] ?? 'PCS'));
                $unitId = self::findOrCreateUnit($unitName);
                $unitCost = (float) ($r['اخر شراء'] ?? 0);
                $barcode = trim((string) ($r['الرمز'] ?? ''));

                $waste = (float) ($r['نسبة الهدر'] ?? 0);
                $outputWaste = (float) ($r['نسبة الهدر حسب الناتج'] ?? 0);
                if ($outputWaste > $headerWaste) $headerWaste = $outputWaste;

                $isPrepared = isset($preparedItemIds[$ingName]);
                $ingType = $isPrepared ? 'prepared' : 'raw';
                $ingCatName = $isPrepared ? 'محضر' : 'مواد أولية';
                $ingCatId = self::findOrCreateCategory($ingCatName, $ingType);

                $ingId = self::findItemId($ingName);
                $ingIsPrepared = $isPrepared ? 1 : 0;
                if (!$ingId) {
                    $ingId = self::createItem($ingName, $ingCatId, $unitId, $unitCost, $barcode, $ingIsPrepared);
                    $createdItems++;
                    if ($unitCost > 0) {
                        ItemPriceHistory::record($ingId, $unitCost, date('Y-m-d'), 'استيراد مكون محضر', $userId);
                    }
                } else {
                    Database::query(
                        "UPDATE items SET category_id = ?, item_type = COALESCE(NULLIF(item_type, ''), ?), is_prepared = ? WHERE id = ?",
                        [$ingCatId, $ingType, $ingIsPrepared, $ingId]
                    );
                    if ($unitCost > 0) {
                        Database::query("UPDATE items SET cost_price = ? WHERE id = ?", [$unitCost, $ingId]);
                        ItemPriceHistory::record($ingId, $unitCost, date('Y-m-d'), 'تحديث سعر مكون', $userId);
                    }
                    $updatedItems++;
                }

                $lines[] = [
                    'ingredient_item_id' => $ingId,
                    'qty' => $qty,
                    'unit_id' => $unitId,
                    'waste_rate' => $waste,
                    'yield_gain_rate' => 0,
                ];
            }

            if (!$lines) {
                $errors[] = "الصنف '$preparedName' لا يحتوي على مكونات صالحة";
                continue;
            }

            $header = [
                'yield_qty' => 1,
                'yield_unit_id' => $item['unit_id'] ?? null,
                'waste_rate' => $headerWaste,
                'sale_price' => 0,
                'notes' => 'مستورد من Excel — الأصناف المحضرة',
            ];
            ItemCostSheet::save($itemId, $header, $lines, $userId);
            $createdSheets++;
        }

        PreparedItemCostService::recalculateAll($userId);

        return [
            'created_sheets' => $createdSheets,
            'created_items' => $createdItems,
            'updated_items' => $updatedItems,
            'errors' => $errors,
        ];
    }
    private static function sheetRows($sheet): array
    {
        $maxRow = (int) $sheet->getHighestDataRow();
        $maxCol = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        if ($maxRow < 1 || $maxCol < 1) { return []; }

        $headers = [];
        for ($col = 1; $col <= $maxCol; $col++) {
            $headers[Coordinate::stringFromColumnIndex($col)] = strtolower(trim((string) self::cellValue($sheet, $col, 1)));
        }

        $rows = [];
        for ($row = 2; $row <= $maxRow; $row++) {
            $mapped = [];
            $hasValue = false;
            for ($col = 1; $col <= $maxCol; $col++) {
                $key = $headers[Coordinate::stringFromColumnIndex($col)] ?? null;
                if (!$key) { continue; }
                $val = self::cellValue($sheet, $col, $row);
                $mapped[$key] = $val;
                if ($val !== null && $val !== '') { $hasValue = true; }
            }
            if ($hasValue) { $rows[] = $mapped; }
        }
        return $rows;
    }

    private static function cellValue($sheet, int $col, int $row)
    {
        $coord = Coordinate::stringFromColumnIndex($col) . $row;
        if (!$sheet->cellExists($coord)) { return null; }
        $cell = $sheet->getCell($coord);
        return ($cell->getDataType() === 'f') ? $cell->getOldCalculatedValue() : $cell->getValue();
    }

    private static function findItemId(string $name): ?int
    {
        $row = Database::query("SELECT id FROM items WHERE TRIM(name) = ? LIMIT 1", [trim($name)])->fetch();
        return $row ? (int) $row['id'] : null;
    }

    private static function createItem(string $name, int $categoryId, int $unitId, float $cost, ?string $barcode = null, int $isPrepared = 0): int
    {
        $barcode = ($barcode !== null && trim($barcode) !== '') ? trim($barcode) : null;
        Database::query(
            "INSERT INTO items (barcode, name, category_id, unit_id, cost_price, is_prepared, is_active) VALUES (?,?,?,?,?,?,1)",
            [$barcode, $name, $categoryId, $unitId, $cost, $isPrepared]
        );
        return (int) Database::lastInsertId();
    }

    private static function findOrCreateUnit(string $name): int
    {
        $name = self::unitAlias($name);
        $row = Database::query("SELECT id FROM units WHERE TRIM(name) = ? LIMIT 1", [$name])->fetch();
        if ($row) { return (int) $row['id']; }
        Database::query("INSERT INTO units (name, factor) VALUES (?,1)", [$name]);
        return (int) Database::lastInsertId();
    }

    private static function unitAlias(string $name): string
    {
        $map = [
            'ml' => 'مل', 'g' => 'غم', 'kg' => 'كغم', 'kgs' => 'كغم', 'كغ' => 'كغم',
            'pcs' => 'حبة', 'piece' => 'حبة', 'pieces' => 'حبة',
            'ltr' => 'لتر', 'liter' => 'لتر', 'litre' => 'لتر',
            'gram' => 'غم', 'grams' => 'غم', 'kilogram' => 'كغم', 'kilograms' => 'كغم',
        ];
        return $map[strtolower(trim($name))] ?? trim($name);
    }

    private static function findOrCreateCategory(string $name, string $type): int
    {
        $name = trim($name);
        if ($name === '') { return self::defaultCategoryId($type); }
        $row = Database::query("SELECT id FROM item_categories WHERE TRIM(name) = ? AND type = ? LIMIT 1", [$name, $type])->fetch();
        if ($row) { return (int) $row['id']; }
        Database::query("INSERT INTO item_categories (name, type) VALUES (?, ?)", [$name, $type]);
        return (int) Database::lastInsertId();
    }

    private static function defaultCategoryId(string $type): int
    {
        $row = Database::query("SELECT id FROM item_categories WHERE type = ? AND LOWER(TRIM(name)) = ? LIMIT 1", [$type, $type === 'raw' ? 'مواد أولية' : 'محضر'])->fetch();
        if ($row) { return (int) $row['id']; }
        $row = Database::query("SELECT id FROM item_categories WHERE type = ? LIMIT 1", [$type])->fetch();
        if ($row) { return (int) $row['id']; }
        $row = Database::query("SELECT id FROM item_categories LIMIT 1")->fetch();
        if ($row) { return (int) $row['id']; }
        Database::query("INSERT INTO item_categories (name, type) VALUES ('مستورد', 'raw')");
        return (int) Database::lastInsertId();
    }

    private static function similar(string $a, string $b): bool
    {
        return str_contains(mb_strtolower(str_replace([' ', '_', '-'], '', $a)), mb_strtolower(str_replace([' ', '_', '-'], '', $b)))
            || str_contains(mb_strtolower(str_replace([' ', '_', '-'], '', $b)), mb_strtolower(str_replace([' ', '_', '-'], '', $a)));
    }
}

