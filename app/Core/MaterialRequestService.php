<?php
namespace App\Core;

class MaterialRequestService
{
    public static function list(array $filters = [], int $limit = 200): array
    {
        $where = "mr.deleted_at IS NULL";
        $params = [];
        if (!empty($filters['status'])) { $where .= " AND mr.status = ?"; $params[] = $filters['status']; }
        if (!empty($filters['branch_id'])) { $where .= " AND mr.branch_id = ?"; $params[] = $filters['branch_id']; }
        if (!empty($filters['requested_by'])) { $where .= " AND mr.requested_by = ?"; $params[] = $filters['requested_by']; }
        if (!empty($filters['priority'])) { $where .= " AND mr.priority = ?"; $params[] = $filters['priority']; }
        if (!empty($filters['cost_center_id'])) { $where .= " AND mr.cost_center_id = ?"; $params[] = $filters['cost_center_id']; }
        if (!empty($filters['production_order_id'])) { $where .= " AND mr.production_order_id = ?"; $params[] = $filters['production_order_id']; }
        if (!empty($filters['kitchen_section_id'])) { $where .= " AND mr.kitchen_section_id = ?"; $params[] = $filters['kitchen_section_id']; }
        if (!empty($filters['q'])) {
            $where .= " AND (mr.request_no LIKE ? OR mr.reason LIKE ?)";
            $like = '%' . $filters['q'] . '%';
            $params[] = $like; $params[] = $like;
        }
        return Database::query(
            "SELECT mr.*, b.name AS branch_name, d.name AS department_name, u.full_name AS requester_name,
                    cc.name AS cost_center_name, po.production_no AS production_order_no, ks.name AS kitchen_section_name,
                    w.name AS warehouse_name
             FROM material_requests mr
             LEFT JOIN branches b ON b.id = mr.branch_id
             LEFT JOIN departments d ON d.id = mr.department_id
             LEFT JOIN users u ON u.id = mr.requested_by
             LEFT JOIN cost_centers cc ON cc.id = mr.cost_center_id
             LEFT JOIN production_orders po ON po.id = mr.production_order_id
             LEFT JOIN kitchen_sections ks ON ks.id = mr.kitchen_section_id
             LEFT JOIN warehouses w ON w.id = mr.warehouse_id
             WHERE $where ORDER BY mr.id DESC LIMIT $limit",
            $params
        )->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $row = Database::query(
            "SELECT mr.*, b.name AS branch_name, d.name AS department_name, u.full_name AS requester_name,
                    cc.name AS cost_center_name, po.production_no AS production_order_no, ks.name AS kitchen_section_name,
                    w.name AS warehouse_name
             FROM material_requests mr
             LEFT JOIN branches b ON b.id = mr.branch_id
             LEFT JOIN departments d ON d.id = mr.department_id
             LEFT JOIN users u ON u.id = mr.requested_by
             LEFT JOIN cost_centers cc ON cc.id = mr.cost_center_id
             LEFT JOIN production_orders po ON po.id = mr.production_order_id
             LEFT JOIN kitchen_sections ks ON ks.id = mr.kitchen_section_id
             LEFT JOIN warehouses w ON w.id = mr.warehouse_id
             WHERE mr.id = ? AND mr.deleted_at IS NULL",
            [$id]
        )->fetch();
        return $row ?: null;
    }

    public static function items(int $requestId): array
    {
        return Database::query(
            "SELECT mri.*,
                    COALESCE(i.name, mri.new_item_name) AS item_name,
                    COALESCE(i.barcode, '') AS barcode,
                    COALESCE(u.name, nu.name) AS unit_name,
                    w.name AS warehouse_name,
                    ic.name AS category_name,
                    ic.type AS category_type
             FROM material_request_items mri
             LEFT JOIN items i ON i.id = mri.item_id
             LEFT JOIN units u ON u.id = mri.unit_id
             LEFT JOIN units nu ON nu.id = mri.new_item_unit_id
             LEFT JOIN item_categories ic ON ic.id = COALESCE(i.category_id, mri.new_item_category_id)
             LEFT JOIN warehouses w ON w.id = mri.warehouse_id
             WHERE mri.material_request_id = ?",
            [$requestId]
        )->fetchAll();
    }

    public static function fulfillment(int $requestId): array
    {
        return Database::query(
            "SELECT mrf.*, fw.name AS from_warehouse, tw.name AS to_warehouse
             FROM material_request_fulfillment mrf
             LEFT JOIN warehouses fw ON fw.id = mrf.from_warehouse_id
             LEFT JOIN warehouses tw ON tw.id = mrf.to_warehouse_id
             WHERE mrf.material_request_id = ?",
            [$requestId]
        )->fetchAll();
    }

    public static function create(int $userId, array $data): int
    {
        $no = Helpers::docNumber('MR', 'material_requests', 'request_no');
        $branchId = (int)($data['branch_id'] ?? 0) ?: null;
        $departmentId = (int)($data['department_id'] ?? 0) ?: null;
        $costCenterId = (int)($data['cost_center_id'] ?? 0) ?: null;
        $productionOrderId = (int)($data['production_order_id'] ?? 0) ?: null;
        $kitchenSectionId = (int)($data['kitchen_section_id'] ?? 0) ?: null;
        $warehouseId = (int)($data['warehouse_id'] ?? 0) ?: null;
        $total = 0.0;
        Database::query(
            "INSERT INTO material_requests (request_no, branch_id, department_id, cost_center_id, production_order_id, kitchen_section_id, warehouse_id, requested_by, request_date, priority, reason, notes, total_estimated_cost, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [$no, $branchId, $departmentId, $costCenterId, $productionOrderId, $kitchenSectionId, $warehouseId, $userId, $data['request_date'] ?: date('Y-m-d'), $data['priority'] ?: 'normal', $data['reason'] ?? '', $data['notes'] ?? '', $total, 'draft']
        );
        $requestId = (int) Database::lastInsertId();
        self::syncItems($requestId, $data['items'] ?? []);
        self::audit($requestId, 'created', [], ['request_no' => $no]);
        Helpers::queueSync('material_requests', $requestId, 'insert', ['request_no' => $no, 'status' => 'draft']);
        return $requestId;
    }

    public static function update(int $requestId, array $data): void
    {
        $mr = self::find($requestId);
        if (!$mr || $mr['status'] !== 'draft') throw new \RuntimeException('لا يمكن تعديل الطلب في حالته الحالية');
        Database::query(
            "UPDATE material_requests SET branch_id = ?, department_id = ?, cost_center_id = ?, production_order_id = ?, kitchen_section_id = ?, warehouse_id = ?, request_date = ?, priority = ?, reason = ?, notes = ?, updated_at = NOW() WHERE id = ?",
            [(int)($data['branch_id'] ?? 0) ?: null, (int)($data['department_id'] ?? 0) ?: null, (int)($data['cost_center_id'] ?? 0) ?: null, (int)($data['production_order_id'] ?? 0) ?: null, (int)($data['kitchen_section_id'] ?? 0) ?: null, (int)($data['warehouse_id'] ?? 0) ?: null, $data['request_date'] ?: date('Y-m-d'), $data['priority'] ?: 'normal', $data['reason'] ?? '', $data['notes'] ?? '', $requestId]
        );
        self::syncItems($requestId, $data['items'] ?? []);
        self::audit($requestId, 'updated', [], ['updated_fields' => array_keys($data)]);
        Helpers::queueSync('material_requests', $requestId, 'update', ['status' => 'draft']);
    }


    /**
     * إنشاء طلب مواد تلقائياً من أمر تصنيع (Production Order) بناءً على وصفته
     */
    public static function createFromProductionOrder(int $productionOrderId, int $userId, ?int $targetWarehouseId = null): ?int
    {
        $po = Database::query(
            "SELECT p.*, i.name AS item_name FROM production_orders p
             JOIN items i ON i.id = p.item_id
             WHERE p.id = ?",
            [$productionOrderId]
        )->fetch();
        if (!$po) return null;

        $recipeId = (int)($po['recipe_id'] ?? 0);
        if (!$recipeId) {
            $recipe = Database::query("SELECT id FROM production_recipes WHERE output_item_id = ? LIMIT 1", [(int)$po['item_id']])->fetch();
            $recipeId = (int)($recipe['id'] ?? 0);
        }
        if (!$recipeId) return null;

        $needed = (float)$po['planned_qty'] - (float)$po['actual_qty'];
        if ($needed <= 0) return null;

        if (!$targetWarehouseId) {
            $wh = Database::query("SELECT id FROM warehouses WHERE is_active = 1 ORDER BY id LIMIT 1")->fetch();
            $targetWarehouseId = (int)($wh['id'] ?? 0);
        }

        $items = [];
        $ings = Database::query(
            "SELECT pri.*, i.unit_id FROM production_recipe_ingredients pri
             JOIN items i ON i.id = pri.item_id
             WHERE pri.recipe_id = ?",
            [$recipeId]
        )->fetchAll();
        foreach ($ings as $ing) {
            $items[] = [
                'item_id' => (int)$ing['item_id'],
                'unit_id' => (int)$ing['unit_id'],
                'requested_qty' => (float)$ing['qty'] * $needed,
                'warehouse_id' => $targetWarehouseId,
                'purpose' => 'استهلاك تصنيع ' . ($po['production_no'] ?? ''),
            ];
        }
        if (!$items) return null;

        $id = self::create($userId, [
            'cost_center_id' => (int)($po['cost_center_id'] ?? 0),
            'production_order_id' => $productionOrderId,
            'warehouse_id' => $targetWarehouseId,
            'priority' => 'high',
            'reason' => 'طلب مواد لأمر التصنيع ' . ($po['production_no'] ?? ''),
            'items' => $items,
        ]);
        self::audit($id, 'created_from_production', [], ['production_order_id' => $productionOrderId]);
        return $id;
    }

    /**
     * إنشاء طلب مواد من قسم مطبخ (Kitchen Section) لتجهيز مستلزمات يومية
     */
    public static function createFromKitchenSection(int $kitchenSectionId, array $lines, int $userId): ?int
    {
        if (!$lines) return null;
        $ks = Database::query("SELECT * FROM kitchen_sections WHERE id = ?", [$kitchenSectionId])->fetch();
        if (!$ks) return null;
        $items = [];
        foreach ($lines as $line) {
            if ((float)($line['qty'] ?? 0) <= 0) continue;
            $items[] = [
                'item_id' => (int)($line['item_id'] ?? 0),
                'unit_id' => (int)($line['unit_id'] ?? 0),
                'requested_qty' => (float)$line['qty'],
                'warehouse_id' => (int)($line['warehouse_id'] ?? 0),
                'purpose' => $line['purpose'] ?? 'تجهيزات قسم ' . ($ks['name'] ?? ''),
            ];
        }
        if (!$items) return null;

        $id = self::create($userId, [
            'kitchen_section_id' => $kitchenSectionId,
            'priority' => 'normal',
            'reason' => 'تجهيز مواد لقسم المطبخ: ' . ($ks['name'] ?? ''),
            'items' => $items,
        ]);
        self::audit($id, 'created_from_kitchen', [], ['kitchen_section_id' => $kitchenSectionId]);
        return $id;
    }

    public static function submit(int $requestId, int $userId): void
    {
        $mr = self::find($requestId);
        if (!$mr || $mr['status'] !== 'draft') throw new \RuntimeException('لا يمكن إرسال الطلب');
        $items = self::items($requestId);
        if (!$items) throw new \RuntimeException('الطلب لا يحتوي على بنود');
        $total = array_sum(array_map(fn($i) => (float)($i['total_price'] ?? 0), $items));
        Database::query("UPDATE material_requests SET total_estimated_cost = ?, status = 'submitted' WHERE id = ?", [$total, $requestId]);

        $template = WorkflowTemplateService::getTemplateByCode('material_request');
        if (!$template) throw new \RuntimeException('قالب workflow غير موجود');
        $wfId = WorkflowEngine::createRequest((int)$template['id'], $userId, [
            'title' => 'طلب مواد ' . $mr['request_no'],
            'estimated_cost' => $total,
            'priority' => $mr['priority'],
            'entity_type' => 'material_request',
            'entity_id' => $requestId,
        ], true);
        Database::query("UPDATE material_requests SET workflow_request_id = ? WHERE id = ?", [$wfId, $requestId]);
        Database::query("INSERT INTO hr_workflow_links (entity_type, entity_id, request_id) VALUES (?,?,?)", ['material_request', $requestId, $wfId]);
        WorkflowEngine::submitRequest($wfId, $userId);
        self::audit($requestId, 'submitted', ['status' => 'draft'], ['status' => 'submitted']);
        Helpers::queueSync('material_requests', $requestId, 'update', ['status' => 'submitted', 'workflow_request_id' => $wfId]);
    }

    public static function cancel(int $requestId, int $userId): void
    {
        $mr = self::find($requestId);
        if (!$mr || in_array($mr['status'], ['fulfilled','closed','cancelled'], true)) throw new \RuntimeException('لا يمكن إلغاء الطلب');
        Database::query("UPDATE material_requests SET status = 'cancelled' WHERE id = ?", [$requestId]);
        if ($mr['workflow_request_id']) {
            WorkflowEngine::cancelRequest((int)$mr['workflow_request_id'], $userId);
        }
        self::audit($requestId, 'cancelled', ['status' => $mr['status']], ['status' => 'cancelled']);
        Helpers::queueSync('material_requests', $requestId, 'update', ['status' => 'cancelled']);
    }

    public static function handleWorkflowOutcome(array $request, string $status, int $actorId, string $notes): void
    {
        $requestId = (int)($request['id'] ?? 0);
        $link = Database::query(
            "SELECT entity_id FROM hr_workflow_links WHERE entity_type = 'material_request' AND request_id = ?",
            [$requestId]
        )->fetch();
        $entityId = (int)($link['entity_id'] ?? 0);
        if (!$entityId) {
            $entityId = (int)($request['data']['entity_id'] ?? 0);
        }
        if (!$entityId) return;
        $mr = self::find($entityId);
        if (!$mr) return;
        $old = ['status' => $mr['status']];
        if ($status === 'approved') {
            Database::beginTransaction();
            try {
                Database::query("UPDATE material_requests SET status = 'approved' WHERE id = ?", [$entityId]);
                self::createItemsFromNewLines($entityId);
                self::createPurchaseRequestForNewItems($entityId);
                self::planSubRequestsForExistingItems($entityId);
                Database::commit();
                self::audit($entityId, 'approved', $old, ['status' => 'approved']);
                NotificationService::mrApproved($entityId, (int)$mr['requested_by'], $mr['request_no']);
            } catch (\Throwable $e) {
                Database::rollBack();
                throw $e;
            }
        } elseif ($status === 'rejected') {
            Database::query("UPDATE material_requests SET status = 'rejected' WHERE id = ?", [$entityId]);
            self::audit($entityId, 'rejected', $old, ['status' => 'rejected']);
            NotificationService::mrRejected($entityId, (int)$mr['requested_by'], $mr['request_no'], $notes);
        } elseif ($status === 'returned_for_edit') {
            Database::query("UPDATE material_requests SET status = 'returned' WHERE id = ?", [$entityId]);
            self::audit($entityId, 'returned', $old, ['status' => 'returned']);
        }
    }

    public static function fulfillmentPlan(int $requestId): array
    {
        $items = self::items($requestId);
        $mr = self::find($requestId);
        $plan = [];
        foreach ($items as $item) {
            $need = (float)($item['approved_qty'] ?? $item['requested_qty']);
            $isNew = (int)($item['is_new_item'] ?? 0) === 1;
            $itemId = (int)($item['item_id'] ?? 0);
            $warehouseId = (int)($item['warehouse_id'] ?? 0);
            $unitId = (int)($item['unit_id'] ?? 0);
            if (!$unitId) {
                $unitId = (int)($item['new_item_unit_id'] ?? 0);
            }
            if (!$unitId) {
                $firstUnit = Database::query("SELECT id FROM units ORDER BY id LIMIT 1")->fetch();
                $unitId = (int)($firstUnit['id'] ?? 0);
            }
            if ($isNew || !$itemId) {
                $plan[] = [
                    'item_id' => $itemId, 'item_name' => $item['item_name'], 'unit_id' => $unitId,
                    'requested_qty' => $need, 'available_qty' => 0, 'transfer_qty' => 0,
                    'purchase_qty' => $need, 'manufacturing_qty' => 0, 'warehouse_id' => $warehouseId,
                    'material_request_item_id' => $item['id'], 'is_new_item' => 1,
                ];
                continue;
            }
            $stock = $warehouseId ? StockService::balance($warehouseId, $itemId) : StockService::totalBalance($itemId);
            $available = UnitConversionService::fromBaseUnit($unitId, $stock);
            $transferQty = min($available, $need);
            $remaining = $need - $transferQty;
            $poQty = 0;
            $mfgQty = 0;
            if ($remaining > 0) {
                $recipe = Database::query(
                    "SELECT id FROM production_recipes WHERE output_item_id = ? LIMIT 1",
                    [$itemId]
                )->fetch();
                if ($recipe || ($mr && ($mr['production_order_id'] || $mr['kitchen_section_id']))) {
                    $mfgQty = $remaining;
                } else {
                    $poQty = $remaining;
                }
            }
            $plan[] = [
                'item_id' => $itemId, 'item_name' => $item['item_name'], 'unit_id' => $unitId,
                'requested_qty' => $need, 'available_qty' => $available, 'transfer_qty' => $transferQty,
                'purchase_qty' => $poQty, 'manufacturing_qty' => $mfgQty, 'warehouse_id' => $warehouseId,
                'material_request_item_id' => $item['id'], 'is_new_item' => 0,
            ];
        }
        return $plan;
    }

    public static function executeFulfillment(int $requestId, array $plan, int $userId): void
    {
        $mr = self::find($requestId);
        if (!$mr || $mr['status'] !== 'approved') throw new \RuntimeException('الطلب غير معتمد');
        Database::beginTransaction();
        try {
            foreach ($plan as $line) {
                $itemId = (int)$line['item_id'];
                $mriId = (int)$line['material_request_item_id'];
                $warehouseId = (int)($line['warehouse_id'] ?? 0);
                $fromWarehouseId = (int)($line['from_warehouse_id'] ?? $warehouseId);
                $toWarehouseId = (int)($line['to_warehouse_id'] ?? $warehouseId);
                $unitId = (int)($line['unit_id'] ?? 0);
                if (!$unitId) {
                    $firstUnit = Database::query("SELECT id FROM units ORDER BY id LIMIT 1")->fetch();
                    $unitId = (int)($firstUnit['id'] ?? 0);
                }
                if ((float)$line['transfer_qty'] > 0 && $fromWarehouseId && $toWarehouseId && $fromWarehouseId !== $toWarehouseId) {
                    $baseQty = UnitConversionService::toBaseUnit($unitId, (float)$line['transfer_qty']);
                    StockService::move($fromWarehouseId, $itemId, 'transfer_out', $baseQty, 0, $mr['request_no'], 'مناقلة لطلب مواد');
                    StockService::move($toWarehouseId, $itemId, 'transfer_in', $baseQty, 0, $mr['request_no'], 'استلام من طلب مواد');
                    self::recordFulfillment($requestId, $mriId, 'transfer', 0, $mr['request_no'], (float)$line['transfer_qty'], $fromWarehouseId, $toWarehouseId, $userId);
                }
                if ((float)$line['purchase_qty'] > 0 && empty($line['is_new_item'])) {
                    $poId = self::createPurchaseOrder($requestId, $itemId, $unitId, (float)$line['purchase_qty'], $userId);
                    self::recordFulfillment($requestId, $mriId, 'purchase_order', $poId, '', (float)$line['purchase_qty'], null, null, $userId);
                }
                if ((float)$line['manufacturing_qty'] > 0 && empty($line['is_new_item'])) {
                    $prodId = self::createProductionOrderFromLine($itemId, $unitId, (float)$line['manufacturing_qty'], $toWarehouseId, $userId, (int)($mr['cost_center_id'] ?? 0));
                    self::recordFulfillment($requestId, $mriId, 'manufacturing_order', $prodId, '', (float)$line['manufacturing_qty'], null, $toWarehouseId, $userId);
                }
                self::updateItemFulfilledQty($mriId);
            }
            self::updateRequestStatus($requestId);
            Database::commit();
            self::audit($requestId, 'fulfillment_executed', ['status' => 'approved'], ['status' => 'fulfilling']);
            Helpers::queueSync('material_requests', $requestId, 'update', ['status' => 'fulfilling']);
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public static function updateFulfillmentFromReceipt(int $poId, int $itemId, float $qty, int $warehouseId): void
    {
        $po = Database::query("SELECT request_id FROM purchase_orders WHERE id = ?", [$poId])->fetch();
        if (!$po || !$po['request_id']) return;
        $prId = (int) $po['request_id'];

        $mr = Database::query("SELECT id FROM material_requests WHERE purchase_request_id = ?", [$prId])->fetch();
        if (!$mr) return;
        $mrId = (int) $mr['id'];

        $pri = Database::query(
            "SELECT material_request_item_id FROM purchase_request_items
             WHERE request_id = ? AND item_id = ? AND material_request_item_id IS NOT NULL
             ORDER BY id DESC LIMIT 1",
            [$prId, $itemId]
        )->fetch();
        if (!$pri) return;
        $mriId = (int) $pri['material_request_item_id'];

        self::recordFulfillment($mrId, $mriId, 'purchase_order', $poId, '', $qty, null, $warehouseId, (int)(Auth::id() ?? 0));
        self::updateItemFulfilledQty($mriId);
        self::updateRequestStatus($mrId);
    }

    private static function syncItems(int $requestId, array $items): void
    {
        Database::query("DELETE FROM material_request_items WHERE material_request_id = ?", [$requestId]);
        foreach ($items as $i) {
            if ((float)($i['requested_qty'] ?? 0) <= 0) continue;
            $isNew = !empty($i['is_new_item']);
            $qty = (float)$i['requested_qty'];
            $price = (float)($i['unit_price'] ?? 0);
            $total = $price * $qty;
            if ($isNew) {
                if (empty($i['new_item_name'])) continue;
                Database::query(
                    "INSERT INTO material_request_items (material_request_id, is_new_item, new_item_name, new_item_category_id, new_item_unit_id, new_item_notes, unit_id, requested_qty, unit_price, total_price, warehouse_id, required_date, purpose, notes)
                    VALUES (?,1,?,?,?,?,?,?,?,?,?,?,?,?)",
                    [
                        $requestId, (string)$i['new_item_name'], (int)($i['new_item_category_id'] ?? 0) ?: null,
                        (int)($i['new_item_unit_id'] ?? 0) ?: null, $i['new_item_notes'] ?? '',
                        (int)($i['new_item_unit_id'] ?? 0) ?: null, $qty, $price, $total,
                        (int)($i['warehouse_id'] ?? 0) ?: null, $i['required_date'] ?: null,
                        $i['purpose'] ?? '', $i['notes'] ?? ''
                    ]
                );
            } else {
                if (empty($i['item_id'])) continue;
                $unitId = (int)($i['unit_id'] ?? 0) ?: null;
                Database::query(
                    "INSERT INTO material_request_items (material_request_id, item_id, unit_id, requested_qty, unit_price, total_price, warehouse_id, required_date, purpose, notes)
                    VALUES (?,?,?,?,?,?,?,?,?,?)",
                    [$requestId, (int)$i['item_id'], $unitId, $qty, $price, $total, (int)($i['warehouse_id'] ?? 0) ?: null, $i['required_date'] ?: null, $i['purpose'] ?? '', $i['notes'] ?? '']
                );
            }
        }
        $total = (float) Database::query("SELECT COALESCE(SUM(total_price),0) AS t FROM material_request_items WHERE material_request_id = ?", [$requestId])->fetch()['t'];
        Database::query("UPDATE material_requests SET total_estimated_cost = ? WHERE id = ?", [$total, $requestId]);
    }

    private static function createPurchaseOrder(int $requestId, int $itemId, int $unitId, float $qty, int $userId): int
    {
        $mr = self::find($requestId);
        $poNo = Helpers::docNumber('PO', 'purchase_orders', 'po_no');
        // افتراضي: مورد عام إذا لم يُحدد
        $supplier = Database::query("SELECT id FROM suppliers WHERE is_active = 1 ORDER BY id LIMIT 1")->fetch();
        $supplierId = $supplier ? (int)$supplier['id'] : null;
        $baseQty = UnitConversionService::toBaseUnit($unitId, $qty);
        $item = Database::query("SELECT cost_price FROM items WHERE id = ?", [$itemId])->fetch();
        $price = (float)($item['cost_price'] ?? 0);
        Database::query(
            "INSERT INTO purchase_orders (po_no, supplier_id, request_id, status, total, created_by)
             VALUES (?,?,?,'sent',?,?)",
            [$poNo, $supplierId, null, $baseQty * $price, $userId]
        );
        $poId = (int) Database::lastInsertId();
        Database::query(
            "INSERT INTO purchase_order_items (po_id, item_id, qty, unit_price)
             VALUES (?,?,?,?)",
            [$poId, $itemId, $baseQty, $price]
        );
        return $poId;
    }

    private static function createProductionOrderFromLine(int $itemId, int $unitId, float $qty, ?int $toWarehouseId, int $userId, ?int $costCenterId): int
    {
        $recipe = Database::query("SELECT id FROM production_recipes WHERE output_item_id = ? LIMIT 1", [$itemId])->fetch();
        $recipeId = $recipe ? (int)$recipe['id'] : null;
        $no = Helpers::docNumber('PRD', 'production_orders', 'production_no');
        $baseQty = UnitConversionService::toBaseUnit($unitId, $qty);
        Database::query(
            "INSERT INTO production_orders (production_no, item_id, recipe_id, planned_qty, production_date, status, cost_center_id, created_by)
             VALUES (?,?,?,?,?,'planned',?,?)",
            [$no, $itemId, $recipeId, $baseQty, date('Y-m-d'), $costCenterId, $userId]
        );
        $prodId = (int) Database::lastInsertId();
        if ($recipeId && $toWarehouseId) {
            try {
                ProductionService::manufacture($recipeId, $baseQty, $toWarehouseId, $toWarehouseId, $no, 'تلبية طلب مواد');
                Database::query(
                    "UPDATE production_orders SET actual_qty = ?, status = 'done' WHERE id = ?",
                    [$baseQty, $prodId]
                );
            } catch (\Throwable $e) {
                // المواد غير متوفرة: يبقى أمر الإنتاج planned ويرتبط بطلب المواد
            }
        }
        return $prodId;
    }


    private static function createItemsFromNewLines(int $requestId): void
    {
        $lines = Database::query(
            "SELECT * FROM material_request_items WHERE material_request_id = ? AND is_new_item = 1",
            [$requestId]
        )->fetchAll();
        foreach ($lines as $line) {
            if (!empty($line['item_id'])) continue;
            $itemId = self::createItemFromLine($line);
            if ($itemId) {
                Database::query(
                    "UPDATE material_request_items SET item_id = ?, unit_id = new_item_unit_id WHERE id = ?",
                    [$itemId, $line['id']]
                );
            }
        }
    }

    private static function createItemFromLine(array $line): ?int
    {
        if (empty($line['new_item_name']) || empty($line['new_item_category_id']) || empty($line['new_item_unit_id'])) {
            return null;
        }
        $barcode = 'NEW-' . time() . '-' . $line['id'];
        Database::query(
            "INSERT INTO items (barcode, name, category_id, unit_id, cost_price, is_active)
             VALUES (?,?,?,?,?,1)",
            [$barcode, (string)$line['new_item_name'], (int)$line['new_item_category_id'], (int)$line['new_item_unit_id'], (float)($line['unit_price'] ?? 0)]
        );
        return (int) Database::lastInsertId();
    }

    private static function createPurchaseRequestForNewItems(int $requestId): void
    {
        $mr = self::find($requestId);
        if (!$mr) return;
        $lines = Database::query(
            "SELECT * FROM material_request_items WHERE material_request_id = ? AND is_new_item = 1 AND item_id IS NOT NULL",
            [$requestId]
        )->fetchAll();
        if (!$lines) return;

        $prId = self::createPurchaseRequest($requestId, $mr, $lines, true);
        if ($prId) {
            Database::query(
                "UPDATE material_requests SET purchase_request_id = ? WHERE id = ?",
                [$prId, $requestId]
            );
        }
    }

    /**
     * إنشاء طلب شراء من بي<|im_start|>ات طلب المواد (جديدة أو موجودة غير متوفرة)
     * وإرساله لمسار الموافقات.
     */
    private static function createPurchaseRequest(int $requestId, array $mr, array $lines, bool $isNewItem): ?int
    {
        if (!$lines) return null;
        $no = Helpers::docNumber('PRQ', 'purchase_requests', 'request_no');
        Database::query(
            "INSERT INTO purchase_requests (request_no, department, status, notes, requested_by, material_request_id)
             VALUES (?,?,?,?,?,?)",
            [$no, $mr['department_name'] ?? '', 'pending', 'تم إنشاؤه تلقائياً من طلب مواد ' . $mr['request_no'], $mr['requested_by'], $requestId]
        );
        $prId = (int) Database::lastInsertId();

        foreach ($lines as $line) {
            $unitId = $isNewItem ? (int)$line['new_item_unit_id'] : (int)$line['unit_id'];
            $baseQty = UnitConversionService::toBaseUnit($unitId, (float)$line['requested_qty']);
            $itemName = $isNewItem ? (string)$line['new_item_name'] : (string)$line['item_name'];
            Database::query(
                "INSERT INTO purchase_request_items (request_id, item_id, new_item_name, qty, notes, material_request_item_id)
                 VALUES (?,?,?,?,?,?)",
                [$prId, (int)$line['item_id'], $itemName, $baseQty, $line['new_item_notes'] ?? $line['notes'] ?? '', (int)$line['id']]
            );
        }

        ApprovalService::request('purchase_request', $prId);
        return $prId;
    }


    private static function planSubRequestsForExistingItems(int $requestId): void
    {
        $mr = self::find($requestId);
        if (!$mr) return;
        $lines = Database::query(
            "SELECT mri.*, i.name AS item_name, u.name AS unit_name
             FROM material_request_items mri
             JOIN items i ON i.id = mri.item_id
             LEFT JOIN units u ON u.id = mri.unit_id
             WHERE mri.material_request_id = ? AND mri.is_new_item = 0",
            [$requestId]
        )->fetchAll();
        if (!$lines) return;

        $purchaseLines = [];
        foreach ($lines as $line) {
            $unitId = (int)$line['unit_id'];
            $need = UnitConversionService::toBaseUnit($unitId, (float)$line['requested_qty']);
            $available = self::totalAvailable($line['item_id'], $unitId);
            if ($available >= $need) {
                $sourceWarehouseId = self::findWarehouseWithStock((int)$line['item_id'], $need);
                if ($sourceWarehouseId) {
                    self::createStockTransfer($requestId, $mr, $line, $sourceWarehouseId);
                    continue;
                }
            }
            $purchaseLines[] = $line;
        }

        if ($purchaseLines) {
            self::createPurchaseRequest($requestId, $mr, $purchaseLines, false);
        }
    }

    private static function totalAvailable(int $itemId, int $unitId): float
    {
        $base = StockService::totalBalance($itemId);
        return UnitConversionService::fromBaseUnit($unitId, $base);
    }

    private static function findWarehouseWithStock(int $itemId, float $baseQty): ?int
    {
        $row = Database::query(
            "SELECT warehouse_id FROM stock WHERE item_id = ? AND qty >= ? ORDER BY qty DESC LIMIT 1",
            [$itemId, $baseQty]
        )->fetch();
        return $row ? (int)$row['warehouse_id'] : null;
    }

    private static function createStockTransfer(int $requestId, array $mr, array $line, int $sourceWarehouseId): ?int
    {
        $unitId = (int)$line['unit_id'];
        $targetWarehouseId = (int)($line['warehouse_id'] ?? 0) ?: (int)($mr['warehouse_id'] ?? 0);
        if (!$targetWarehouseId) {
            $first = Database::query("SELECT id FROM warehouses WHERE is_active = 1 ORDER BY id LIMIT 1")->fetch();
            $targetWarehouseId = (int)($first['id'] ?? 0);
        }
        if (!$targetWarehouseId) return null;

        $no = Helpers::docNumber('TRF', 'stock_transfers', 'transfer_no');
        $baseQty = UnitConversionService::toBaseUnit($unitId, (float)$line['requested_qty']);
        Database::query(
            "INSERT INTO stock_transfers (transfer_no, from_warehouse_id, to_warehouse_id, material_request_id, requested_by, notes, status)
             VALUES (?,?,?,?,?,?,?)",
            [$no, $sourceWarehouseId, $targetWarehouseId, $requestId, $mr['requested_by'],
             'مناقلة من طلب مواد ' . $mr['request_no'], 'pending']
        );
        $transferId = (int) Database::lastInsertId();
        Database::query(
            "INSERT INTO stock_transfer_items (transfer_id, item_id, qty, unit_id, material_request_item_id)
             VALUES (?,?,?,?,?)",
            [$transferId, (int)$line['item_id'], $baseQty, $unitId, (int)$line['id']]
        );
        ApprovalService::request('material_request_transfer', $transferId);
        return $transferId;
    }


    public static function onTransferApproved(int $transferId): void
    {
        $t = Database::query("SELECT * FROM stock_transfers WHERE id = ?", [$transferId])->fetch();
        if (!$t || !$t['material_request_id']) return;
        $mrId = (int) $t['material_request_id'];
        self::audit($mrId, 'transfer_approved', ['status' => 'approved'], ['transfer_id' => $transferId, 'status' => 'fulfilling']);
        self::setFulfillingIfApproved($mrId);
    }

    public static function onPurchaseRequestApproved(int $prId): void
    {
        $pr = Database::query("SELECT * FROM purchase_requests WHERE id = ?", [$prId])->fetch();
        if (!$pr || !$pr['material_request_id']) return;
        $mrId = (int) $pr['material_request_id'];
        PurchasingService::onPurchaseRequestApproved($prId);
        self::audit($mrId, 'purchase_request_approved', ['status' => 'approved'], ['purchase_request_id' => $prId, 'status' => 'fulfilling']);
        self::setFulfillingIfApproved($mrId);
    }

    private static function setFulfillingIfApproved(int $mrId): void
    {
        $mr = self::find($mrId);
        if ($mr && $mr['status'] === 'approved') {
            Database::query("UPDATE material_requests SET status = 'fulfilling' WHERE id = ?", [$mrId]);
            Helpers::queueSync('material_requests', $mrId, 'update', ['status' => 'fulfilling']);
        }
    }

    public static function prepareTransfer(int $transferId, int $userId): void
    {
        $t = Database::query("SELECT * FROM stock_transfers WHERE id = ? AND status = 'approved'", [$transferId])->fetch();
        if (!$t) throw new \RuntimeException('المناقلة غير معتمدة');
        Database::query(
            "UPDATE stock_transfers SET status = 'prepared', prepared_by = ?, prepared_at = NOW() WHERE id = ?",
            [$userId, $transferId]
        );
        if ($t['material_request_id']) {
            self::audit((int)$t['material_request_id'], 'transfer_prepared', ['status' => 'fulfilling'], ['transfer_id' => $transferId]);
            $mr = self::find((int)$t['material_request_id']);
            if ($mr) {
                NotificationService::transferPrepared($transferId, (int)$mr['requested_by'], $t['transfer_no']);
            }
        }
    }

    public static function deliverTransfer(int $transferId, string $recipientName, string $signature, int $userId): void
    {
        $t = Database::query(
            "SELECT * FROM stock_transfers WHERE id = ? AND status IN ('approved','prepared')",
            [$transferId]
        )->fetch();
        if (!$t) throw new \RuntimeException('المناقلة غير جاهزة للتسليم');

        Database::beginTransaction();
        try {
            $lines = Database::query("SELECT * FROM stock_transfer_items WHERE transfer_id = ?", [$transferId])->fetchAll();
            foreach ($lines as $line) {
                StockService::move((int)$t['from_warehouse_id'], (int)$line['item_id'], 'transfer_out',
                    (float)$line['qty'], 0, $t['transfer_no'], 'مناقلة طلب مواد');
                StockService::move((int)$t['to_warehouse_id'], (int)$line['item_id'], 'transfer_in',
                    (float)$line['qty'], 0, $t['transfer_no'], 'استلام مناقلة طلب مواد');
            }
            Database::query(
                "UPDATE stock_transfers SET status = 'delivered', recipient_name = ?, recipient_signature = ?, recipient_user_id = ?, delivered_at = NOW(), delivered_by = ? WHERE id = ?",
                [$recipientName, $signature, $userId, $userId, $transferId]
            );
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }

        if ($t['material_request_id']) {
            $mrId = (int) $t['material_request_id'];
            foreach ($lines as $line) {
                if (!$line['material_request_item_id']) continue;
                $mriId = (int) $line['material_request_item_id'];
                $qty = UnitConversionService::fromBaseUnit((int)$line['unit_id'], (float)$line['qty']);
                self::recordFulfillment($mrId, $mriId, 'transfer', $transferId, $t['transfer_no'], $qty,
                    (int)$t['from_warehouse_id'], (int)$t['to_warehouse_id'], $userId);
                self::updateItemFulfilledQty($mriId);
            }
            self::updateRequestStatus($mrId);
            self::audit($mrId, 'transfer_delivered', ['status' => 'fulfilling'], ['transfer_id' => $transferId, 'recipient' => $recipientName]);
            $mr = self::find($mrId);
            if ($mr) {
                NotificationService::transferDelivered($transferId, (int)$mr['requested_by'], $t['transfer_no']);
            }
        }
    }

    private static function recordFulfillment(int $requestId, int $mriId, string $type, int $docId, string $docNo, float $qty, ?int $from, ?int $to, int $userId): void
    {
        Database::query(
            "INSERT INTO material_request_fulfillment (material_request_id, material_request_item_id, fulfillment_type, document_id, document_no, qty, from_warehouse_id, to_warehouse_id, created_by)
             VALUES (?,?,?,?,?,?,?,?,?)",
            [$requestId, $mriId, $type, $docId, $docNo, $qty, $from, $to, $userId]
        );
    }

    private static function updateItemFulfilledQty(int $mriId): void
    {
        $total = (float) Database::query("SELECT COALESCE(SUM(qty),0) AS t FROM material_request_fulfillment WHERE material_request_item_id = ?", [$mriId])->fetch()['t'];
        $req = (float) Database::query("SELECT requested_qty FROM material_request_items WHERE id = ?", [$mriId])->fetch()['requested_qty'];
        $status = $total <= 0 ? 'pending' : ($total >= $req ? 'fulfilled' : 'partially_fulfilled');
        Database::query("UPDATE material_request_items SET fulfilled_qty = ?, status = ? WHERE id = ?", [$total, $status, $mriId]);
    }

    private static function updateRequestStatus(int $requestId): void
    {
        $rows = Database::query("SELECT status FROM material_request_items WHERE material_request_id = ?", [$requestId])->fetchAll();
        if (!$rows) return;
        $allFulfilled = true; $anyFulfilled = false;
        foreach ($rows as $r) {
            if ($r['status'] !== 'fulfilled') $allFulfilled = false;
            if (in_array($r['status'], ['fulfilled','partially_fulfilled'], true)) $anyFulfilled = true;
        }
        $status = $allFulfilled ? 'fulfilled' : ($anyFulfilled ? 'partially_fulfilled' : 'fulfilling');
        Database::query("UPDATE material_requests SET status = ? WHERE id = ?", [$status, $requestId]);
    }

    private static function audit(int $requestId, string $action, array $old, array $new): void
    {
        Database::query(
            "INSERT INTO material_request_audit_logs (material_request_id, user_id, action, old_values, new_values, ip_address, user_agent)
             VALUES (?,?,?,?,?,?,?)",
            [$requestId, Auth::id() ?: null, $action, json_encode($old, JSON_UNESCAPED_UNICODE), json_encode($new, JSON_UNESCAPED_UNICODE), $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]
        );
    }
}
