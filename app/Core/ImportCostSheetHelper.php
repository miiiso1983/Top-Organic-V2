<?php
namespace App\Core;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use App\Models\ItemCostSheet;
use App\Models\ItemPriceHistory;

/**
 * استيراد قوالب تكلفة الأصناف من ملف Excel مطابق للمرفق
 */
class ImportCostSheetHelper
{
    /** تنفيذ الاستيراد وإرجاع ملخص النتائج */
    public static function process(string $filePath, int $userId): array
    {
        $errors = [];
        $created = 0;
        $updated = 0;
        $salePrices = [];

        try {
            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);
        } catch (\Throwable $e) {
            return ['created' => 0, 'updated' => 0, 'errors' => ['فشل قراءة الملف: ' . $e->getMessage()]];
        }

        // أولاً: جمع أسعار البيع من أي ورقة تقرير أطباق
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $name = (string) $sheet->getTitle();
            if (self::similar($name, 'cost of plates') || self::similar($name, 'تقرير مواد')) {
                $rows = self::sheetRows($sheet, 'اسم المادة');
                $salePrices = array_merge($salePrices, self::importPlatePrices($rows));
            }
        }

        // ثانياً: تحديث الأسعار وإنشاء قوالب التكلفة
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $name = (string) $sheet->getTitle();
            $rows = self::sheetRows($sheet);
            if (!$rows) { continue; }

            if (self::similar($name, 'سعر اخر شراء')) {
                $res = self::importPrices($rows);
                $updated += $res['updated'];
                $errors = array_merge($errors, $res['errors']);
            }
        }

        // نعالج ورقة المحضر أولاً لتصبح كلفة الأصناف المحضرة جاهزة قبل استخدامها في الأطباق
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $name = (string) $sheet->getTitle();
            if (self::similar($name, 'كلفة المحضر')) {
                $rows = self::sheetRows($sheet);
                if (!$rows) { continue; }
                $res = self::importCostSheets($rows, $salePrices, $userId, $name);
                $created += $res['created'];
                $errors = array_merge($errors, $res['errors']);
            }
        }

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $name = (string) $sheet->getTitle();
            if (self::similar($name, 'كلفة مواد')) {
                $rows = self::sheetRows($sheet);
                if (!$rows) { continue; }
                $res = self::importCostSheets($rows, $salePrices, $userId, $name);
                $created += $res['created'];
                $errors = array_merge($errors, $res['errors']);
            }
        }

        return ['created' => $created, 'updated' => $updated, 'errors' => $errors];
    }

    /** قراءة صفوف الورقة مع تخطي الصفوف الفارغة. يمكن البحث عن صف العناوين عندما لا يكون أول صف. */
    private static function sheetRows($sheet, ?string $headerSearch = null): array
    {
        $maxRow = (int) $sheet->getHighestDataRow();
        $maxCol = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        if ($maxRow < 1 || $maxCol < 1) { return []; }

        $headerRow = 1;
        if ($headerSearch) {
            for ($row = 1; $row <= $maxRow; $row++) {
                for ($col = 1; $col <= $maxCol; $col++) {
                    $val = (string) self::cellValue($sheet, $col, $row);
                    if (str_contains(strtolower($val), $headerSearch)) {
                        $headerRow = $row;
                        break 2;
                    }
                }
            }
        }

        $headers = [];
        for ($col = 1; $col <= $maxCol; $col++) {
            $headers[Coordinate::stringFromColumnIndex($col)] = strtolower(trim((string) self::cellValue($sheet, $col, $headerRow)));
        }

        $rows = [];
        for ($row = $headerRow + 1; $row <= $maxRow; $row++) {
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

    /** تحديث أسعار آخر شراء */
    private static function importPrices(array $rows): array
    {
        $updated = 0;
        $errors = [];
        $catId = self::defaultCategoryId();
        $unitId = self::defaultUnitId();
        foreach ($rows as $idx => $r) {
            $name = trim((string) ($r['المادة'] ?? ''));
            $price = (float) ($r['سعر'] ?? 0);
            if ($name === '' || $price <= 0) { continue; }
            $itemId = self::findItemId($name);
            if (!$itemId) {
                $itemId = self::createItem($name, $catId, $unitId, $price);
            } else {
                Database::query("UPDATE items SET cost_price = ? WHERE id = ?", [$price, $itemId]);
            }
            ItemPriceHistory::record($itemId, $price, date('Y-m-d'), 'استيراد سعر شراء', 0);
            $updated++;
        }
        return ['updated' => $updated, 'errors' => $errors];
    }

    /** قراءة أسعار البيع من تقرير الأطباق */
    private static function importPlatePrices(array $rows): array
    {
        $map = [];
        foreach ($rows as $r) {
            $name = trim((string) ($r['اسم المادة'] ?? ($r['الايتم'] ?? '')));
            $price = (float) ($r['سعر المبيع'] ?? 0);
            if ($name && $price > 0) {
                $map[$name] = $price;
            }
        }
        return $map;
    }

    /** إنشاء/تحديث قوالب التكلفة من ورقة المكونات */
    private static function importCostSheets(array $rows, array $salePrices, int $userId, string $sheetName): array
    {
        $created = 0;
        $errors = [];
        $groups = [];
        $itemCol = self::similar($sheetName, 'كلفة المحضر') ? 'المادة المصنعة' : 'الايتم';
        foreach ($rows as $r) {
            $itemName = trim((string) ($r[$itemCol] ?? ''));
            if ($itemName === '') { continue; }
            $groups[$itemName][] = $r;
        }

        foreach ($groups as $itemName => $groupRows) {
            $itemId = self::findItemId($itemName);
            $firstRow = $groupRows[0];
            $catName = trim((string) ($firstRow['صنف'] ?? ''));
            $itemBarcode = trim((string) ($firstRow['الرمز'] ?? ''));
            $itemUnitId = self::findOrCreateUnit(trim((string) ($firstRow['الوحدة'] ?? 'PCS')));
            $catId = self::findOrCreateCategory($catName, 'prepared');
            if (!$itemId) {
                $itemId = self::createItem($itemName, $catId, $itemUnitId, 0, $itemBarcode);
            } else {
                Database::query(
                    "UPDATE items SET category_id = ?, unit_id = ?, barcode = COALESCE(NULLIF(?, ''), barcode) WHERE id = ?",
                    [$catId, $itemUnitId, $itemBarcode, $itemId]
                );
            }
            $item = Database::query("SELECT unit_id FROM items WHERE id = ?", [$itemId])->fetch();

            $lines = [];
            foreach ($groupRows as $r) {
                $ingName = trim((string) ($r['المادة'] ?? ''));
                if ($ingName === '' || $ingName === $itemName) { continue; }
                $unitName = trim((string) ($r['الوحدة'] ?? ''));
                $qty = (float) ($r['الوزن'] ?? ($r['الكمية'] ?? 0));
                $unitCost = (float) ($r['سعر اخر شراء'] ?? ($r['اخر شراء'] ?? 0));
                $waste = (float) ($r['%هدر المادة الاولية'] ?? ($r['نسبة الهدر'] ?? 0));
                $yieldGain = (float) ($r['%زيادة الناتج'] ?? ($r['زيادة الناتج'] ?? 0));
                if ($qty <= 0) { continue; }

                $unitId = self::findOrCreateUnit($unitName);
                $ingType = self::typeFromString((string) ($r['نوع المادة'] ?? 'مادة اولية'));
                $ingCatName = $ingType === 'prepared' ? 'محضر' : 'مواد أولية';
                $ingCatId = self::findOrCreateCategory($ingCatName, $ingType);
                $ingId = self::findItemId($ingName);
                if (!$ingId) {
                    $ingId = self::createItem($ingName, $ingCatId, $unitId, $unitCost);
                    if ($unitCost > 0) {
                        ItemPriceHistory::record($ingId, $unitCost, date('Y-m-d'), 'استيراد مكون', 0);
                    }
                } else {
                    Database::query(
                        "UPDATE items SET category_id = ?, unit_id = ? WHERE id = ?",
                        [$ingCatId, $unitId, $ingId]
                    );
                    if ($unitCost > 0 && !ItemCostSheet::getByItemId($ingId)) {
                        $base = UnitConversionService::toBaseUnit($unitId, 1);
                        $costPerBase = $base > 0 ? $unitCost / $base : $unitCost;
                        Database::query("UPDATE items SET cost_price = ? WHERE id = ?", [$costPerBase, $ingId]);
                        ItemPriceHistory::record($ingId, $costPerBase, date('Y-m-d'), 'استيراد سعر مكون', 0);
                    }
                }
                $lines[] = [
                    'ingredient_item_id' => $ingId,
                    'qty' => $qty,
                    'unit_id' => $unitId,
                    'waste_rate' => $waste,
                    'yield_gain_rate' => $yieldGain,
                ];
            }

            if (!$lines) {
                $errors[] = "الصنف '$itemName' لا يحتوي على مكونات صالحة";
                continue;
            }

            $existingSheet = ItemCostSheet::getByItemId($itemId);
            $headerWaste = 0.0;
            foreach ($groupRows as $r) {
                $hw = (float) ($r['%هدر القسم'] ?? ($r['هدر القسم'] ?? 0));
                if ($hw > $headerWaste) { $headerWaste = $hw; }
            }
            $header = [
                'yield_qty' => 1,
                'yield_unit_id' => $item['unit_id'] ?? null,
                'waste_rate' => $headerWaste ?: ($existingSheet['waste_rate'] ?? 0),
                'sale_price' => $salePrices[$itemName] ?? ($existingSheet['sale_price'] ?? 0),
                'notes' => 'مستورد من Excel',
            ];
            ItemCostSheet::save($itemId, $header, $lines, $userId);
            $created++;
        }
        return ['created' => $created, 'errors' => $errors];
    }

    private static function findItemId(string $name): ?int
    {
        $row = Database::query("SELECT id FROM items WHERE TRIM(name) = ? LIMIT 1", [trim($name)])->fetch();
        return $row ? (int) $row['id'] : null;
    }

    private static function createItem(string $name, int $categoryId, int $unitId, float $cost, ?string $barcode = null): int
    {
        $barcode = ($barcode !== null && trim($barcode) !== '') ? trim($barcode) : null;
        Database::query(
            "INSERT INTO items (barcode, name, category_id, unit_id, cost_price, is_active) VALUES (?,?,?,?,?,1)",
            [$barcode, $name, $categoryId, $unitId, $cost]
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
            'ml' => 'مل', 'g' => 'غم', 'kg' => 'كغم', 'kgs' => 'كغم',
            'pcs' => 'حبة', 'piece' => 'حبة', 'pieces' => 'حبة',
            'ltr' => 'لتر', 'liter' => 'لتر', 'litre' => 'لتر',
            'gram' => 'غم', 'grams' => 'غم', 'kilogram' => 'كغم', 'kilograms' => 'كغم',
        ];
        return $map[strtolower(trim($name))] ?? trim($name);
    }

    private static function typeFromString(string $typeName): string
    {
        $key = strtolower(str_replace([' ', '_', '-'], '', trim($typeName)));
        $map = [
            'مادةاولية' => 'raw', 'raw' => 'raw', 'مواداولية' => 'raw',
            'محضر' => 'prepared', 'prepared' => 'prepared', 'منتج' => 'prepared',
        ];
        return $map[$key] ?? 'raw';
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

    private static function defaultCategoryId(string $type = 'raw'): int
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

    private static function defaultUnitId(): int
    {
        $row = Database::query("SELECT id FROM units LIMIT 1")->fetch();
        if ($row) { return (int) $row['id']; }
        Database::query("INSERT INTO units (name, factor) VALUES ('حبة', 1)");
        return (int) Database::lastInsertId();
    }

    private static function similar(string $a, string $b): bool
    {
        return str_contains(mb_strtolower(str_replace([' ', '_', '-'], '', $a)), mb_strtolower(str_replace([' ', '_', '-'], '', $b)))
            || str_contains(mb_strtolower(str_replace([' ', '_', '-'], '', $b)), mb_strtolower(str_replace([' ', '_', '-'], '', $a)));
    }
}
