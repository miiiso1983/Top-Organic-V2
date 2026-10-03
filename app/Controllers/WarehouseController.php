<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Auth, Helpers, Response, ExportHelper, UnitConversionService};

/**
 * إدارة المخازن والأقسام
 */
class WarehouseController extends Controller
{
    public function index(): void
    {
        $warehouses = Database::query(
            "SELECT * FROM warehouses ORDER BY warehouse_type, name"
        )->fetchAll();
        $this->view('inventory/warehouses', [
            'title' => 'إدارة المخازن',
            'warehouses' => $warehouses,
        ]);
    }

    /** صفحة تعرض الأصناف المتوفرة في مخزن مُختار (أو جميع المخازن) */
    public function items(): void
    {
        $selectedId = (int) ($_GET['warehouse_id'] ?? 0);
        $warehouses = Database::query(
            "SELECT * FROM warehouses WHERE is_active = 1 ORDER BY name"
        )->fetchAll();

        $sql = "SELECT s.warehouse_id, w.name AS warehouse_name, s.item_id,
                       i.name AS item_name, i.barcode, u.name AS unit_name, i.unit_id, s.qty
                FROM stock s
                JOIN warehouses w ON w.id = s.warehouse_id
                JOIN items i ON i.id = s.item_id
                LEFT JOIN units u ON u.id = i.unit_id
                WHERE s.qty > 0 AND w.is_active = 1";
        $params = [];
        if ($selectedId) {
            $sql .= " AND s.warehouse_id = ?";
            $params[] = $selectedId;
        }
        $sql .= " ORDER BY w.name, i.name";
        $stock = Database::query($sql, $params)->fetchAll();
        foreach ($stock as &$row) {
            $row['qty'] = UnitConversionService::fromBaseUnit((int) $row['unit_id'], (float) $row['qty']);
        }
        unset($row);

        $byWarehouse = [];
        foreach ($warehouses as $w) {
            $byWarehouse[(int)$w['id']] = ['info' => $w, 'items' => []];
        }
        foreach ($stock as $row) {
            $wid = (int)$row['warehouse_id'];
            if (isset($byWarehouse[$wid])) {
                $byWarehouse[$wid]['items'][] = $row;
            }
        }

        $this->view('inventory/warehouse_items', [
            'title' => 'الأصناف حسب المخزن',
            'warehouses' => $warehouses,
            'selectedId' => $selectedId,
            'byWarehouse' => $selectedId ? array_intersect_key($byWarehouse, [$selectedId => 1]) : $byWarehouse,
        ]);
    }

    /** تصدير جدول الأصناف حسب المخزن إلى Excel أو PDF */
    public function exportItems(): void
    {
        $selectedId = (int) ($_GET['warehouse_id'] ?? 0);
        $format = (string) ($_GET['format'] ?? 'pdf');

        $sql = "SELECT s.warehouse_id, w.name AS warehouse_name, s.item_id,
                       i.name AS item_name, i.barcode, u.name AS unit_name, i.unit_id, s.qty
                FROM stock s
                JOIN warehouses w ON w.id = s.warehouse_id
                JOIN items i ON i.id = s.item_id
                LEFT JOIN units u ON u.id = i.unit_id
                WHERE s.qty > 0 AND w.is_active = 1";
        $params = [];
        if ($selectedId) {
            $sql .= " AND s.warehouse_id = ?";
            $params[] = $selectedId;
        }
        $sql .= " ORDER BY w.name, i.name";
        $rows = Database::query($sql, $params)->fetchAll();
        foreach ($rows as &$row) {
            $row['qty'] = UnitConversionService::fromBaseUnit((int) $row['unit_id'], (float) $row['qty']);
        }
        unset($row);

        $headers = $selectedId
            ? ['الباركود', 'المادة', 'الوحدة', 'الرصيد']
            : ['المخزن', 'الباركود', 'المادة', 'الوحدة', 'الرصيد'];
        $data = [];
        foreach ($rows as $r) {
            $data[] = $selectedId
                ? [$r['barcode'] ?? '', $r['item_name'], $r['unit_name'] ?? '', (float)$r['qty']]
                : [$r['warehouse_name'], $r['barcode'] ?? '', $r['item_name'], $r['unit_name'] ?? '', (float)$r['qty']];
        }

        $title = $selectedId ? 'الأصناف في المخزن المختار' : 'الأصناف في جميع المخازن';
        if ($format === 'excel') {
            ExportHelper::excel('warehouse_items_' . date('Ymd') . '.xlsx', [[
                'title' => $title,
                'report_title' => $title,
                'period' => date('Y-m-d'),
                'headers' => $headers,
                'rows' => $data,
            ]]);
        }
        ExportHelper::pdf('warehouse_items_' . date('Ymd') . '.pdf', [
            'title' => $title,
            'period' => date('Y-m-d'),
            'headers' => $headers,
            'rows' => $data,
        ]);
    }

    public function store(): void
    {
        $name = (string) $this->input('name');
        $location = (string) $this->input('location');
        $type = (string) $this->input('warehouse_type', 'warehouse');
        $allowed = ['warehouse', 'department', 'sales', 'manufacturing'];
        if (!in_array($type, $allowed, true)) {
            $type = 'warehouse';
        }
        if ($name === '') {
            $this->flash('danger', 'اسم المخزن مطلوب');
            Response::redirect('/warehouses');
        }
        Database::query(
            "INSERT INTO warehouses (branch_id, name, location, warehouse_type) VALUES (?,?,?,?)",
            [Auth::branchId() ?? 1, $name, $location, $type]
        );
        $this->flash('success', 'تم إضافة المخزن');
        Response::redirect('/warehouses');
    }

    public function update(string $id): void
    {
        $warehouse = Database::query("SELECT * FROM warehouses WHERE id = ?", [(int)$id])->fetch();
        if (!$warehouse) {
            $this->flash('danger', 'المخزن غير موجود');
            Response::redirect('/warehouses');
        }
        $name = (string) $this->input('name');
        $location = (string) $this->input('location');
        $type = (string) $this->input('warehouse_type', $warehouse['warehouse_type']);
        $allowed = ['warehouse', 'department', 'sales', 'manufacturing'];
        if (!in_array($type, $allowed, true)) {
            $type = $warehouse['warehouse_type'];
        }
        if ($name === '') {
            $this->flash('danger', 'اسم المخزن مطلوب');
            Response::redirect('/warehouses');
        }
        Database::query(
            "UPDATE warehouses SET name = ?, location = ?, warehouse_type = ? WHERE id = ?",
            [$name, $location, $type, (int)$id]
        );
        $this->flash('success', 'تم تحديث المخزن');
        Response::redirect('/warehouses');
    }

    public function destroy(string $id): void
    {
        $warehouse = Database::query("SELECT * FROM warehouses WHERE id = ?", [(int)$id])->fetch();
        if (!$warehouse) {
            $this->flash('danger', 'المخزن غير موجود');
            Response::redirect('/warehouses');
        }
        // نمنع الحذف الفعلي للحفاظ على الحركات والأرصدة؛ نكتفي بالتعطيل
        Database::query(
            "UPDATE warehouses SET is_active = 0, name = CONCAT(name, ' (معطل)') WHERE id = ?",
            [(int)$id]
        );
        $this->flash('success', 'تم تعطيل المخزن');
        Response::redirect('/warehouses');
    }
}
