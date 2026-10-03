<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Auth, Helpers, Response, StockService, ApprovalService, AccountingService, MaterialRequestService, PurchasingService, PurchaseInvoiceService, NotificationService, WorkflowEngine, WorkflowTemplateService};

class PurchasingController extends Controller
{
    public function requests(): void
    {
        $requests = Database::query(
            "SELECT pr.*, u.full_name AS requester,
                    (SELECT COUNT(*) FROM purchase_request_items i WHERE i.request_id = pr.id) AS items_count
             FROM purchase_requests pr
             LEFT JOIN users u ON u.id = pr.requested_by
             ORDER BY pr.id DESC LIMIT 100"
        )->fetchAll();
        $items = Database::query("SELECT id, name FROM items WHERE is_active=1 ORDER BY name")->fetchAll();

        $this->view('purchasing/requests', [
            'title' => 'طلبات الشراء', 'requests' => $requests, 'items' => $items,
        ]);
    }

    /** أصناف طلبات الشراء - تُدار من واجهة الإدارة */
    public function purchaseRequestItems(): void
    {
        $items = Database::query(
            "SELECT i.*, u.name AS unit_name, c.name AS category_name
             FROM items i
             LEFT JOIN units u ON u.id = i.unit_id
             LEFT JOIN item_categories c ON c.id = i.category_id
             WHERE i.is_active = 1
             ORDER BY i.available_for_purchase_request DESC, i.name"
        )->fetchAll();
        $units = Database::query("SELECT id, name FROM units ORDER BY name")->fetchAll();
        $categories = Database::query("SELECT id, name FROM item_categories ORDER BY name")->fetchAll();
        $this->view('purchasing/purchase_items', [
            'title' => 'أصناف طلبات الشراء',
            'items' => $items,
            'units' => $units,
            'categories' => $categories,
        ]);
    }

    public function storePurchaseRequestItem(): void
    {
        $name = trim((string)$this->input('name'));
        $unitId = (int)$this->input('unit_id');
        $categoryId = (int)$this->input('category_id');
        if ($name === '') {
            $this->flash('danger', 'اسم الصنف مطلوب');
        } elseif ($categoryId <= 0) {
            $this->flash('danger', 'يجب اختيار التصنيف');
        } else {
            Database::query(
                "INSERT INTO items (barcode, name, category_id, unit_id, min_qty, cost_price, track_expiry, is_active, available_for_purchase_request)
                 VALUES (?,?,?,?,?,?,?,1,1)",
                [null, $name, $categoryId, $unitId > 0 ? $unitId : null, 0, 0, 0]
            );
            $this->flash('success', 'تم إضافة الصنف وسيظهر في تطبيق الموبايل');
        }
        Response::redirect('/purchasing/purchase-request-items');
    }

    public function deletePurchaseRequestItem(string $id): void
    {
        Database::query(
            "UPDATE items SET is_active = 0, available_for_purchase_request = 0 WHERE id = ?",
            [(int)$id]
        );
        $this->flash('success', 'تم حذف الصنف');
        Response::redirect('/purchasing/purchase-request-items');
    }

    public function togglePurchaseRequestItem(string $id): void
    {
        Database::query(
            "UPDATE items SET available_for_purchase_request = NOT available_for_purchase_request WHERE id = ?",
            [(int)$id]
        );
        $this->flash('success', 'تم تحديث حالة الصنف');
        Response::redirect('/purchasing/purchase-request-items');
    }

    public function storeRequest(): void
    {
        $no = Helpers::docNumber('PRQ', 'purchase_requests', 'request_no');
        Database::query(
            "INSERT INTO purchase_requests (request_no, branch_id, cost_center_id, department, notes, requested_by) VALUES (?,?,?,?,?,?)",
            [$no, (int)$this->input('branch_id', 0) ?: null, (int)$this->input('cost_center_id', 0) ?: null,
             (string)$this->input('department'), (string)$this->input('notes'), Auth::id()]
        );
        $requestId = (int)Database::lastInsertId();

        Database::query(
            "INSERT INTO purchase_request_items (request_id, item_id, new_item_name, qty, notes) VALUES (?,?,?,?,?)",
            [
                $requestId,
                $this->input('item_id') ?: null,
                $this->input('new_item_name') ?: null,
                (float)$this->input('qty'),
                (string)$this->input('item_notes'),
            ]
        );
        // مسار الموافقات متعدد المستويات (محرك Workflow مع الهيكل الإداري)
        $template = WorkflowTemplateService::getTemplateByCode('purchase_request');
        if ($template) {
            $total = (float)$this->input('qty') * (float)$this->input('unit_price', 0);
            $wfId = WorkflowEngine::createRequest((int)$template['id'], (int)Auth::id(), [
                'title' => 'طلب شراء ' . $no,
                'estimated_cost' => $total,
                'entity_type' => 'purchase_request',
                'entity_id' => $requestId,
                'branch_id' => (int)$this->input('branch_id', 0) ?: null,
                'cost_center_id' => (int)$this->input('cost_center_id', 0) ?: null,
            ], true);
            Database::query("UPDATE purchase_requests SET workflow_request_id = ? WHERE id = ?", [$wfId, $requestId]);
            Database::query(
                "INSERT INTO hr_workflow_links (entity_type, entity_id, request_id) VALUES (?,?,?)",
                ['purchase_request', $requestId, $wfId]
            );
            WorkflowEngine::submitRequest($wfId, (int)Auth::id());
            $this->flash('success', "تم إنشاء طلب الشراء $no وأرسل لمسار الموافقات التسلسلي");
        } else {
            $approvalId = ApprovalService::request('purchase_request', $requestId);
            if ($approvalId) {
                $this->flash('success', "تم إنشاء طلب الشراء $no وأرسل للموافقة");
            } else {
                $this->flash('warning', "تم إنشاء طلب الشراء $no لكن لا يوجد مسار موافقات نشط لهذا النوع");
            }
        }
        Response::redirect('/purchasing/requests');
    }

    public function orders(): void
    {
        $orders = Database::query(
            "SELECT po.*, s.name AS supplier_name FROM purchase_orders po
             JOIN suppliers s ON s.id = po.supplier_id
             ORDER BY po.id DESC LIMIT 100"
        )->fetchAll();
        $suppliers = Database::query("SELECT id, name FROM suppliers WHERE is_active=1 ORDER BY name")->fetchAll();
        $items = Database::query("SELECT id, name, cost_price FROM items WHERE is_active=1 ORDER BY name")->fetchAll();
        $approvedRequests = Database::query(
            "SELECT id, request_no FROM purchase_requests WHERE status='approved' ORDER BY id DESC"
        )->fetchAll();

        $this->view('purchasing/orders', [
            'title' => 'أوامر الشراء', 'orders' => $orders, 'suppliers' => $suppliers,
            'items' => $items, 'approvedRequests' => $approvedRequests,
        ]);
    }

    public function storeOrder(): void
    {
        $no  = Helpers::docNumber('PO', 'purchase_orders', 'po_no');
        $qty = (float)$this->input('qty');
        $price = (float)$this->input('unit_price');

        Database::beginTransaction();
        try {
            Database::query(
                "INSERT INTO purchase_orders (po_no, branch_id, cost_center_id, supplier_id, request_id, status, total, created_by)
                 VALUES (?,?,?,?,?,'sent',?,?)",
                [$no, (int)$this->input('branch_id', 0) ?: null, (int)$this->input('cost_center_id', 0) ?: null,
                 (int)$this->input('supplier_id'), $this->input('request_id') ?: null,
                 $qty * $price, Auth::id()]
            );
            $poId = (int)Database::lastInsertId();
            Database::query(
                "INSERT INTO purchase_order_items (po_id, item_id, qty, unit_price) VALUES (?,?,?,?)",
                [$poId, (int)$this->input('item_id'), $qty, $price]
            );
            if ($this->input('request_id')) {
                Database::query("UPDATE purchase_requests SET status='converted' WHERE id = ?",
                    [(int)$this->input('request_id')]);
            }
            Database::commit();
            $this->flash('success', "تم إنشاء أمر الشراء $no");
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->flash('danger', 'خطأ: ' . $e->getMessage());
        }
        Response::redirect('/purchasing/orders');
    }

    public function receipts(): void
    {
        $receipts = Database::query(
            "SELECT gr.*, po.po_no, w.name AS warehouse_name, i.name AS item_name,
                    gri.item_id, gri.expected_qty, gri.qty AS actual_qty, gri.accepted_qty, gri.damaged_qty, gri.variance_qty, gri.batch_no, gri.production_date, gri.expiry_date
             FROM goods_receipts gr
             JOIN purchase_orders po ON po.id = gr.po_id
             JOIN warehouses w ON w.id = gr.warehouse_id
             LEFT JOIN goods_receipt_items gri ON gri.receipt_id = gr.id
             LEFT JOIN items i ON i.id = gri.item_id
             ORDER BY gr.id DESC LIMIT 100"
        )->fetchAll();
        $openPOs = Database::query(
            "SELECT po.id, po.po_no, s.name AS supplier_name FROM purchase_orders po
             JOIN suppliers s ON s.id = po.supplier_id
             WHERE po.status IN ('sent','partially_received') ORDER BY po.id DESC"
        )->fetchAll();
        $warehouses = Database::query("SELECT * FROM warehouses WHERE is_active=1")->fetchAll();
        $items = Database::query("SELECT id, name FROM items WHERE is_active=1 ORDER BY name")->fetchAll();

        // آخر سعر شراء تاريخي لكل مادة (إن وُجد purchase_prices)
        $lastPrices = [];
        try {
            $pp = Database::query(
                "SELECT pp.item_id, pp.purchase_price, pp.purchase_date, pp.id
                 FROM purchase_prices pp
                 JOIN (
                   SELECT item_id, MAX(purchase_date) AS max_date
                   FROM purchase_prices GROUP BY item_id
                 ) t ON t.item_id = pp.item_id AND t.max_date = pp.purchase_date
                 ORDER BY pp.item_id, pp.id DESC"
            )->fetchAll();
            foreach ($pp as $row) {
                $iid = (int)$row['item_id'];
                if (!isset($lastPrices[$iid])) { $lastPrices[$iid] = (float)$row['purchase_price']; }
            }
        } catch (\Throwable $e) {
            // تجاهل في حال عدم وجود الجدول بعد
        }

        $this->view('purchasing/receipts', [
            'title' => 'الاستلام والفحص', 'receipts' => $receipts,
            'openPOs' => $openPOs, 'warehouses' => $warehouses, 'items' => $items,
            'lastPrices' => $lastPrices,
        ]);
    }

    public function storeReceipt(): void
    {
        $no = Helpers::docNumber('GRN', 'goods_receipts', 'receipt_no');
        $poId = (int)$this->input('po_id');
        $itemId = (int)$this->input('item_id');
        $actualQty = (float)$this->input('qty');
        $price = (float)$this->input('unit_price');
        $warehouseId = (int)$this->input('warehouse_id');
        $damagedQty = (float)$this->input('damaged_qty', 0);
        $acceptedQty = max(0, $actualQty - $damagedQty);
        $batchNo = (string)$this->input('batch_no');
        $barcode = (string)$this->input('barcode');
        $qrCode = (string)$this->input('qr_code');

        Database::beginTransaction();
        try {
            $poItem = Database::query(
                "SELECT qty, received_qty FROM purchase_order_items WHERE po_id = ? AND item_id = ?",
                [$poId, $itemId]
            )->fetch();
            $expectedQty = $poItem ? (float)$poItem['qty'] : $actualQty;
            $varianceQty = $expectedQty - $actualQty;
            $costCenterId = (int)$this->input('cost_center_id', 0) ?: null;
            $po = Database::query("SELECT cost_center_id FROM purchase_orders WHERE id = ?", [$poId])->fetch();
            if (!$costCenterId && $po) {
                $costCenterId = (int)($po['cost_center_id'] ?? 0) ?: null;
            }

            Database::query(
                "INSERT INTO goods_receipts (receipt_no, po_id, branch_id, cost_center_id, warehouse_id, inspection_result, notes, received_by)
                 VALUES (?,?,?,?,?,?,?,?)",
                [$no, $poId, (int)$this->input('branch_id', 0) ?: null, $costCenterId, $warehouseId,
                 (string)$this->input('inspection_result', 'accepted'), (string)$this->input('notes'), Auth::id()]
            );
            $grId = (int)Database::lastInsertId();
            Database::query(
                "INSERT INTO goods_receipt_items (receipt_id, item_id, qty, unit_price, expiry_date, production_date, barcode, qr_code, batch_no, expected_qty, variance_qty, damaged_qty, accepted_qty, damage_reason)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [$grId, $itemId, $actualQty, $price, $this->input('expiry_date') ?: null,
                 $this->input('production_date') ?: null, $barcode, $qrCode, $batchNo,
                 $expectedQty, $varianceQty, $damagedQty, $acceptedQty, (string)$this->input('damage_reason')]
            );
            $griId = (int)Database::lastInsertId();
            // إدخال للمخزن بالكمية المقبولة مع دفعة/باركود/QR
            StockService::move($warehouseId, $itemId, 'in', $acceptedQty, $price, $no, 'استلام مشتريات',
                $this->input('expiry_date') ?: null, null, null, $batchNo,
                $this->input('production_date') ?: null, $barcode, $qrCode);
            // تحديث متوسط التكلفة
            StockService::updateAverageCost($itemId, $acceptedQty, $price);
            Database::query("UPDATE purchase_order_items SET received_qty = received_qty + ? WHERE po_id = ? AND item_id = ?",
                [$acceptedQty, $poId, $itemId]);
            Database::query("UPDATE purchase_orders SET status='received' WHERE id = ?", [$poId]);

            // إنشاء/ربط أصل ثابت عند استلام مواد من فئة الأصول
            $assetId = self::ensureFixedAssetFromReceipt($itemId, $acceptedQty, $price, $costCenterId, $warehouseId, $no, $grId);
            if ($assetId) {
                Database::query("UPDATE goods_receipt_items SET fixed_asset_id = ? WHERE id = ?", [$assetId, $griId]);
            }

            // تسجيل سعر الشراء التاريخي في purchase_prices (آمن وغير معطِّل)
            try {
                $po = Database::query("SELECT supplier_id FROM purchase_orders WHERE id = ?", [$poId])->fetch();
                $supplierId = $po ? (int)$po['supplier_id'] : null;
                $pDate = date('Y-m-d'); // تاريخ الاستلام الحالي
                Database::query(
                    "INSERT INTO purchase_prices (item_id, supplier_id, purchase_price, currency, purchase_date, is_last_price, notes)
                     VALUES (?,?,?,?,?,1,?)
                     ON DUPLICATE KEY UPDATE purchase_price = VALUES(purchase_price), is_last_price = 1, notes = VALUES(notes)",
                    [$itemId, $supplierId, $price, 'IQD', $pDate, 'من استلام مشتريات ' . $no]
                );
            } catch (\Throwable $e) {
                // تجاهُل الخطأ لعدم تعطيل الاستلام في حال عدم وجود الجدول بعد
            }

            AccountingService::postPurchaseReceipt($grId);
            // إنشاء فاتورة شراء تلقائياً من الاستلام وإرسالها للحسابات
            PurchaseInvoiceService::createFromReceipt($grId, Auth::id());
            // تحديث طلب المواد المرتبط (إن وجد) عند استلام مادة جديدة
            MaterialRequestService::updateFulfillmentFromReceipt($poId, $itemId, $acceptedQty, $warehouseId);
            // إشعار القسم الطالب
            self::notifyRequestingDepartment($poId, $itemId, $acceptedQty, $no);
            Database::commit();
            $this->flash('success', "تم الاستلام $no وإدخال المواد للمخزن — بانتظار الدفع في الحسابات");
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->flash('danger', 'خطأ: ' . $e->getMessage());
        }
        Response::redirect('/purchasing/receipts');
    }

    private static function notifyRequestingDepartment(int $poId, int $itemId, float $qty, string $receiptNo): void
    {
        $po = Database::query("SELECT request_id, rfq_id FROM purchase_orders WHERE id = ?", [$poId])->fetch();
        if (!$po || !$po['request_id']) return;
        $pr = Database::query("SELECT material_request_id FROM purchase_requests WHERE id = ?", [(int)$po['request_id']])->fetch();
        if (!$pr || !$pr['material_request_id']) return;
        $mr = Database::query(
            "SELECT mr.id, mr.requested_by, u.id AS user_id
             FROM material_requests mr
             LEFT JOIN users u ON u.id = mr.requested_by
             WHERE mr.id = ?",
            [(int)$pr['material_request_id']]
        )->fetch();
        if (!$mr) return;
        $itemName = Database::query("SELECT name FROM items WHERE id = ?", [$itemId])->fetch()['name'] ?? 'مادة';
        NotificationService::receiptDone((int)$mr['id'], (int)$mr['requested_by'], $receiptNo, $itemName);
    }

    private static function ensureFixedAssetFromReceipt(int $itemId, float $qty, float $price, ?int $costCenterId, int $warehouseId, string $receiptNo, int $receiptId): ?int
    {
        $item = Database::query(
            "SELECT i.*, c.type AS category_type, c.name AS category_name FROM items i
             JOIN item_categories c ON c.id = i.category_id WHERE i.id = ?",
            [$itemId]
        )->fetch();
        if (!$item || !in_array($item['category_type'], ['asset','tool'], true)) return null;
        $assetNo = Helpers::docNumber('AST', 'fixed_assets', 'asset_no');
        $category = Database::query("SELECT id FROM asset_categories WHERE name = ? LIMIT 1", [$item['category_name']])->fetch();
        $categoryId = $category ? (int)$category['id'] : null;
        if (!$categoryId) {
            Database::query("INSERT INTO asset_categories (name, default_life_years) VALUES (?,5)", [$item['category_name']]);
            $categoryId = (int)Database::lastInsertId();
        }
        $branch = Database::query("SELECT branch_id FROM warehouses WHERE id = ?", [$warehouseId])->fetch();
        $branchId = (int)($branch['branch_id'] ?? 0) ?: null;
        Database::query(
            "INSERT INTO fixed_assets (asset_no, name, category_id, purchase_date, purchase_cost, life_years, status, cost_center_id, branch_id, notes)
             VALUES (?,?,?,?,?,5,'active',?,?,?)",
            [$assetNo, $item['name'], $categoryId, date('Y-m-d'), $qty * $price, $costCenterId, $branchId, 'مستلم من ' . $receiptNo]
        );
        return (int)Database::lastInsertId();
    }

    public function rfqs(): void
    {
        $rfqs = Database::query(
            "SELECT rfq.*, pr.request_no AS pr_no, pr.status AS pr_status,
                    (SELECT COUNT(*) FROM rfq_items i WHERE i.rfq_id = rfq.id) AS items_count
             FROM request_for_quotations rfq
             JOIN purchase_requests pr ON pr.id = rfq.purchase_request_id
             ORDER BY rfq.id DESC LIMIT 100"
        )->fetchAll();
        $this->view('purchasing/rfqs', ['title' => 'طلبات عروض الأسعار', 'rfqs' => $rfqs]);
    }

    public function showRfq(string $id): void
    {
        $rfq = Database::query(
            "SELECT rfq.*, pr.request_no AS pr_no FROM request_for_quotations rfq
             JOIN purchase_requests pr ON pr.id = rfq.purchase_request_id
             WHERE rfq.id = ?",
            [(int)$id]
        )->fetch();
        if (!$rfq) Response::abort(404);
        $items = Database::query(
            "SELECT ri.*, i.name AS item_name, u.name AS unit_name
             FROM rfq_items ri
             LEFT JOIN items i ON i.id = ri.item_id
             LEFT JOIN units u ON u.id = ri.unit_id
             WHERE ri.rfq_id = ?",
            [(int)$id]
        )->fetchAll();
        $quotes = PurchasingService::compareQuotes((int)$id);
        $suppliers = Database::query("SELECT id, name FROM suppliers WHERE is_active=1 ORDER BY name")->fetchAll();
        $this->view('purchasing/rfq_show', [
            'title' => 'عرض RFQ ' . $rfq['rfq_no'],
            'rfq' => $rfq, 'items' => $items, 'quotes' => $quotes, 'suppliers' => $suppliers,
        ]);
    }

    public function storeQuote(string $id): void
    {
        try {
            $supplierId = (int)$this->input('supplier_id');
            $lines = [];
            foreach (($this->input('lines') ?? []) as $line) {
                $lines[] = [
                    'item_id' => (int)($line['item_id'] ?? 0),
                    'qty' => (float)($line['qty'] ?? 0),
                    'unit_id' => (int)($line['unit_id'] ?? 0),
                    'unit_price' => (float)($line['unit_price'] ?? 0),
                ];
            }
            PurchasingService::addSupplierQuote((int)$id, $supplierId, $lines, [
                'valid_until' => $this->input('valid_until') ?: null,
                'notes' => (string)$this->input('notes'),
            ]);
            $this->flash('success', 'تم إضافة عرض السعر');
        } catch (\Throwable $e) {
            $this->flash('danger', 'خطأ: ' . $e->getMessage());
        }
        Response::redirect('/purchasing/rfqs/' . $id);
    }

    public function selectQuote(string $quoteId): void
    {
        try {
            $poId = PurchasingService::selectQuoteAndCreatePo((int)$quoteId, (int)Auth::id());
            $this->flash('success', 'تم اختيار العرض وإنشاء أمر الشراء');
            Response::redirect('/purchasing/orders/' . $poId);
        } catch (\Throwable $e) {
            $this->flash('danger', 'خطأ: ' . $e->getMessage());
            Response::redirect('/purchasing/rfqs');
        }
    }

    public function createPoFromPr(string $prId): void
    {
        try {
            $supplierId = (int)$this->input('supplier_id');
            $poId = PurchasingService::createPoFromPurchaseRequest((int)$prId, $supplierId, (int)Auth::id());
            $this->flash('success', 'تم إنشاء أمر الشراء');
            Response::redirect('/purchasing/orders/' . $poId);
        } catch (\Throwable $e) {
            $this->flash('danger', 'خطأ: ' . $e->getMessage());
            Response::redirect('/purchasing/requests');
        }
    }

    public function showOrder(string $id): void
    {
        $po = Database::query(
            "SELECT po.*, s.name AS supplier_name, pr.request_no AS pr_no, rfq.rfq_no
             FROM purchase_orders po
             JOIN suppliers s ON s.id = po.supplier_id
             LEFT JOIN purchase_requests pr ON pr.id = po.request_id
             LEFT JOIN request_for_quotations rfq ON rfq.id = po.rfq_id
             WHERE po.id = ?",
            [(int)$id]
        )->fetch();
        if (!$po) Response::abort(404);
        $items = Database::query(
            "SELECT poi.*, i.name AS item_name FROM purchase_order_items poi
             JOIN items i ON i.id = poi.item_id WHERE poi.po_id = ?",
            [(int)$id]
        )->fetchAll();
        $tracking = Database::query(
            "SELECT pot.*, u.full_name AS created_by_name FROM purchase_order_tracking pot
             LEFT JOIN users u ON u.id = pot.created_by WHERE pot.po_id = ? ORDER BY pot.id DESC",
            [(int)$id]
        )->fetchAll();
        $shipments = Database::query(
            "SELECT pos.*, u.full_name AS created_by_name FROM purchase_order_shipments pos
             LEFT JOIN users u ON u.id = pos.created_by WHERE pos.po_id = ? ORDER BY pos.id DESC",
            [(int)$id]
        )->fetchAll();
        $warehouses = Database::query("SELECT id, name FROM warehouses WHERE is_active=1 ORDER BY name")->fetchAll();
        $this->view('purchasing/order_show', [
            'title' => 'أمر شراء ' . $po['po_no'],
            'po' => $po, 'items' => $items, 'tracking' => $tracking,
            'shipments' => $shipments, 'warehouses' => $warehouses,
        ]);
    }

    public function approvePo(string $id): void
    {
        try {
            PurchasingService::approvePoAndSend((int)$id, (int)Auth::id());
            $this->flash('success', 'تم اعتماد أمر الشراء وإرساله');
        } catch (\Throwable $e) {
            $this->flash('danger', 'خطأ: ' . $e->getMessage());
        }
        Response::redirect('/purchasing/orders/' . $id);
    }

    public function storePoTracking(string $id): void
    {
        try {
            PurchasingService::addPoTracking(
                (int)$id, (string)$this->input('status'), (string)$this->input('location'),
                (string)$this->input('notes'), (int)Auth::id()
            );
            $this->flash('success', 'تم إضافة تحديث التتبع');
        } catch (\Throwable $e) {
            $this->flash('danger', 'خطأ: ' . $e->getMessage());
        }
        Response::redirect('/purchasing/orders/' . $id);
    }

    public function storePoShipment(string $id): void
    {
        try {
            PurchasingService::addPoShipment((int)$id, [
                'tracking_no' => (string)$this->input('tracking_no'),
                'carrier' => (string)$this->input('carrier'),
                'shipping_date' => $this->input('shipping_date') ?: null,
                'expected_arrival' => $this->input('expected_arrival') ?: null,
                'status' => (string)$this->input('status'),
                'notes' => (string)$this->input('notes'),
            ], (int)Auth::id());
            $this->flash('success', 'تم إضافة الشحنة');
        } catch (\Throwable $e) {
            $this->flash('danger', 'خطأ: ' . $e->getMessage());
        }
        Response::redirect('/purchasing/orders/' . $id);
    }

    public function receiveShipment(string $shipmentId): void
    {
        try {
            $warehouseId = (int)$this->input('warehouse_id');
            PurchasingService::receiveShipment((int)$shipmentId, $warehouseId, (int)Auth::id());
            $this->flash('success', 'تم استلام الشحنة');
        } catch (\Throwable $e) {
            $this->flash('danger', 'خطأ: ' . $e->getMessage());
        }
        Response::redirect('/purchasing/receipts');
    }

    public function invoices(): void
    {
        $invoices = Database::query(
            "SELECT pi.*, s.name AS supplier_name, po.po_no, gr.receipt_no FROM purchase_invoices pi
             JOIN suppliers s ON s.id = pi.supplier_id
             JOIN purchase_orders po ON po.id = pi.po_id
             JOIN goods_receipts gr ON gr.id = pi.receipt_id
             ORDER BY pi.id DESC LIMIT 100"
        )->fetchAll();
        $this->render('purchasing/invoices', ['invoices' => $invoices]);
    }

    public function showInvoice(string $id): void
    {
        $invoice = Database::query(
            "SELECT pi.*, s.name AS supplier_name, po.po_no, gr.receipt_no FROM purchase_invoices pi
             JOIN suppliers s ON s.id = pi.supplier_id
             JOIN purchase_orders po ON po.id = pi.po_id
             JOIN goods_receipts gr ON gr.id = pi.receipt_id
             WHERE pi.id = ?",
            [(int)$id]
        )->fetch();
        if (!$invoice) { $this->flash('danger', 'الفاتورة غير موجودة'); Response::redirect('/purchasing/invoices'); }
        $items = Database::query(
            "SELECT pii.*, i.name AS item_name FROM purchase_invoice_items pii
             JOIN items i ON i.id = pii.item_id WHERE pii.invoice_id = ?",
            [(int)$id]
        )->fetchAll();
        $documents = Database::query(
            "SELECT * FROM document_archive WHERE entity_type = 'purchase_invoice' AND entity_id = ? ORDER BY id DESC",
            [(int)$id]
        )->fetchAll();
        $this->render('purchasing/invoice_show', ['invoice' => $invoice, 'items' => $items, 'documents' => $documents]);
    }

    public function recordInvoicePayment(string $id): void
    {
        try {
            PurchaseInvoiceService::recordPayment(
                (int)$id,
                (float)$this->input('amount'),
                (string)$this->input('method', 'cash'),
                $this->input('reference') ?: null,
                $this->input('notes') ?: null,
                (int)Auth::id()
            );
            $this->flash('success', 'تم تسجيل الدفعة');
        } catch (\Throwable $e) {
            $this->flash('danger', 'خطأ: ' . $e->getMessage());
        }
        Response::redirect('/purchasing/invoices/' . $id);
    }

    public function archiveInvoiceDocument(string $id): void
    {
        try {
            $filePath = (string)$this->input('file_path');
            if (!$filePath) { throw new \RuntimeException('يجب إدخال مسار الملف'); }
            PurchaseInvoiceService::archiveDocument('purchase_invoice', (int)$id, (string)$this->input('document_type', 'invoice'), $filePath, (string)$this->input('notes'), (int)Auth::id());
            $this->flash('success', 'تمت أرشفة المستند');
        } catch (\Throwable $e) {
            $this->flash('danger', 'خطأ: ' . $e->getMessage());
        }
        Response::redirect('/purchasing/invoices/' . $id);
    }
}
