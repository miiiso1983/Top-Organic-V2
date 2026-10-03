<?php
declare(strict_types=1);

namespace App\Core\PlateCost;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

class ExcelParser
{
    private const PURCHASE_PRICE_MAP = [
        'A' => 'material_name', 'B' => 'material_category', 'C' => 'unit_price',
        'E' => 'raw_waste_rate', 'F' => 'raw_waste_over_yield',
    ];
    private const PREPARED_MAP = [
        'A' => 'prepared_name', 'B' => 'ingredient_name', 'C' => 'ingredient_code', 'D' => 'base_quantity', 'E' => 'unit',
        'G' => 'unit_price', 'H' => 'raw_waste_rate', 'I' => 'raw_waste_over_yield', 'J' => 'raw_waste_quantity',
        'K' => 'raw_waste_cost', 'L' => 'department_waste_rate', 'M' => 'department_waste_over_yield',
        'N' => 'department_waste_quantity', 'O' => 'department_waste_cost', 'P' => 'actual_consumed_quantity', 'Q' => 'line_cost',
    ];
    private const DISH_MATERIAL_MAP = [
        'A' => 'dish_name', 'B' => 'dish_code', 'C' => 'dish_category', 'D' => 'material_type', 'E' => 'material_name',
        'F' => 'unit', 'G' => 'base_quantity', 'I' => 'resolved_unit_cost', 'J' => 'raw_waste_over_yield',
        'K' => 'department_waste_rate', 'L' => 'department_waste_over_yield', 'M' => 'actual_consumed_quantity', 'N' => 'line_cost',
    ];
    private const DISH_MAP = [
        'A' => 'dish_name', 'B' => 'dish_code', 'C' => 'dish_category', 'D' => 'sale_unit',
        'E' => 'dish_cost', 'G' => 'sale_price', 'H' => 'gross_profit', 'I' => 'markup_on_cost',
    ];

    public static function parse(string $filePath): array
    {
        $errors = [];
        $warnings = [];
        $result = ['purchase_prices' => [], 'prepared_components' => [], 'dish_materials' => [], 'dishes' => []];

        try {
            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $reader->setReadFilter(self::makeReadFilter());
            $spreadsheet = $reader->load($filePath);
        } catch (\Throwable $e) {
            return ['errors' => ['فشل قراءة الملف: ' . $e->getMessage()], 'warnings' => []] + $result;
        }

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $name = self::normalizeName($sheet->getTitle());
            if (str_contains($name, 'سعراخرشراء')) {
                $result['purchase_prices'] = self::readMappedRows($sheet, self::PURCHASE_PRICE_MAP, 1, $warnings);
            } elseif (str_contains($name, 'كلفةالمحضر')) {
                $result['prepared_components'] = self::readMappedRows($sheet, self::PREPARED_MAP, 1, $warnings);
            } elseif (str_contains($name, 'كلفةمواد')) {
                $result['dish_materials'] = self::readMappedRows($sheet, self::DISH_MATERIAL_MAP, 1, $warnings);
            } elseif (str_contains($name, 'costofplates')) {
                $rows = self::readMappedRows($sheet, self::DISH_MAP, 4, $warnings);
                $result['dishes'] = self::filterDishRows($rows);
            }
        }

        if (!$result['purchase_prices']) {
            $errors[] = 'لم يتم العثور على ورقة أسعار الشراء (سعر اخر شراء)';
        }
        if (!$result['dishes']) {
            $errors[] = 'لم يتم العثور على ورقة الأطباق (Cost Of Plates)';
        }

        return $result + ['errors' => $errors, 'warnings' => $warnings];
    }

    private static function normalizeName(string $name): string
    {
        return mb_strtolower(str_replace([' ', '_', '-'], '', trim($name)));
    }

    private static function makeReadFilter(): IReadFilter
    {
        return new class implements IReadFilter {
            public function readCell($columnAddress, $row, $worksheetName = ''): bool
            {
                $name = mb_strtolower(str_replace([' ', '_', '-'], '', trim($worksheetName)));
                if (!str_contains($name, 'سعراخرشراء')
                    && !str_contains($name, 'كلفةالمحضر')
                    && !str_contains($name, 'كلفةمواد')
                    && !str_contains($name, 'costofplates')
                ) {
                    return false;
                }
                // نقرأ فقط الأعمدة حتى Q كحد أقصى
                return $columnAddress <= 'Q';
            }
        };
    }

    private static function readMappedRows($sheet, array $map, int $headerRow, array &$warnings): array
    {
        $maxRow = (int) $sheet->getHighestDataRow();
        $out = [];
        for ($row = $headerRow + 1; $row <= $maxRow; $row++) {
            $mapped = [];
            $hasValue = false;
            foreach ($map as $col => $key) {
                $val = self::cellValue($sheet, $col, $row);
                $mapped[$key] = $val;
                if ($val !== null && $val !== '') {
                    $hasValue = true;
                }
            }
            if (!$hasValue) {
                continue;
            }
            $out[] = $mapped;
        }
        return $out;
    }

    private static function cellValue($sheet, string $col, int $row): mixed
    {
        $coord = $col . $row;
        if (!$sheet->cellExists($coord)) {
            return null;
        }
        $cell = $sheet->getCell($coord);
        return ($cell->getDataType() === 'f') ? $cell->getOldCalculatedValue() : $cell->getValue();
    }

    private static function filterDishRows(array $rows): array
    {
        return array_values(array_filter($rows, fn ($r) => !empty($r['dish_name'])));
    }
}
