<?php
namespace App\Core;

/**
 * خدمة فواتير الشراء: إنشاء تلقائي، مطابقة، وإرسال للحسابات
 */
class PurchaseInvoiceService
{
    /** إنشاء فاتورة شراء تلقائياً بعد اعتماد الاستلام */
    public static function createFromReceipt(int $receiptId, ?int $userId = null): ?int
    {
        $receipt = Database::query(
            "SELECT gr.*, po.supplier_id, po.total, po.request_id, po.rfq_id
             FROM goods_receipts gr
             JOIN purchase_orders po ON po.id = gr.po_id
             WHERE gr.id = ?",
            [$receiptId]
        )->fetch();
        if (!$receipt) { return null; }

        $items = Database::query(
            "SELECT gri.*, poi.unit_price AS po_unit_price
             FROM goods_receipt_items gri
             LEFT JOIN purchase_order_items poi ON poi.po_id = gri.receipt_id AND poi.item_id = gri.item_id
             WHERE gri.receipt_id = ?",
            [$receiptId]
        )->fetchAll();
        if (!$items) { return null; }

        // منع تكرار الفاتورة لنفس الاستلام
        $existing = Database::query(
            "SELECT id FROM purchase_invoices WHERE receipt_id = ?",
            [$receiptId]
        )->fetch();
        if ($existing) { return (int)$existing['id']; }

        Database::beginTransaction();
        try {
            $invoiceNo = Helpers::docNumber('PINV', 'purchase_invoices', 'invoice_no');
            $subtotal = 0;
            foreach ($items as $it) {
                $price = (float)($it['po_unit_price'] ?: $it['unit_price']);
                $subtotal += (float)$it['qty'] * $price;
            }
            $total = $subtotal; // بدون ضريبة/خصم افتراضياً

            Database::query(
                "INSERT INTO purchase_invoices (invoice_no, supplier_id, po_id, receipt_id, amount, total_amount, status, remaining_amount, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?)",
                [$invoiceNo, $receipt['supplier_id'], $receipt['po_id'], $receiptId, $total, $total, 'sent_to_accounting', $total, $userId ?? Auth::id()]
            );
            $invoiceId = (int)Database::lastInsertId();

            foreach ($items as $it) {
                $price = (float)($it['po_unit_price'] ?: $it['unit_price']);
                $lineTotal = (float)$it['qty'] * $price;
                Database::query(
                    "INSERT INTO purchase_invoice_items (invoice_id, item_id, qty, unit_price, total)
                     VALUES (?,?,?,?,?)",
                    [$invoiceId, $it['item_id'], $it['qty'], $price, $lineTotal]
                );
            }

            // تسجيل مطابقة الاستلام
            Database::query(
                "INSERT INTO invoice_receipt_matching (invoice_id, receipt_id, po_id, matched_by, status)
                 VALUES (?,?,?,?,?)",
                [$invoiceId, $receiptId, $receipt['po_id'], $userId ?? Auth::id(), 'matched']
            );

            // استخراج مركز الكلفة من الاستلام أو أمر الشراء
            $costCenterId = self::costCenterForReceipt($receiptId);

            // إنشاء قيد محاسبي: المخزون مدين / الموردين دائن
            self::postInvoiceJournal($invoiceId, $receiptId, $total, $costCenterId);

            // إشعار قسم الحسابات
            self::notifyAccounting($invoiceId, $invoiceNo, $total);

            // إشعار صاحب طلب المواد بإنشاء الفاتورة وجاهزية الدفع
            $requester = self::requesterForReceipt($receiptId);
            if ($requester) {
                NotificationService::invoiceCreated($invoiceId, $requester, $invoiceNo, $total);
                NotificationService::paymentReady($invoiceId, $requester, $invoiceNo, $total);
            }

            Database::commit();
            return $invoiceId;
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    /** تسجيل قيد محاسبي للفاتورة: المخزون مدين، الموردون دائن */
    private static function postInvoiceJournal(int $invoiceId, int $receiptId, float $total, ?int $costCenterId = null): void
    {
        $invAcc = AccountingService::accountId('1100');
        $payAcc = AccountingService::accountId('2000');
        if (!$invAcc || !$payAcc) { return; }
        $receipt = Database::query(
            "SELECT receipt_no, po_no FROM goods_receipts gr JOIN purchase_orders po ON po.id = gr.po_id WHERE gr.id = ?",
            [$receiptId]
        )->fetch();
        AccountingService::post([
            'entry_date' => date('Y-m-d'),
            'description' => 'فاتورة شراء - ' . ($receipt['receipt_no'] ?? '') . ' / ' . ($receipt['po_no'] ?? ''),
            'cost_center_id' => $costCenterId,
            'source_type' => 'purchase_invoice', 'source_id' => $invoiceId,
            'lines' => [
                ['account_id' => $invAcc, 'debit' => $total, 'credit' => 0],
                ['account_id' => $payAcc, 'debit' => 0, 'credit' => $total],
            ],
        ]);
    }

    private static function costCenterForReceipt(int $receiptId): ?int
    {
        $gr = Database::query("SELECT cost_center_id, po_id FROM goods_receipts WHERE id = ?", [$receiptId])->fetch();
        if (!$gr) return null;
        if ($gr['cost_center_id']) return (int)$gr['cost_center_id'];
        $po = Database::query("SELECT cost_center_id FROM purchase_orders WHERE id = ?", [(int)($gr['po_id'] ?? 0)])->fetch();
        return $po ? (int)($po['cost_center_id'] ?? 0) : null;
    }

    private static function requesterForReceipt(int $receiptId): ?int
    {
        $gr = Database::query(
            "SELECT gr.po_id, po.request_id, pr.material_request_id, mr.requested_by
             FROM goods_receipts gr
             JOIN purchase_orders po ON po.id = gr.po_id
             LEFT JOIN purchase_requests pr ON pr.id = po.request_id
             LEFT JOIN material_requests mr ON mr.id = pr.material_request_id
             WHERE gr.id = ?",
            [$receiptId]
        )->fetch();
        return $gr ? (int)($gr['requested_by'] ?? 0) : null;
    }

    /** إشعار مستخدمي الحسابات بوجود فاتورة جديدة */
    private static function notifyAccounting(int $invoiceId, string $invoiceNo, float $amount): void
    {
        $accountingRole = Database::query("SELECT id FROM roles WHERE name = 'accounting' OR code = 'accounting' LIMIT 1")->fetch();
        if ($accountingRole) {
            Helpers::notifyRole((int)$accountingRole['id'], 'فاتورة شراء جديدة', "فاتورة $invoiceNo بمبلغ $amount تم إرسالها للحسابات", '/purchasing/invoices/' . $invoiceId);
        }
    }

    /** تسجيل دفعة على الفاتورة وتحديث الحالة */
    public static function recordPayment(int $invoiceId, float $amount, string $method, ?string $reference, ?string $notes, ?int $userId = null): void
    {
        $invoice = Database::query("SELECT * FROM purchase_invoices WHERE id = ?", [$invoiceId])->fetch();
        if (!$invoice) { throw new \RuntimeException('الفاتورة غير موجودة'); }
        if ($amount <= 0) { throw new \RuntimeException('مبلغ غير صالح'); }
        if ($amount > (float)$invoice['remaining_amount']) { throw new \RuntimeException('المبلغ أكبر من المتبقي'); }

        Database::beginTransaction();
        try {
            $paymentNo = Helpers::docNumber('PAY', 'payments', 'payment_no');
            Database::query(
                "INSERT INTO payments (payment_no, party_type, party_id, direction, amount, method, reference, description, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?)",
                [$paymentNo, 'supplier', $invoice['supplier_id'], 'out', $amount, $method, $reference, $notes ?? 'دفعة فاتورة شراء', $userId ?? Auth::id()]
            );
            $paymentId = (int)Database::lastInsertId();

            $paid = (float)$invoice['paid_amount'] + $amount;
            $remaining = (float)$invoice['total_amount'] - $paid;
            $status = $remaining <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');
            $invStatus = $remaining <= 0 ? 'paid' : 'approved';

            Database::query(
                "UPDATE purchase_invoices SET paid_amount = ?, remaining_amount = ?, payment_status = ?, status = ? WHERE id = ?",
                [$paid, max(0, $remaining), $status, $invStatus, $invoiceId]
            );

            // قيد محاسبي للدفعة: الموردون مدين / النقدية دائن
            $cashAcc = AccountingService::accountId('1000');
            $payAcc = AccountingService::accountId('2000');
            if ($payAcc) {
                $lines = [
                    ['account_id' => $payAcc, 'debit' => $amount, 'credit' => 0],
                ];
                if ($cashAcc) {
                    $lines[] = ['account_id' => $cashAcc, 'debit' => 0, 'credit' => $amount];
                }
                AccountingService::post([
                    'entry_date' => date('Y-m-d'),
                    'description' => 'دفعة فاتورة شراء - ' . $invoice['invoice_no'] . ' - ' . $paymentNo,
                    'source_type' => 'payment', 'source_id' => $paymentId,
                    'lines' => $lines,
                ]);
            }

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    /** أرشفة مستند مرتبط بالفاتورة أو PO أو استلام */
    public static function archiveDocument(string $entityType, int $entityId, string $documentType, string $filePath, ?string $notes = null, ?int $userId = null): void
    {
        Database::query(
            "INSERT INTO document_archive (entity_type, entity_id, document_type, file_path, notes, uploaded_by)
             VALUES (?,?,?,?,?,?)",
            [$entityType, $entityId, $documentType, $filePath, $notes, $userId ?? Auth::id()]
        );
    }
}
