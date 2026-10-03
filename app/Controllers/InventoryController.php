<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Auth, Helpers, Response, StockService, ApprovalService, ExportHelper, ImportHelper, UnitConversionService, BarcodeService, AccountingService};
use App\Models\ItemPriceHistory;
use App\Models\ItemCostSheet;

class InventoryController extends Controller
{
    public function index(): void
    {
        $type = (string) $this->input('type', 'all');
        $validTypes = ['raw', 'prepared', 'asset', 'tool', 'clothing', 'other'];
        $params = [];
        $where = 'WHERE i.is_active = 1';
        if (in_array($type, $validTypes, true)) {
            $where .= ' AND c.type = ?';
            $params[] = $type;
        }

        $items = Database::query(
            "SELECT i.*, c.name AS category_name, c.type AS category_type, u.name AS unit_name,
                    COALESCE(SUM(s.qty),0) AS total_qty
             FROM items i
             JOIN item_categories c ON c.id = i.category_id
             JOIN units u ON u.id = i.unit_id
             LEFT JOIN stock s ON s.item_id = i.id
             $where
             GROUP BY i.id ORDER BY i.name",
            $params
        )->fetchAll();
        foreach ($items as &$it) {
            $it['total_qty'] = UnitConversionService::fromBaseUnit((int) $it['unit_id'], (float) $it['total_qty']);
        }
        unset($it);
        $categories = Database::query("SELECT * FROM item_categories")->fetchAll();
        $units = Database::query("SELECT * FROM units")->fetchAll();

        $this->view('inventory/index', [
            'title' => 'الأصناف والأرصدة', 'items' => $items,
            'categories' => $categories, 'units' => $units,
            'lowStock' => StockService::lowStock(),
            'type' => $type,
        ]);
    }

    public function exportInventory(): void
    {
        $type = (string) $this->input('type', 'all');
        $validTypes = ['raw', 'prepared', 'asset', 'tool', 'clothing', 'other'];
        $params = [];
        $where = 'WHERE i.is_active = 1';
        if (in_array($type, $validTypes, true)) {
            $where .= ' AND c.type = ?';
            $params[] = $type;
        }

        $items = Database::query(
            "SELECT i.*, c.name AS category_name, c.type AS category_type, u.name AS unit_name,
                    COALESCE(SUM(s.qty),0) AS total_qty
             FROM items i
             JOIN item_categories c ON c.id = i.category_id
             JOIN units u ON u.id = i.unit_id
             LEFT JOIN stock s ON s.item_id = i.id
             $where
             GROUP BY i.id ORDER BY i.name",
            $params
        )->fetchAll();
        foreach ($items as &$it) {
            $it['total_qty'] = UnitConversionService::fromBaseUnit((int) $it['unit_id'], (float) $it['total_qty']);
        }
        unset($it);

        $headers = ['الباركود', 'المادة', 'التصنيف', 'نوع التصنيف', 'الوحدة', 'الرصيد', 'حد التنبيه', 'سعر الكلفة', 'تتبع الصلاحية'];
        $rows = [];
        foreach ($items as $it) {
            $rows[] = [
                $it['barcode'] ?? '',
                $it['name'],
                $it['category_name'] ?? '',
                $it['category_type'] ?? '',
                $it['unit_name'] ?? '',
                (float) $it['total_qty'],
                (float) $it['min_qty'],
                (float) $it['cost_price'],
                (int) $it['track_expiry'] ? 'نعم' : 'لا',
            ];
        }

        $typeLabels = ['all'=>'الكل','raw'=>'مواد أولية','prepared'=>'مواد محضرة','asset'=>'موجودات','tool'=>'أدوات','clothing'=>'ملابس','other'=>'أخرى'];
        $typeLabel = $typeLabels[$type] ?? 'الكل';

        $format = (string) $this->input('format', 'pdf');
        if ($format === 'excel') {
            ExportHelper::excel('inventory_items_' . date('Ymd') . '.xlsx', [[
                'title' => 'الأصناف والأرصدة',
                'report_title' => 'تقرير الأصناف والأرصدة',
                'period' => 'التصنيف: ' . $typeLabel,
                'headers' => $headers, 'rows' => $rows,
            ]]);
        }
        ExportHelper::pdf('inventory_items_' . date('Ymd') . '.pdf', [
            'title' => 'تقرير الأصناف والأرصدة',
            'period' => 'التصنيف: ' . $typeLabel,
            'headers' => $headers, 'rows' => $rows,
        ]);
    }

    public function importTemplate(): void
    {
        ImportHelper::downloadTemplate(
            ['الباركود', 'الاسم', 'التصنيف', 'الوحدة', 'حد التنبيه', 'سعر الكلفة', 'تتبع الصلاحية'],
            'inventory_items_template.xlsx',
            ['123456789', 'مادة تجريبية', 'مواد أولية', 'كيلوغرام', '10', '5000', 'لا']
        );
    }

    public function importInventory(): void
    {
        if (empty($_FILES['import_file']['tmp_name']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('danger', 'لم يتم اختيار ملف');
            Response::redirect('/inventory');
        }

        $read = ImportHelper::readFile($_FILES['import_file']['tmp_name']);
        if ($read['errors']) {
            ImportHelper::flashErrors($read['errors']);
            $this->flash('danger', 'خطأ في قراءة الملف');
            Response::redirect('/inventory');
        }

        $rows = $read['rows'];
        $errors = array_merge(
            ImportHelper::validateRequired($rows, ['الاسم', 'التصنيف', 'الوحدة']),
            ImportHelper::validateUnique($rows, 'الباركود')
        );

        // التحقق من عدم تكرار الباركود في قاعدة البيانات
        $barcodes = [];
        foreach ($rows as $row) {
            $b = trim((string) ($row['الباركود'] ?? ''));
            if ($b !== '') { $barcodes[] = $b; }
        }
        if ($barcodes) {
            $in = implode(',', array_fill(0, count($barcodes), '?'));
            $existing = Database::query("SELECT barcode FROM items WHERE barcode IN ($in)", $barcodes)->fetchAll();
            $existing = array_column($existing, 'barcode');
            foreach ($rows as $idx => $row) {
                $b = trim((string) ($row['الباركود'] ?? ''));
                if ($b && in_array($b, $existing, true)) {
                    $errors[] = 'الصف ' . ($idx + 2) . ': الباركود "' . $b . '" موجود مسبقاً';
                }
            }
        }

        // مائة التصنيفات والوحدات
        $categories = Database::query("SELECT id, name, type FROM item_categories")->fetchAll();
        $catMap = [];
        foreach ($categories as $c) {
            $catMap[mb_strtolower(trim($c['name']))] = $c;
        }
        $units = Database::query("SELECT id, name FROM units")->fetchAll();
        $unitMap = [];
        foreach ($units as $u) {
            $unitMap[mb_strtolower(trim($u['name']))] = $u['id'];
        }

        foreach ($rows as $idx => $row) {
            $catName = trim((string) ($row['التصنيف'] ?? ''));
            $unitName = trim((string) ($row['الوحدة'] ?? ''));
            $catKey = mb_strtolower($catName);
            $unitKey = mb_strtolower($unitName);
            if (!isset($catMap[$catKey])) {
                $errors[] = 'الصف ' . ($idx + 2) . ': التصنيف "' . $catName . '" غير معروف';
            }
            if (!isset($unitMap[$unitKey])) {
                $errors[] = 'الصف ' . ($idx + 2) . ': الوحدة "' . $unitName . '" غير معروفة';
            }
        }

        if ($errors) {
            ImportHelper::flashErrors($errors);
            $this->flash('danger', 'يوجد أخطاء في ملف الاستيراد');
            Response::redirect('/inventory');
        }

        Database::beginTransaction();
        try {
            foreach ($rows as $row) {
                $catKey = mb_strtolower(trim((string) $row['التصنيف']));
                $unitKey = mb_strtolower(trim((string) $row['الوحدة']));
                $barcode = trim((string) ($row['الباركود'] ?? ''));
                $barcode = $barcode !== '' ? $barcode : null;
                $name = trim((string) $row['الاسم']);
                $minQty = is_numeric($row['حد التنبيه'] ?? '') ? (float) $row['حد التنبيه'] : 0;
                $costPrice = is_numeric($row['سعر الكلفة'] ?? '') ? (float) $row['سعر الكلفة'] : 0;
                $trackExpiry = in_array(strtolower(trim((string) ($row['تتبع الصلاحية'] ?? ''))), ['1', 'yes', 'نعم'], true);

                Database::query(
                    "INSERT INTO items (barcode, name, category_id, unit_id, min_qty, cost_price, track_expiry)
                     VALUES (?,?,?,?,?,?,?)",
                    [
                        $barcode,
                        $name,
                        (int) $catMap[$catKey]['id'],
                        (int) $unitMap[$unitKey],
                        $minQty,
                        $costPrice,
                        $trackExpiry ? 1 : 0,
                    ]
                );
                $itemId = (int) Database::lastInsertId();
                if ($costPrice > 0) {
                    ItemPriceHistory::record($itemId, $costPrice, date('Y-m-d'), 'استيراد', Auth::id());
                }
            }
            Database::commit();
            $this->flash('success', 'تم استيراد ' . count($rows) . ' مادة بنجاح');
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->flash('danger', 'خطأ أثناء الاستيراد: ' . $e->getMessage());
        }
        Response::redirect('/inventory');
    }


    public function storeCategory(): void
    {
        $name = trim((string) $this->input('name'));
        $type = (string) $this->input('type', 'raw');
        $allowed = ['raw', 'prepared', 'asset', 'tool', 'clothing', 'other'];
        if ($name === '' || !in_array($type, $allowed, true)) {
            $this->flash('danger', 'بيانات التصنيف غير صالحة');
            Response::redirect('/inventory');
        }
        try {
            Database::query(
                "INSERT INTO item_categories (name, type) VALUES (?,?)",
                [$name, $type]
            );
            $this->flash('success', 'تم إضافة التصنيف: ' . $name);
        } catch (\Throwable $e) {
            $this->flash('warning', 'التصنيف موجود مسبقاً أو حدث خطأ');
        }
        Response::redirect('/inventory');
    }

    public function storeItem(): void
    {
        $categoryId = (int) $this->input('category_id');
        if ($categoryId <= 0) {
            $this->flash('danger', 'يجب اختيار التصنيف');
            Response::redirect('/inventory');
        }

        $barcode = trim((string) $this->input('barcode'));
        if ($barcode !== '') {
            $exists = Database::query("SELECT id FROM items WHERE barcode = ?", [$barcode])->fetch();
            if ($exists) {
                $this->flash('danger', 'الباركود مستخدم مسبقاً');
                Response::redirect('/inventory');
            }
        }

        $costPrice = (float) $this->input('cost_price', 0);
        Database::query(
            "INSERT INTO items (barcode, name, category_id, unit_id, min_qty, cost_price, track_expiry)
             VALUES (?,?,?,?,?,?,?)",
            [
                $barcode !== '' ? $barcode : null,
                (string) $this->input('name'),
                $categoryId,
                (int) $this->input('unit_id'),
                (float) $this->input('min_qty', 0),
                $costPrice,
                (int) (bool) $this->input('track_expiry'),
            ]
        );
        $itemId = (int) Database::lastInsertId();
        if ($costPrice > 0) {
            ItemPriceHistory::record($itemId, $costPrice, date('Y-m-d'), 'إنشاء مادة', Auth::id());
        }
        $this->flash('success', 'تم إضافة المادة');
        Response::redirect('/inventory');
    }

    /** حذف مادة (حذف ناعم) */
    public function deleteItem(string $id): void
    {
        $itemId = (int) $id;
        $item = Database::query("SELECT id, name FROM items WHERE id = ? AND is_active = 1", [$itemId])->fetch();
        if (!$item) {
            $this->flash('danger', 'المادة غير موجودة أو محذوفة');
            Response::redirect('/inventory');
        }

        Database::query("UPDATE items SET is_active = 0 WHERE id = ?", [$itemId]);
        Helpers::audit('حذف مادة', 'items', $itemId, $item['name']);
        $this->flash('success', 'تم حذف المادة: ' . $item['name']);
        Response::redirect('/inventory');
    }

    /** نموذج تحديث سعر الشراء مع تاريخ فعّال */
    public function priceForm(string $id): void
    {
        $itemId = (int) $id;
        $item = Database::query(
            "SELECT i.*, u.name AS unit_name, c.name AS category_name
             FROM items i
             JOIN units u ON u.id = i.unit_id
             JOIN item_categories c ON c.id = i.category_id
             WHERE i.id = ? AND i.is_active = 1",
            [$itemId]
        )->fetch();
        if (!$item) {
            Response::abort(404, 'المادة غير موجودة');
        }
        $history = ItemPriceHistory::list($itemId);
        $this->view('inventory/item_price_form', [
            'title' => 'تحديث سعر: ' . $item['name'],
            'item' => $item,
            'history' => $history,
        ]);
    }

    /** تعديل سعر الشراء وتسجيله في التاريخ */
    public function updatePrice(string $id): void
    {
        $itemId = (int) $id;
        $item = Database::query("SELECT id, name, cost_price FROM items WHERE id = ? AND is_active = 1", [$itemId])->fetch();
        if (!$item) {
            $this->flash('danger', 'المادة غير موجودة');
            Response::redirect('/inventory');
        }
        $newPrice = (float) $this->input('cost_price', 0);
        $effectiveDate = (string) $this->input('effective_date', date('Y-m-d'));
        if ($newPrice <= 0) {
            $this->flash('danger', 'السعر يجب أن يكون أكبر من صفر');
            Response::redirect('/inventory/items/' . $itemId . '/price');
        }
        Database::beginTransaction();
        try {
            Database::query("UPDATE items SET cost_price = ? WHERE id = ?", [$newPrice, $itemId]);
            ItemPriceHistory::record($itemId, $newPrice, $effectiveDate, 'تحديث يدوي', Auth::id());
            Database::commit();
            Helpers::audit('تحديث سعر مادة', 'items', $itemId, $item['name'] . ' -> ' . $newPrice);
            $this->flash('success', 'تم تحديث السعر وتسجيل التاريخ الفعّال');
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->flash('danger', 'خطأ: ' . $e->getMessage());
        }
        Response::redirect('/inventory');
    }

    /** بحث مادة بالباركود للإجاكس الويب */
    public function barcode(): void
    {
        $code = trim((string) $this->input('code'));
        $warehouseId = (int) $this->input('warehouse_id', 0);
        if ($code === '') {
            Response::json(['ok' => false, 'error' => 'الباركود فارغ']);
            return;
        }
        $item = BarcodeService::findItem($code, $warehouseId > 0 ? $warehouseId : null);
        if (!$item) {
            Response::json(['ok' => false, 'error' => 'المادة غير موجودة']);
            return;
        }
        $item['total_balance'] = StockService::totalBalance((int) $item['id']);
        Response::json(['ok' => true, 'data' => $item]);
    }

    public function movements(): void
    {
        $movements = Database::query(
            "SELECT m.*, i.name AS item_name, w.name AS warehouse_name,
                    dw.name AS destination_warehouse_name, u.full_name
             FROM stock_movements m
             JOIN items i ON i.id = m.item_id
             JOIN warehouses w ON w.id = m.warehouse_id
             LEFT JOIN warehouses dw ON dw.id = m.destination_warehouse_id
             LEFT JOIN users u ON u.id = m.created_by
             ORDER BY m.id DESC LIMIT 200"
        )->fetchAll();
        $items = Database::query("SELECT id, name, barcode, unit_id FROM items WHERE is_active=1 ORDER BY name")->fetchAll();
        $warehouses = Database::query("SELECT * FROM warehouses WHERE is_active=1")->fetchAll();
        $units = Database::query("SELECT * FROM units ORDER BY name")->fetchAll();

        $format = (string)($_GET['format'] ?? '');
        if ($format === 'excel' || $format === 'pdf') {
            $typeLabels = ['in'=>'إدخال','out'=>'إخراج','transfer_in'=>'مناقلة واردة','transfer_out'=>'مناقلة صادرة',
                            'adjustment'=>'تسوية','consumption'=>'استهلاك','return'=>'مرتجع'];
            $rows = array_map(fn($m) => [
                $m['item_name'], $m['warehouse_name'], $typeLabels[$m['movement_type']] ?? $m['movement_type'],
                (float)$m['qty'], $m['expiry_date'] ?? '-', $m['reference'] ?? '-', $m['full_name'] ?? '-', $m['created_at']
            ], $movements);
            if ($format === 'excel') {
                ExportHelper::excel('inventory_movements_' . date('Y-m-d') . '.xlsx', [[
                    'title' => 'حركات المخزون', 'report_title' => 'حركات المخزون', 'period' => date('Y-m-d'),
                    'headers' => ['المادة', 'المخزن', 'النوع', 'الكمية', 'الصلاحية', 'المرجع', 'بواسطة', 'الوقت'], 'rows' => $rows, 'totals' => [],
                ]]);
            }
            ExportHelper::pdf('inventory_movements_' . date('Y-m-d') . '.pdf', [
                'title' => 'حركات المخزون', 'period' => date('Y-m-d'),
                'headers' => ['المادة', 'المخزن', 'النوع', 'الكمية', 'الصلاحية', 'المرجع', 'بواسطة', 'الوقت'], 'rows' => $rows, 'totals' => [],
            ]);
        }

        $this->view('inventory/movements', [
            'title' => 'حركات المخزون', 'movements' => $movements,
            'items' => $items, 'warehouses' => $warehouses, 'units' => $units,
        ]);
    }

    public function storeMovement(): void
    {
        $item = $this->resolveItem($this->input('barcode'), $this->input('item_id'));
        if (!$item) {
            $this->flash('danger', 'الباركود أو المادة غير معروفة');
            Response::redirect('/inventory/movements');
        }
        $itemId = (int) $item['id'];
        $warehouseId = (int) $this->input('warehouse_id');
        $unitId = (int) $this->input('unit_id', $item['unit_id']);
        $qty = (float) $this->input('qty', 0);
        $type = (string) $this->input('movement_type', 'in');
        $allowed = ['in', 'out', 'adjustment', 'return', 'consumption'];
        if (!in_array($type, $allowed, true)) {
            $this->flash('danger', 'نوع الحركة غير صالح');
            Response::redirect('/inventory/movements');
        }

        if (in_array($type, ['out', 'consumption'], true)) {
            $baseQty = UnitConversionService::toBaseUnit($unitId, $qty);
            $balance = StockService::balance($warehouseId, $itemId);
            if ($balance < $baseQty - 0.0001) {
                $this->flash('danger', 'الكمية في المخزن غير كافية (متوفر: ' . $balance . ')');
                Response::redirect('/inventory/movements');
            }
        }

        $destinationWarehouseId = null;
        if (in_array($type, ['out', 'transfer_out'], true)) {
            $destinationWarehouseId = (int) $this->input('destination_warehouse_id', 0);
            if ($destinationWarehouseId > 0) {
                $destinationExists = Database::query(
                    "SELECT id FROM warehouses WHERE id = ? AND is_active = 1",
                    [$destinationWarehouseId]
                )->fetch();
                if (!$destinationExists) {
                    $this->flash('danger', 'المخزن/القسم الوجهة غير موجود أو غير نشط');
                    Response::redirect('/inventory/movements');
                }
            }
        }

        $unitCost = (float) $this->input('unit_cost', 0);
        $reference = (string) $this->input('reference');
        $notes = (string) $this->input('notes');
        $expiryDate = $this->input('expiry_date') ?: null;

        Database::beginTransaction();
        try {
            StockService::moveWithUnit(
                $warehouseId,
                $itemId,
                $type,
                $qty,
                $unitId,
                $unitCost,
                $reference,
                $notes,
                $expiryDate,
                $destinationWarehouseId > 0 ? $destinationWarehouseId : null
            );

            // عند الإخراج إلى مخزن/قسم وجهة، نسجّل إدخال مقابل في الوجهة
            if ($destinationWarehouseId > 0 && $destinationWarehouseId !== $warehouseId) {
                StockService::moveWithUnit(
                    $destinationWarehouseId,
                    $itemId,
                    'in',
                    $qty,
                    $unitId,
                    $unitCost,
                    $reference,
                    $notes,
                    $expiryDate
                );
                $this->flash('success', 'تم نقل المادة إلى المخزن/القسم الوجهة');
            } else {
                $this->flash('success', 'تم تسجيل الحركة');
            }
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->flash('danger', 'خطأ أثناء تسجيل الحركة: ' . $e->getMessage());
        }
        Response::redirect('/inventory/movements');
    }

    public function transfers(): void
    {
        $transfers = Database::query(
            "SELECT t.*, wf.name AS from_name, wt.name AS to_name
             FROM stock_transfers t
             JOIN warehouses wf ON wf.id = t.from_warehouse_id
             JOIN warehouses wt ON wt.id = t.to_warehouse_id
             ORDER BY t.id DESC LIMIT 100"
        )->fetchAll();
        $warehouses = Database::query("SELECT * FROM warehouses WHERE is_active=1")->fetchAll();
        $items = Database::query("SELECT id, name FROM items WHERE is_active=1 ORDER BY name")->fetchAll();

        $this->view('inventory/transfers', [
            'title' => 'المناقلات المخزنية', 'transfers' => $transfers,
            'warehouses' => $warehouses, 'items' => $items,
        ]);
    }

    public function storeTransfer(): void
    {
        $items = (array) $this->input('items', []);

        // توافق مع نموذج الواجهة البسيط الذي يرسل صنف واحد مباشرة
        if (empty($items)) {
            $itemId = (int) $this->input('item_id');
            $qty     = (float) $this->input('qty');
            if ($itemId > 0 && $qty > 0) {
                $items = [['item_id' => $itemId, 'qty' => $qty]];
            }
        }

        if (empty($items)) {
            $this->flash('danger', 'يجب إضافة بند واحد على المناقلة');
            Response::redirect('/inventory/transfers');
        }

        $no = Helpers::docNumber('TRF', 'stock_transfers', 'transfer_no');
        Database::beginTransaction();
        try {
            Database::query(
                "INSERT INTO stock_transfers (transfer_no, from_warehouse_id, to_warehouse_id, requested_by, notes)
                 VALUES (?,?,?,?,?)",
                [$no, (int) $this->input('from_warehouse_id'), (int) $this->input('to_warehouse_id'),
                 Auth::id(), (string) $this->input('notes')]
            );
            $transferId = (int) Database::lastInsertId();

            foreach ($items as $line) {
                $line = (array) $line;
                $item = $this->resolveItem($line['barcode'] ?? null, $line['item_id'] ?? null);
                if (!$item) {
                    throw new \Exception('بند غير معروف: ' . ($line['barcode'] ?? $line['item_id'] ?? ''));
                }
                $unitId = (int) ($line['unit_id'] ?? $item['unit_id']);
                $qty = (float) ($line['qty'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }
                $baseQty = UnitConversionService::toBaseUnit($unitId, $qty);
                Database::query(
                    "INSERT INTO stock_transfer_items (transfer_id, item_id, qty, unit_id) VALUES (?,?,?,?)",
                    [$transferId, (int) $item['id'], $baseQty, $unitId]
                );
            }

            ApprovalService::request('stock_transfer', $transferId);
            Database::commit();
            $this->flash('success', "تم إنشاء المناقلة $no وأرسلت للموافقة");
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->flash('danger', 'خطأ: ' . $e->getMessage());
        }
        Response::redirect('/inventory/transfers');
    }

    public function deliverTransfer(string $id): void
    {
        try {
            StockService::deliverTransfer((int)$id);
            $this->flash('success', 'تم تسليم المناقلة وتحديث الأرصدة');
        } catch (\Throwable $e) {
            $this->flash('danger', 'خطأ: ' . $e->getMessage());
        }
        Response::redirect('/inventory/transfers');
    }

    public function stocktakes(): void
    {
        $stocktakes = Database::query(
            "SELECT st.*, w.name AS warehouse_name, u.full_name,
                    (SELECT COUNT(*) FROM stocktake_items si WHERE si.stocktake_id = st.id) AS items_count
             FROM stocktakes st
             JOIN warehouses w ON w.id = st.warehouse_id
             LEFT JOIN users u ON u.id = st.created_by
             ORDER BY st.id DESC LIMIT 50"
        )->fetchAll();
        $warehouses = Database::query("SELECT * FROM warehouses WHERE is_active=1")->fetchAll();
        $items = Database::query("SELECT id, name FROM items WHERE is_active=1 ORDER BY name")->fetchAll();

        $this->view('inventory/stocktakes', [
            'title' => 'الجرد', 'stocktakes' => $stocktakes,
            'warehouses' => $warehouses, 'items' => $items,
        ]);
    }

    public function storeStocktake(): void
    {
        $warehouseId = (int) $this->input('warehouse_id');
        $item = $this->resolveItem($this->input('barcode'), $this->input('item_id'));
        if (!$item) {
            $this->flash('danger', 'الباركود أو المادة غير معروفة');
            Response::redirect('/inventory/stocktakes');
        }
        $itemId = (int) $item['id'];
        $unitId = (int) $this->input('unit_id', $item['unit_id']);
        $counted = (float) $this->input('counted_qty');
        $countedBase = UnitConversionService::toBaseUnit($unitId, $counted);
        $systemQty = StockService::balance($warehouseId, $itemId);

        Database::beginTransaction();
        try {
            Database::query(
                "INSERT INTO stocktakes (warehouse_id, stocktake_type, status, created_by, closed_at)
                 VALUES (?,?,'closed',?,NOW())",
                [$warehouseId, (string) $this->input('stocktake_type', 'periodic'), Auth::id()]
            );
            $stId = (int) Database::lastInsertId();
            Database::query(
                "INSERT INTO stocktake_items (stocktake_id, item_id, system_qty, counted_qty) VALUES (?,?,?,?)",
                [$stId, $itemId, $systemQty, $countedBase]
            );
            // تسوية الفرق تلقائياً
            $diff = $countedBase - $systemQty;
            if (abs($diff) > 0.0001) {
                StockService::move($warehouseId, $itemId, 'adjustment', $diff, 0, 'ST-' . $stId, 'تسوية جرد');
            }
            AccountingService::postStocktake($stId);
            Database::commit();
            $this->flash('success', 'تم الجرد والتسوية (الفرق: ' . $diff . ')');
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->flash('danger', 'خطأ: ' . $e->getMessage());
        }
        Response::redirect('/inventory/stocktakes');
    }

    public function reports(): void
    {
        $type = (string) $this->input('type', 'all');
        $valid = ['raw', 'prepared', 'asset', 'tool', 'clothing', 'all'];
        if (!in_array($type, $valid, true)) {
            $type = 'all';
        }
        $where = $type === 'all' ? '' : "WHERE c.type = ?";
        $params = $type === 'all' ? [] : [$type];

        $rows = Database::query(
            "SELECT c.type AS category_type, COUNT(i.id) AS items_count,
                    COALESCE(SUM(s.qty),0) AS total_qty,
                    COALESCE(SUM(s.qty * i.cost_price),0) AS total_value
             FROM items i
             JOIN item_categories c ON c.id = i.category_id
             LEFT JOIN stock s ON s.item_id = i.id
             $where
             GROUP BY c.type
             ORDER BY c.type",
            $params
        )->fetchAll();

        $this->view('inventory/reports', [
            'title' => 'تقرير المخزون حسب التصنيف',
            'rows' => $rows,
            'type' => $type,
        ]);
    }

    /** صفحة تخطيط تكاليف الأطباق (مراحل التنفيذ) */
    public function plateCosts(): void
    {
        $phases = [
            [
                'title' => 'المرحلة 1: التحليل',
                'points' => [
                    'تحليل منطق Excel وقواعد العمل بالكامل',
                    'حصر جميع الصيغ والعلاقات (SUMIF/VLOOKUP/LOOKUP)',
                    'توثيق الاعتماديات بين الأوراق والبيانات',
                ],
            ],
            [
                'title' => 'المرحلة 2: تصميم قاعدة البيانات',
                'points' => [
                    'تصميم ERD والجداول الأساسية (Items, Units, Categories, Suppliers, Purchase Prices, Recipes...)',
                    'إضافة المفاتيح الأجنبية والفهارس للأداء',
                ],
            ],
            [
                'title' => 'المرحلة 3: طبقة Laravel',
                'points' => [
                    'النماذج والعلاقات والسياسات',
                    'Repositories + Services + DTO/Actions',
                ],
            ],
            [
                'title' => 'المرحلة 4: محرك التكلفة',
                'points' => [
                    'Actual Consumption = الكمية × (هدر المادة + هدر القسم + هدر الناتج)',
                    'Raw: آخر سعر شراء تاريخي — Prepared: تكلفة آخر Batch',
                    'يدعم FIFO / Moving Average / Standard + Snapshot Engine',
                ],
            ],
            [
                'title' => 'المرحلة 5: الشاشات والتقارير',
                'points' => [
                    'شاشة تكلفة الطبخة + تفاصيل المكونات والمؤشرات (Food Cost%, GP%, Contribution...)',
                    'تقارير التكلفة والهدر وتغير الأسعار والمقارنات',
                ],
            ],
            [
                'title' => 'المرحلة 6: الاختبارات والأداء',
                'points' => [
                    'Unit/Feature/Performance Tests',
                    'التحسين والفهارس والتخزين المؤقت',
                ],
            ],
        ];
        // قسم حسابات مباشرة مبسّط: يعرض تكلفة الوحدة التاريخية اليوم/بتاريخ مُختار لقوالب التكلفة
        $date = (string) $this->input('date', date('Y-m-d'));
        $sheets = Database::query(
            "SELECT cs.id, cs.item_id, cs.sale_price, cs.yield_qty, i.name AS item_name, i.barcode,
                    u.name AS yield_unit_name
             FROM item_cost_sheets cs
             JOIN items i ON i.id = cs.item_id
             LEFT JOIN units u ON u.id = cs.yield_unit_id
             ORDER BY i.name"
        )->fetchAll();
        $live = [];
        foreach ($sheets as $s) {
            $unitCost = ItemCostSheet::costAtDate((int)$s['item_id'], $date);
            $sale = (float) ($s['sale_price'] ?? 0);
            $gp = $sale - $unitCost;
            $fcPct = $sale > 0 ? ($unitCost / $sale) * 100 : null;
            $gpPct = $sale > 0 ? ($gp / $sale) * 100 : null;
            $live[] = [
                'item_id' => (int)$s['item_id'],
                'item_name' => $s['item_name'],
                'barcode' => $s['barcode'] ?? null,
                'yield_qty' => (float)($s['yield_qty'] ?? 1),
                'yield_unit_name' => $s['yield_unit_name'] ?? '',
                'unit_cost' => round((float)$unitCost, 4),
                'sale_price' => $sale,
                'food_cost_pct' => $fcPct === null ? null : round($fcPct, 1),
                'gp' => round($gp, 2),
                'gp_pct' => $gpPct === null ? null : round($gpPct, 1),
            ];
        }

        // 
        // حسابات وصفات المنيو (بدون اعتماد على قوالب التكلفة)
        $recipes = Database::query(
            "SELECT mi.id, mi.name, mi.price
             FROM menu_items mi
             WHERE mi.is_active = 1 AND EXISTS (
               SELECT 1 FROM recipes r JOIN recipe_ingredients ri ON ri.recipe_id = r.id
               WHERE r.menu_item_id = mi.id
             ) ORDER BY mi.name"
        )->fetchAll();
        $menuLive = [];
        foreach ($recipes as $m) {
            // تكلفة حصة واحدة بالتاريخ المختار
            $u = \App\Core\AccountingService::recipeCost((int)$m['id'], 1.0, $date);
            $sale = (float)($m['price'] ?? 0);
            $gp = $sale - $u;
            $fcPct = $sale > 0 ? ($u / $sale) * 100 : null;
            $gpPct = $sale > 0 ? ($gp / $sale) * 100 : null;
            $menuLive[] = [
                'menu_item_id' => (int)$m['id'],
                'name' => $m['name'],
                'unit_cost' => round((float)$u, 4),
                'sale_price' => $sale,
                'food_cost_pct' => $fcPct === null ? null : round($fcPct, 1),
                'gp' => round($gp, 2),
                'gp_pct' => $gpPct === null ? null : round($gpPct, 1),
            ];
        }

        // تصدير (اختياري)
        $format = (string) $this->input('format', '');
        if ($format === 'excel' || $format === 'pdf') {
            $sheetsRows = array_map(function($r) {
                return [
                    $r['item_name'], $r['barcode'] ?? '-', $r['yield_qty'] . ' ' . ($r['yield_unit_name'] ?? ''),
                    number_format((float)$r['unit_cost'], 2), number_format((float)$r['sale_price'], 2),
                    isset($r['food_cost_pct']) ? number_format((float)$r['food_cost_pct'], 1).'%' : '-',
                    number_format((float)$r['gp'], 2), isset($r['gp_pct']) ? number_format((float)$r['gp_pct'], 1).'%' : '-',
                ];
            }, $live);
            $menuRows = array_map(function($r) {
                return [
                    $r['name'], number_format((float)$r['unit_cost'], 2), number_format((float)$r['sale_price'], 2),
                    isset($r['food_cost_pct']) ? number_format((float)$r['food_cost_pct'], 1).'%' : '-',
                    number_format((float)$r['gp'], 2), isset($r['gp_pct']) ? number_format((float)$r['gp_pct'], 1).'%' : '-',
                ];
            }, $menuLive);
            $filename = 'plate_costs_' . ($date ?: date('Y-m-d'));
            if ($format === 'excel') {
                \App\Core\ExportHelper::excel($filename . '.xlsx', [
                    ['title' => 'قوالب التكلفة', 'report_title' => 'تكاليف الأطباق (قوالب)', 'period' => $date,
                     'headers' => ['الصنف','الباركود','الوحدة الناتجة','تكلفة/وحدة','سعر البيع','Food Cost%','مجمل الربح','GP%'],
                     'rows' => $sheetsRows, 'totals' => []],
                    ['title' => 'أطباق المنيو', 'report_title' => 'تكاليف الأطباق (منيو)', 'period' => $date,
                     'headers' => ['طبق المنيو','تكلفة/حصة','سعر البيع','Food Cost%','مجمل الربح','GP%'],
                     'rows' => $menuRows, 'totals' => []],
                ]);
            } else {
                $pdfRows = array_merge(
                    [["=== قوالب التكلفة ===",'','','']], $sheetsRows,
                    [["=== أطباق المنيو ===",'','','']], $menuRows
                );
                \App\Core\ExportHelper::pdf($filename . '.pdf', [
                    'title' => 'تكاليف الأطباق (حسب التاريخ)', 'period' => $date,
                    'headers' => ['البند','','',''], 'rows' => $pdfRows, 'totals' => [], 'orientation' => 'A4-L',
                ]);
            }
        }

        $this->view('inventory/plate_costs', [
            'title' => 'تكاليف الأطباق',
            'phases' => $phases,
            'date' => $date,
            'live' => $live,
            'menuLive' => $menuLive,
        ]);
    }

    private function resolveItem($barcode, $itemId): ?array
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
