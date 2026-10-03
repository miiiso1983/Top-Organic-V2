<?php
namespace App\Core;

class PurchasingService
{
    /** بعد اعتماد طلب الشراء: يُنشئ RFQ أو PO مباشرة */
    public static function onPurchaseRequestApproved(int $prId): void
    {
        $pr = Database::query("SELECT * FROM purchase_requests WHERE id = ?", [$prId])->fetch();
        if (!$pr || $pr['status'] !== 'approved') return;
        $items = Database::query(
            "SELECT pri.*, i.unit_id AS base_unit_id FROM purchase_request_items pri
             LEFT JOIN items i ON i.id = pri.item_id
             WHERE pri.request_id = ?",
            [$prId]
        )->fetchAll();
        if (!$items) return;

        $supplierCount = (int) Database::query("SELECT COUNT(*) FROM suppliers WHERE is_active = 1")->fetchColumn();
        if ($supplierCount > 1) {
            self::createRfqFromPurchaseRequest($prId, $items);
        } else {
            $supplier = Database::query("SELECT id FROM suppliers WHERE is_active = 1 ORDER BY id LIMIT 1")->fetch();
            if ($supplier) {
                self::createPoFromPurchaseRequest($prId, (int)$supplier['id'], (int)($pr['requested_by'] ?? 0) ?: 1);
            }
        }
    }

    public static function createRfqFromPurchaseRequest(int $prId, ?array $items = null): int
    {
        $pr = Database::query("SELECT * FROM purchase_requests WHERE id = ?", [$prId])->fetch();
        if (!$pr) throw new \RuntimeException('طلب الشراء غير موجود');
        $no = Helpers::docNumber('RFQ', 'request_for_quotations', 'rfq_no');
        Database::query(
            "INSERT INTO request_for_quotations (purchase_request_id, rfq_no, status, notes, created_by)
             VALUES (?,?,?,?,?)",
            [$prId, $no, 'draft', 'RFQ من ' . $pr['request_no'], (int)($pr['requested_by'] ?? 0) ?: 1]
        );
        $rfqId = (int) Database::lastInsertId();

        $items = $items ?: Database::query(
            "SELECT pri.*, i.unit_id AS base_unit_id FROM purchase_request_items pri
             LEFT JOIN items i ON i.id = pri.item_id
             WHERE pri.request_id = ?",
            [$prId]
        )->fetchAll();

        foreach ($items as $it) {
            $unitId = (int)($it['unit_id'] ?? $it['base_unit_id'] ?? 0) ?: null;
            Database::query(
                "INSERT INTO rfq_items (rfq_id, item_id, qty, unit_id, notes)
                 VALUES (?,?,?,?,?)",
                [$rfqId, (int)($it['item_id'] ?? 0) ?: null, (float)$it['qty'], $unitId, $it['notes'] ?? '']
            );
        }
        return $rfqId;
    }

    public static function createPoFromPurchaseRequest(int $prId, int $supplierId, int $userId): int
    {
        $pr = Database::query("SELECT * FROM purchase_requests WHERE id = ?", [$prId])->fetch();
        if (!$pr) throw new \RuntimeException('طلب الشراء غير موجود');
        $no = Helpers::docNumber('PO', 'purchase_orders', 'po_no');
        Database::query(
            "INSERT INTO purchase_orders (po_no, supplier_id, request_id, status, total, created_by)
             VALUES (?,?,?,'draft',0,?)",
            [$no, $supplierId, $prId, $userId]
        );
        $poId = (int) Database::lastInsertId();

        $items = Database::query(
            "SELECT pri.*, i.unit_id AS base_unit_id, i.cost_price FROM purchase_request_items pri
             LEFT JOIN items i ON i.id = pri.item_id
             WHERE pri.request_id = ?",
            [$prId]
        )->fetchAll();
        $total = 0.0;
        foreach ($items as $it) {
            $itemId = (int)($it['item_id'] ?? 0);
            if (!$itemId) continue;
            $unitId = (int)($it['unit_id'] ?? $it['base_unit_id'] ?? 0);
            $baseQty = (float)$it['qty'];
            $price = (float)($it['cost_price'] ?? 0);
            $lineTotal = $baseQty * $price;
            $total += $lineTotal;
            Database::query(
                "INSERT INTO purchase_order_items (po_id, item_id, qty, unit_price) VALUES (?,?,?,?)",
                [$poId, $itemId, $baseQty, $price]
            );
        }
        Database::query("UPDATE purchase_orders SET total = ? WHERE id = ?", [$total, $poId]);
        Database::query("UPDATE purchase_requests SET status = 'converted' WHERE id = ?", [$prId]);

        // إرسال إشعار إصدار أمر الشراء لصاحب طلب المواد الأب
        $requester = self::requesterForPurchaseRequest($prId);
        if ($requester) {
            NotificationService::poIssued($poId, $requester, $no);
        }
        return $poId;
    }

    private static function requesterForPurchaseRequest(int $prId): ?int
    {
        $pr = Database::query("SELECT material_request_id FROM purchase_requests WHERE id = ?", [$prId])->fetch();
        if (!$pr || !$pr['material_request_id']) return null;
        $mr = Database::query("SELECT requested_by FROM material_requests WHERE id = ?", [(int)$pr['material_request_id']])->fetch();
        return $mr ? (int)$mr['requested_by'] : null;
    }

    public static function addSupplierQuote(int $rfqId, int $supplierId, array $lines, array $data = []): int
    {
        $rfq = Database::query("SELECT * FROM request_for_quotations WHERE id = ?", [$rfqId])->fetch();
        if (!$rfq) throw new \RuntimeException('RFQ غير موجود');
        $total = 0.0;
        foreach ($lines as $line) {
            $total += (float)($line['qty'] ?? 0) * (float)($line['unit_price'] ?? 0);
        }
        Database::query(
            "INSERT INTO quotations (rfq_id, supplier_id, total, status, valid_until, notes)
             VALUES (?,?,?,?,?,?)",
            [$rfqId, $supplierId, $total, 'received', $data['valid_until'] ?? null, $data['notes'] ?? '']
        );
        $quoteId = (int) Database::lastInsertId();
        foreach ($lines as $line) {
            $itemId = (int)($line['item_id'] ?? 0);
            if (!$itemId) continue;
            $qty = (float)($line['qty'] ?? 0);
            $price = (float)($line['unit_price'] ?? 0);
            Database::query(
                "INSERT INTO quotation_items (quotation_id, item_id, qty, unit_id, unit_price, total)
                 VALUES (?,?,?,?,?,?)",
                [$quoteId, $itemId, $qty, (int)($line['unit_id'] ?? 0) ?: null, $price, $qty * $price]
            );
        }
        Database::query("UPDATE request_for_quotations SET status = 'received' WHERE id = ?", [$rfqId]);
        return $quoteId;
    }

    public static function compareQuotes(int $rfqId): array
    {
        return Database::query(
            "SELECT q.*, s.name AS supplier_name, s.rating
             FROM quotations q
             JOIN suppliers s ON s.id = q.supplier_id
             WHERE q.rfq_id = ? AND q.status = 'received'
             ORDER BY q.total ASC, s.rating DESC",
            [$rfqId]
        )->fetchAll();
    }

    public static function selectQuoteAndCreatePo(int $quoteId, int $userId): int
    {
        $quote = Database::query(
            "SELECT q.*, r.purchase_request_id FROM quotations q
             JOIN request_for_quotations r ON r.id = q.rfq_id
             WHERE q.id = ?",
            [$quoteId]
        )->fetch();
        if (!$quote) throw new \RuntimeException('عرض السعر غير موجود');

        Database::beginTransaction();
        try {
            Database::query("UPDATE quotations SET status = 'accepted' WHERE id = ?", [$quoteId]);
            Database::query(
                "UPDATE quotations SET status = 'rejected' WHERE rfq_id = ? AND id != ? AND status = 'received'",
                [(int)$quote['rfq_id'], $quoteId]
            );
            Database::query("UPDATE request_for_quotations SET status = 'closed' WHERE id = ?", [(int)$quote['rfq_id']]);

            $no = Helpers::docNumber('PO', 'purchase_orders', 'po_no');
            Database::query(
                "INSERT INTO purchase_orders (po_no, supplier_id, request_id, rfq_id, status, total, created_by)
                 VALUES (?,?,?,?,?,'draft',?)",
                [$no, (int)$quote['supplier_id'], (int)$quote['purchase_request_id'], (int)$quote['rfq_id'], (float)$quote['total'], $userId]
            );
            $poId = (int) Database::lastInsertId();

            $items = Database::query(
                "SELECT qi.* FROM quotation_items qi WHERE qi.quotation_id = ?",
                [$quoteId]
            )->fetchAll();
            foreach ($items as $it) {
                Database::query(
                    "INSERT INTO purchase_order_items (po_id, item_id, qty, unit_price) VALUES (?,?,?,?)",
                    [$poId, (int)$it['item_id'], (float)$it['qty'], (float)$it['unit_price']]
                );
            }
            if ($quote['purchase_request_id']) {
                Database::query("UPDATE purchase_requests SET status = 'converted' WHERE id = ?", [(int)$quote['purchase_request_id']]);
            }
            Database::commit();
            return $poId;
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public static function approvePoAndSend(int $poId, int $userId): void
    {
        Database::query("UPDATE purchase_orders SET status = 'sent' WHERE id = ?", [$poId]);
        self::addPoTracking($poId, 'processing', null, 'تم اعتماد أمر الشراء وإرساله للمورد', $userId);
    }

    public static function addPoTracking(int $poId, string $status, ?string $location, string $notes, int $userId): void
    {
        Database::query(
            "INSERT INTO purchase_order_tracking (po_id, status, location, notes, created_by) VALUES (?,?,?,?,?)",
            [$poId, $status, $location, $notes, $userId]
        );
        if (in_array($status, ['shipped','in_transit','customs','delivered_to_warehouse'], true)) {
            Database::query("UPDATE purchase_orders SET shipping_status = ? WHERE id = ?", [$status, $poId]);
        }
    }

    public static function addPoShipment(int $poId, array $data, int $userId): int
    {
        Database::query(
            "INSERT INTO purchase_order_shipments (po_id, tracking_no, carrier, shipping_date, expected_arrival, status, notes, created_by)
             VALUES (?,?,?,?,?,?,?,?)",
            [$poId, $data['tracking_no'] ?? '', $data['carrier'] ?? '', $data['shipping_date'] ?? null,
             $data['expected_arrival'] ?? null, $data['status'] ?? 'draft', $data['notes'] ?? '', $userId]
        );
        return (int) Database::lastInsertId();
    }

    public static function receiveShipment(int $shipmentId, int $warehouseId, int $userId): void
    {
        $shipment = Database::query("SELECT * FROM purchase_order_shipments WHERE id = ?", [$shipmentId])->fetch();
        if (!$shipment) throw new \RuntimeException('الشحنة غير موجودة');
        $poId = (int) $shipment['po_id'];
        Database::query("UPDATE purchase_order_shipments SET status = 'received', actual_arrival = CURDATE() WHERE id = ?", [$shipmentId]);
        Database::query("UPDATE purchase_orders SET shipping_status = 'received', actual_arrival = CURDATE(), status = 'partially_received' WHERE id = ?", [$poId]);
        self::addPoTracking($poId, 'received', null, 'تم استلام الشحنة في المخزن', $userId);
    }
}
