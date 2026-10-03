<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Auth, Helpers, Response, ExportHelper, ImportCostSheetHelper, CostMath};
use App\Models\ItemCostSheet;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ItemCostSheetController extends Controller
{
    /** قائمة قوالب التكلفة */
    public function index(): void
    {
        $search = trim((string) $this->input('search', ''));
        $sheets = ItemCostSheet::list($search);
        foreach ($sheets as &$s) {
            $s['cost_per_unit'] = CostMath::unitCost((float) ($s['total_cost'] ?? 0), (float) ($s['yield_qty'] ?? 1));
            $s += CostMath::profitability($s['cost_per_unit'], (float) ($s['sale_price'] ?? 0));
        }
        unset($s);
        $this->view('inventory/cost_sheets_index', [
            'title' => 'قوالب تكلفة الأصناف',
            'sheets' => $sheets,
            'search' => $search,
        ]);
    }

    /** نموذج إنشاء/تعديل قالب التكلفة */
    public function edit(string $id): void
    {
        $itemId = (int) $id;
        $item = Database::query(
            "SELECT i.*, u.name AS unit_name FROM items i LEFT JOIN units u ON u.id = i.unit_id WHERE i.id = ?",
            [$itemId]
        )->fetch();
        if (!$item) {
            Response::abort(404, 'الصنف غير موجود');
        }
        $sheet = ItemCostSheet::getByItemId($itemId) ?: [];
        $lines = $sheet ? ItemCostSheet::getLines((int) $sheet['id']) : [];
        $stockItems = Database::query(
            "SELECT i.id, i.name, i.cost_price, i.unit_id, u.name AS unit FROM items i
             LEFT JOIN units u ON u.id = i.unit_id
             WHERE i.is_active = 1 AND i.id != ? ORDER BY i.name",
            [$itemId]
        )->fetchAll();
        $units = Database::query("SELECT * FROM units ORDER BY name")->fetchAll();

        $costMap = [];
        foreach ($stockItems as $si) {
            $costMap[(int) $si['id']] = (float) ($si['cost_price'] ?? 0);
        }
        $unitMap = [];
        foreach ($units as $u) {
            $unitMap[(int) $u['id']] = [
                'factor' => (float) ($u['factor'] ?? 1),
                'base_unit_id' => $u['base_unit_id'] ? (int) $u['base_unit_id'] : null,
            ];
        }

        $this->view('inventory/cost_sheets_edit', [
            'title' => 'قالب تكلفة: ' . $item['name'],
            'item' => $item,
            'sheet' => $sheet,
            'lines' => $lines,
            'stockItems' => $stockItems,
            'units' => $units,
            'costMap' => $costMap,
            'unitMap' => $unitMap,
        ]);
    }

    /** حفظ قالب التكلفة */
    public function save(string $id): void
    {
        $itemId = (int) $id;
        $item = Database::query("SELECT id FROM items WHERE id = ?", [$itemId])->fetch();
        if (!$item) {
            Response::abort(404, 'الصنف غير موجود');
        }
        $header = [
            'yield_qty' => $this->input('yield_qty', 1),
            'yield_unit_id' => $this->input('yield_unit_id', ''),
            'waste_rate' => $this->input('waste_rate', 0),
            'sale_price' => $this->input('sale_price', 0),
            'notes' => $this->input('notes', ''),
        ];
        $lines = [];
        $itemIds = (array) ($_POST['ingredient_item_id'] ?? []);
        $qtys = (array) ($_POST['qty'] ?? []);
        $unitIds = (array) ($_POST['unit_id'] ?? []);
        $wasteRates = (array) ($_POST['waste_rate_line'] ?? []);
        $yieldGains = (array) ($_POST['yield_gain_rate'] ?? []);
        foreach ($itemIds as $i => $ingredientId) {
            $lines[] = [
                'ingredient_item_id' => (int) $ingredientId,
                'qty' => (float) ($qtys[$i] ?? 0),
                'unit_id' => (int) ($unitIds[$i] ?? 0),
                'waste_rate' => (float) ($wasteRates[$i] ?? 0),
                'yield_gain_rate' => (float) ($yieldGains[$i] ?? 0),
            ];
        }
        try {
            ItemCostSheet::save($itemId, $header, $lines, Auth::id());
            Helpers::audit('item_cost_sheet.save', 'items', $itemId, 'حفظ قالب تكلفة الصنف');
            $this->flash('success', 'تم حفظ قالب التكلفة وتحديث سعر الكلفة');
        } catch (\Throwable $e) {
            $this->flash('danger', 'خطأ: ' . $e->getMessage());
        }
        Response::redirect('/inventory/cost-sheets/' . $itemId);
    }

    /** عرض قالب التكلفة */
    public function show(string $id): void
    {
        $itemId = (int) $id;
        $item = Database::query(
            "SELECT i.*, u.name AS unit_name FROM items i LEFT JOIN units u ON u.id = i.unit_id WHERE i.id = ?",
            [$itemId]
        )->fetch();
        if (!$item) {
            Response::abort(404, 'الصنف غير موجود');
        }
        $sheet = ItemCostSheet::getByItemId($itemId);
        if (!$sheet) {
            Response::redirect('/inventory/cost-sheets/' . $itemId . '/edit');
        }
        $lines = ItemCostSheet::getLines((int) $sheet['id']);
        $totalCost = array_sum(array_column($lines, 'line_cost'));
        $costPerUnit = CostMath::unitCost($totalCost, (float) ($sheet['yield_qty'] ?? 1));
        $metrics = CostMath::profitability($costPerUnit, (float) ($sheet['sale_price'] ?? 0));
        $this->view('inventory/cost_sheets_show', [
            'title' => 'قالب تكلفة: ' . $item['name'],
            'item' => $item,
            'sheet' => $sheet,
            'lines' => $lines,
            'totalCost' => $totalCost,
            'costPerUnit' => $costPerUnit,
            'profit' => $metrics['profit'],
            'marginPct' => $metrics['markup_pct'],
            'foodCostPct' => $metrics['food_cost_pct'],
            'grossMarginPct' => $metrics['margin_pct'],
        ]);
    }

    /** حذف قالب التكلفة */
    public function delete(string $id): void
    {
        $sheetId = (int) $id;
        $sheet = Database::query("SELECT * FROM item_cost_sheets WHERE id = ?", [$sheetId])->fetch();
        if ($sheet) {
            ItemCostSheet::delete($sheetId);
            Helpers::audit('item_cost_sheet.delete', 'items', (int) $sheet['item_id'], 'حذف قالب تكلفة');
            $this->flash('success', 'تم حذف قالب التكلفة');
        }
        Response::redirect('/inventory/cost-sheets');
    }

    /** تصدير قالب التكلفة إلى PDF */
    public function exportPdf(string $id): void
    {
        $itemId = (int) $id;
        $sheet = ItemCostSheet::getByItemId($itemId);
        if (!$sheet) {
            Response::abort(404, 'قالب التكلفة غير موجود');
        }
        $item = Database::query(
            "SELECT i.*, u.name AS unit_name FROM items i LEFT JOIN units u ON u.id = i.unit_id WHERE i.id = ?",
            [$itemId]
        )->fetch();
        $lines = ItemCostSheet::getLines((int) $sheet['id']);
        $totalCost = array_sum(array_column($lines, 'line_cost'));
        $costPerUnit = CostMath::unitCost($totalCost, (float) ($sheet['yield_qty'] ?? 1));
        $metrics = CostMath::profitability($costPerUnit, (float) ($sheet['sale_price'] ?? 0));
        ExportHelper::itemCostSheetPdf('cost_sheet_' . $itemId . '.pdf', [
            'item' => $item,
            'sheet' => $sheet,
            'lines' => $lines,
            'totalCost' => $totalCost,
            'costPerUnit' => $costPerUnit,
            'profit' => $metrics['profit'],
            'foodCostPct' => $metrics['food_cost_pct'],
        ]);
    }

    /** تصدير قالب التكلفة إلى Excel */
    public function exportExcel(string $id): void
    {
        $itemId = (int) $id;
        $sheet = ItemCostSheet::getByItemId($itemId);
        if (!$sheet) {
            Response::abort(404, 'قالب التكلفة غير موجود');
        }
        $item = Database::query(
            "SELECT i.*, u.name AS unit_name FROM items i LEFT JOIN units u ON u.id = i.unit_id WHERE i.id = ?",
            [$itemId]
        )->fetch();
        $lines = ItemCostSheet::getLines((int) $sheet['id']);
        $totalCost = array_sum(array_column($lines, 'line_cost'));
        $costPerUnit = CostMath::unitCost($totalCost, (float) ($sheet['yield_qty'] ?? 1));
        $metrics = CostMath::profitability($costPerUnit, (float) ($sheet['sale_price'] ?? 0));

        $headers = ['#', 'المادة', 'الكمية', 'الوحدة', 'هدر المادة %', 'زيادة الناتج %', 'الكمية الإجمالية', 'كلفة الوحدة', 'الكلفة'];
        $rows = [];
        foreach ($lines as $idx => $line) {
            $rows[] = [
                $idx + 1,
                $line['ingredient_name'],
                (float) $line['qty'],
                $line['line_unit_name'] ?? '-',
                number_format((float) $line['waste_rate'] * 100, 2) . '%',
                number_format((float) ($line['yield_gain_rate'] ?? 0) * 100, 2) . '%',
                number_format((float) ($line['gross_qty'] ?? 0), 2) . ' ' . ($line['line_unit_name'] ?? ''),
                Helpers::money($line['unit_cost'] ?? $line['ingredient_cost'] ?? 0),
                Helpers::money($line['line_cost'] ?? 0),
            ];
        }

        $yieldUnitName = $sheet['yield_unit_name'] ?? $item['unit_name'] ?? '-';
        $period = 'الصنف: ' . ($item['name'] ?? '-')
            . ' | وحدة الناتج: ' . $yieldUnitName
            . ' | كمية الناتج: ' . ((float) ($sheet['yield_qty'] ?? 1))
            . ' | إجمالي الكلفة: ' . Helpers::money($totalCost)
            . ' | كلفة الوحدة: ' . Helpers::money($costPerUnit);

        ExportHelper::excel('cost_sheet_' . $itemId . '.xlsx', [[
            'title' => 'قالب تكلفة',
            'report_title' => 'قالب تكلفة: ' . ($item['name'] ?? '-'),
            'period' => $period,
            'headers' => $headers,
            'rows' => $rows,
            'totals' => [
                'إجمالي الكلفة' => Helpers::money($totalCost),
                'كلفة الوحدة' => Helpers::money($costPerUnit),
                'سعر البيع' => Helpers::money($sheet['sale_price'] ?? 0),
                'الربح' => Helpers::money($metrics['profit']),
                'نسبة تكلفة الغذاء' => number_format($metrics['food_cost_pct'] * 100, 2) . '%',
            ],
        ]]);
    }

    /** نموذج استيراد قوالب التكلفة من Excel */
    public function importForm(): void
    {
        $this->view('inventory/cost_sheets_import', ['title' => 'استيراد قوالب التكلفة']);
    }

    /** معالجة ملف الاستيراد */
    public function import(): void
    {
        if (empty($_FILES['import_file']['tmp_name']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('danger', 'لم يتم اختيار ملف');
            Response::redirect('/inventory/cost-sheets/import');
        }
        $result = ImportCostSheetHelper::process($_FILES['import_file']['tmp_name'], Auth::id());
        $msg = 'تم استيراد ' . $result['created'] . ' قالب تكلفة وتحديث ' . $result['updated'] . ' سعر.';
        if ($result['errors']) {
            $this->flash('warning', $msg . ' ملاحظات: ' . implode(' | ', array_slice($result['errors'], 0, 5)));
        } else {
            $this->flash('success', $msg);
        }
        Helpers::audit('item_cost_sheet.import', 'item_cost_sheets', 0, 'استيراد من Excel');
        Response::redirect('/inventory/cost-sheets');
    }

    /** تنزيل نموذج Excel للاستيراد */
    public function downloadTemplate(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('كلفة مواد');
        $headers = ['الايتم', 'الرمز', 'المادة', 'الوحدة', 'الوزن', 'سعر اخر شراء', '%هدر المادة الاولية', '%هدر القسم', '%زيادة الناتج'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValue([$i + 1, 1], $h);
        }
        $sample = ['بونفيليه لحم بالخضراوات', '12501', 'حليب خام', 'ml', 25, 1.53, 0, 0, 0];
        foreach ($sample as $i => $v) {
            $sheet->setCellValue([$i + 1, 2], $v);
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="cost_sheet_template.xlsx"');
        header('Cache-Control: max-age=0');
        (new Xlsx($spreadsheet))->save('php://output');
        exit;
    }
}
