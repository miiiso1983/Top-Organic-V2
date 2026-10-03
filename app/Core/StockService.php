<?php
namespace App\Core;

/**
 * خدمة حركات المخزون: تسجيل الحركة وتحديث الرصيد معاً
 */
class StockService
{
    public static function move(
        int $warehouseId,
        int $itemId,
        string $type,
        float $qty,
        float $unitCost = 0,
        ?string $reference = null,
        ?string $notes = null,
        ?string $expiryDate = null,
        ?int $inputUnitId = null,
        ?int $destinationWarehouseId = null,
        ?string $batchNo = null,
        ?string $productionDate = null,
        ?string $barcode = null,
        ?string $qrCode = null
    ): void {
        Database::query(
            "INSERT INTO stock_movements
             (warehouse_id, destination_warehouse_id, item_id, movement_type, qty, input_unit_id, unit_cost, expiry_date, reference, notes, created_by, batch_no, production_date, barcode, qr_code)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [$warehouseId, $destinationWarehouseId, $itemId, $type, $qty, $inputUnitId, $unitCost, $expiryDate, $reference, $notes, Auth::id(), $batchNo, $productionDate, $barcode, $qrCode]
        );
        $movementId = (int) Database::lastInsertId();

        $delta = in_array($type, ['in', 'transfer_in', 'return'], true) ? $qty : -$qty;
        if ($type === 'adjustment') {
            $delta = $qty; // قيمة الفرق موجبة أو سالبة
        }

        // تحديث الرصيد بشكل صريح لتجنب مشاكل ON DUPLICATE KEY UPDATE في بعض إعدادات MySQL
        $existing = Database::query(
            "SELECT id FROM stock WHERE warehouse_id = ? AND item_id = ?",
            [$warehouseId, $itemId]
        )->fetch();
        if ($existing) {
            Database::query(
                "UPDATE stock SET qty = qty + ? WHERE warehouse_id = ? AND item_id = ?",
                [$delta, $warehouseId, $itemId]
            );
        } else {
            Database::query(
                "INSERT INTO stock (warehouse_id, item_id, qty) VALUES (?,?,?)",
                [$warehouseId, $itemId, $delta]
            );
        }

        // تحديث/إنشاء دفعة في المخزون عند الإدخال
        if (in_array($type, ['in', 'transfer_in'], true) && $batchNo) {
            self::updateBatch($warehouseId, $itemId, $batchNo, $delta, $unitCost, $expiryDate, $productionDate, $barcode, $qrCode);
        }
        if (in_array($type, ['out', 'transfer_out'], true) && $batchNo) {
            self::updateBatch($warehouseId, $itemId, $batchNo, -$delta, $unitCost, $expiryDate, $productionDate, $barcode, $qrCode);
        }

        // تسجيل الحركة في طابور المزامنة الهجين
        Helpers::queueSync('stock_movements', $movementId, 'insert', [
            'warehouse_id' => $warehouseId,
            'destination_warehouse_id' => $destinationWarehouseId,
            'item_id'      => $itemId,
            'movement_type'=> $type,
            'qty'          => $qty,
            'input_unit_id'=> $inputUnitId,
            'unit_cost'    => $unitCost,
            'expiry_date'  => $expiryDate,
            'reference'    => $reference,
            'notes'        => $notes,
            'batch_no'     => $batchNo,
            'production_date' => $productionDate,
            'barcode'      => $barcode,
            'qr_code'      => $qrCode,
            'balance_after'=> self::balance($warehouseId, $itemId),
        ]);

        // تفقّد النواقص للمواد الأولية فقط
        AlertService::checkLowStock($itemId);
    }

    private static function updateBatch(int $warehouseId, int $itemId, string $batchNo, float $delta, float $unitCost, ?string $expiryDate, ?string $productionDate, ?string $barcode, ?string $qrCode): void
    {
        $existing = Database::query(
            "SELECT id FROM stock_batches WHERE warehouse_id = ? AND item_id = ? AND batch_no = ?",
            [$warehouseId, $itemId, $batchNo]
        )->fetch();
        if ($existing) {
            Database::query(
                "UPDATE stock_batches SET qty = qty + ?, unit_cost = ?, expiry_date = ?, production_date = ?, barcode = ?, qr_code = ?
                 WHERE id = ?",
                [$delta, $unitCost, $expiryDate, $productionDate, $barcode, $qrCode, $existing['id']]
            );
        } else {
            Database::query(
                "INSERT INTO stock_batches (warehouse_id, item_id, batch_no, qty, unit_cost, expiry_date, production_date, barcode, qr_code)
                 VALUES (?,?,?,?,?,?,?,?,?)",
                [$warehouseId, $itemId, $batchNo, $delta, $unitCost, $expiryDate, $productionDate, $barcode, $qrCode]
            );
        }
    }

    public static function updateAverageCost(int $itemId, float $newQty, float $newUnitCost): float
    {
        $current = Database::query(
            "SELECT COALESCE(SUM(qty),0) AS total_qty FROM stock WHERE item_id = ?",
            [$itemId]
        )->fetch();
        $currentQty = (float) $current['total_qty'] - $newQty; // الرصيد قبل الإضافة
        if ($currentQty < 0) $currentQty = 0;

        $item = Database::query("SELECT cost_price FROM items WHERE id = ?", [$itemId])->fetch();
        $currentCost = (float) ($item['cost_price'] ?? 0);

        $totalValue = ($currentQty * $currentCost) + ($newQty * $newUnitCost);
        $totalQty = $currentQty + $newQty;
        $avgCost = $totalQty > 0 ? $totalValue / $totalQty : $currentCost;

        Database::query("UPDATE items SET cost_price = ? WHERE id = ?", [$avgCost, $itemId]);
        return $avgCost;
    }

    /** تسليم مناقلة معتمدة وتحديث رصيد المخازن المصدر/الوجهة */
    public static function deliverTransfer(int $transferId): void
    {
        $t = Database::query("SELECT * FROM stock_transfers WHERE id = ? AND status = 'approved'", [$transferId])->fetch();
        if (!$t) {
            throw new \Exception('المناقلة غير موجودة أو غير معتمدة');
        }
        $lines = Database::query("SELECT * FROM stock_transfer_items WHERE transfer_id = ?", [$transferId])->fetchAll();
        Database::beginTransaction();
        try {
            foreach ($lines as $line) {
                self::move((int)$t['from_warehouse_id'], (int)$line['item_id'], 'transfer_out',
                    (float)$line['qty'], 0, $t['transfer_no']);
                self::move((int)$t['to_warehouse_id'], (int)$line['item_id'], 'transfer_in',
                    (float)$line['qty'], 0, $t['transfer_no']);
            }
            Database::query("UPDATE stock_transfers SET status='delivered' WHERE id = ?", [$transferId]);
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    /**
     * تسجيل حركة مع تحويل الوحدة المدخلة إلى الوحدة الأساسية
     */
    public static function moveWithUnit(
        int $warehouseId,
        int $itemId,
        string $type,
        float $qty,
        int $inputUnitId,
        float $unitCost = 0,
        ?string $reference = null,
        ?string $notes = null,
        ?string $expiryDate = null,
        ?int $destinationWarehouseId = null
    ): void {
        $baseQty = UnitConversionService::toBaseUnit($inputUnitId, $qty);
        self::move($warehouseId, $itemId, $type, $baseQty, $unitCost, $reference, $notes, $expiryDate, $inputUnitId, $destinationWarehouseId);
    }

    /** الرصيد الإجمالي للمادة في جميع المخازن */
    public static function totalBalance(int $itemId): float
    {
        $row = Database::query(
            "SELECT COALESCE(SUM(qty), 0) AS total FROM stock WHERE item_id = ?",
            [$itemId]
        )->fetch();
        return (float) ($row['total'] ?? 0);
    }

    public static function balance(int $warehouseId, int $itemId): float
    {
        $row = Database::query(
            "SELECT qty FROM stock WHERE warehouse_id = ? AND item_id = ?",
            [$warehouseId, $itemId]
        )->fetch();
        return (float)($row['qty'] ?? 0);
    }

    /** استهلاك مكونات وصفة صنف مباع من مخزن المطبخ */
    public static function consumeRecipe(int $menuItemId, float $qty, int $warehouseId, string $reference): void
    {
        $ingredients = Database::query(
            "SELECT ri.item_id, ri.qty FROM recipes r
             JOIN recipe_ingredients ri ON ri.recipe_id = r.id
             WHERE r.menu_item_id = ?",
            [$menuItemId]
        )->fetchAll();

        foreach ($ingredients as $ing) {
            self::move(
                $warehouseId,
                (int)$ing['item_id'],
                'consumption',
                (float)$ing['qty'] * $qty,
                0,
                $reference,
                'استهلاك وصفة'
            );
        }
    }

    /** المواد التي وصلت لحد النواقص */
    public static function lowStock(): array
    {
        $rows = Database::query(
            "SELECT i.id, i.name, i.barcode, i.min_qty, i.unit_id, u.name AS unit,
                    COALESCE(SUM(s.qty),0) AS total_qty
             FROM items i
             JOIN units u ON u.id = i.unit_id
             LEFT JOIN stock s ON s.item_id = i.id
             WHERE i.is_active = 1 AND i.min_qty > 0
             GROUP BY i.id
             ORDER BY total_qty ASC"
        )->fetchAll();
        $result = [];
        foreach ($rows as $row) {
            $total = UnitConversionService::fromBaseUnit((int) $row['unit_id'], (float) $row['total_qty']);
            if ($total <= (float) $row['min_qty'] + 0.0001) {
                $row['total_qty'] = $total;
                $result[] = $row;
            }
        }
        return $result;
    }
}
