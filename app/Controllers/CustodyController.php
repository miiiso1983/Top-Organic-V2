<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Auth, Helpers, Response, StockService, UnitConversionService, BarcodeService, AccountingService};

class CustodyController extends Controller
{
    public function index(): void
    {
        $custody = Database::query(
            "SELECT c.*, i.name AS item_name, e.full_name AS employee_name
             FROM custody c
             JOIN items i ON i.id = c.item_id
             LEFT JOIN employees e ON e.id = c.employee_id
             ORDER BY c.id DESC LIMIT 200"
        )->fetchAll();
        $items = Database::query(
            "SELECT i.id, i.name, c.type AS category_type FROM items i
             JOIN item_categories c ON c.id = i.category_id
             WHERE c.type IN ('asset','tool','clothing') AND i.is_active = 1
             ORDER BY i.name"
        )->fetchAll();
        $employees = Database::query(
            "SELECT id, full_name FROM employees WHERE file_status='open' ORDER BY full_name"
        )->fetchAll();
        $warehouses = Database::query("SELECT * FROM warehouses WHERE is_active=1 ORDER BY name")->fetchAll();
        $units = Database::query("SELECT * FROM units ORDER BY name")->fetchAll();

        $this->view('inventory/custody', [
            'title' => 'العهدة', 'custody' => $custody,
            'items' => $items, 'employees' => $employees,
            'warehouses' => $warehouses, 'units' => $units,
        ]);
    }

    public function store(): void
    {
        $item = $this->resolveCustodyItem($this->input('barcode'), $this->input('item_id'));
        if (!$item) {
            $this->flash('danger', 'الباركود أو المادة غير معروفة');
            Response::redirect('/custody');
        }
        $itemId = (int) $item['id'];
        $categoryType = (string) $item['category_type'];
        $unitId = (int) $this->input('unit_id', $item['unit_id']);
        $qty = (float) $this->input('qty', 1);
        $warehouseId = (int) $this->input('warehouse_id', 1);
        $type = (string) $this->input('custodian_type', 'employee');

        if (!in_array($categoryType, ['asset', 'tool', 'clothing'], true)) {
            $this->flash('danger', 'العهدة مقتصرة على الموجودات والأدوات والملابس');
            Response::redirect('/custody');
        }
        if ($categoryType === 'clothing' && $type !== 'employee') {
            $this->flash('danger', 'الملابس فقط عهدة شخصية للموظف');
            Response::redirect('/custody');
        }
        if ($type === 'employee' && (int) $this->input('employee_id') <= 0) {
            $this->flash('danger', 'يجب اختيار الموظف');
            Response::redirect('/custody');
        }

        Database::beginTransaction();
        try {
            $baseQty = UnitConversionService::toBaseUnit($unitId, $qty);
            $balance = StockService::balance($warehouseId, $itemId);
            if ($balance < $baseQty - 0.0001) {
                throw new \Exception('الكمية في المخزن غير كافية (متوفر: ' . $balance . ')');
            }
            Database::query(
                "INSERT INTO custody (item_id, qty, unit_id, warehouse_id, custodian_type, employee_id, department, expiry_date, notes, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?)",
                [
                    $itemId, $baseQty, $unitId, $warehouseId, $type,
                    $type === 'employee' ? (int) $this->input('employee_id') : null,
                    $type === 'department' ? (string) $this->input('department') : null,
                    $this->input('expiry_date') ?: null,
                    (string) $this->input('notes'),
                    Auth::id(),
                ]
            );
            $custodyId = (int)Database::lastInsertId();
            StockService::moveWithUnit($warehouseId, $itemId, 'out', $qty, $unitId, 0, 'CUSTODY', 'تسليم عهدة');
            AccountingService::postCustodyIssueLegacy($custodyId);
            Database::commit();
            $this->flash('success', 'تم تسليم العهدة وخصمها من المخزن');
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->flash('danger', 'خطأ: ' . $e->getMessage());
        }
        Response::redirect('/custody');
    }

    public function returnItem(string $id): void
    {
        $c = Database::query("SELECT * FROM custody WHERE id = ?", [(int)$id])->fetch();
        if ($c && $c['status'] === 'active') {
            $newStatus = (string)$this->input('status', 'returned');
            if (!in_array($newStatus, ['returned', 'damaged', 'lost'], true)) {
                $newStatus = 'returned';
            }
            Database::query(
                "UPDATE custody SET status = ?, returned_at = NOW() WHERE id = ?",
                [$newStatus, (int)$id]
            );
            // إعادة للمخزن المكتب فقط في حالة الإرجاع السليم
            if ($newStatus === 'returned') {
                $warehouseId = (int) ($c['warehouse_id'] ?? 1);
                StockService::move($warehouseId, (int)$c['item_id'], 'return', (float)$c['qty'], 0, 'CUSTODY-RET', 'إرجاع عهدة');
            }
            AccountingService::postCustodyReturnLegacy((int)$id);
            // تحميل قيمة العهدة على الموظف في حالة التلف أو الفقدان
            if (in_array($newStatus, ['damaged', 'lost'], true) && !empty($c['employee_id'])) {
                $item = Database::query("SELECT name, cost_price FROM items WHERE id = ?", [(int)$c['item_id']])->fetch();
                $amount = (float)$c['qty'] * (float)($item['cost_price'] ?? 0);
                if ($amount > 0) {
                    Database::query(
                        "INSERT INTO hr_adjustments (employee_id, adjustment_type, amount, effective_date, reason, status, requested_by, approved_by, approved_at)
                         VALUES (?, 'deduction', ?, ?, ?, 'approved', ?, ?, NOW())",
                        [(int)$c['employee_id'], $amount, date('Y-m-d'), 'تحميل قيمة عهدة (تلف/فقد): ' . ($item['name'] ?? '') . ' #' . (int)$id, Auth::id(), Auth::id()]
                    );
                    $adjId = (int)Database::lastInsertId();
                    AccountingService::postAdjustment($adjId);
                }
            }
            $this->flash('success', 'تم تحديث حالة العهدة');
        }
        Response::redirect('/custody');
    }

    private function resolveCustodyItem($barcode, $itemId): ?array
    {
        $barcode = trim((string) ($barcode ?? ''));
        if ($barcode !== '') {
            return BarcodeService::findItemByBarcodeOrName($barcode);
        }
        $id = (int) ($itemId ?? 0);
        if ($id > 0) {
            return Database::query(
                "SELECT i.*, c.name AS category_name, c.type AS category_type, u.name AS unit_name
                 FROM items i
                 JOIN item_categories c ON c.id = i.category_id
                 JOIN units u ON u.id = i.unit_id
                 WHERE i.id = ? AND i.is_active = 1",
                [$id]
            )->fetch() ?: null;
        }
        return null;
    }
}
