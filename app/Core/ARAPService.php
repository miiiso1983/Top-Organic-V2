<?php
namespace App\Core;

/**
 * خدمة الحسابات المدينة والدائنة (AR/AP)
 */
class ARAPService
{
    const BUCKET_DAYS = [30, 60, 90, 120];

    public static function customers(): array
    {
        return Database::query("SELECT id, name, phone, balance, account_id FROM customers ORDER BY name")->fetchAll();
    }

    public static function suppliers(): array
    {
        return Database::query("SELECT id, name, phone, balance, account_id FROM suppliers WHERE is_active=1 ORDER BY name")->fetchAll();
    }

    public static function customerBalance(int $customerId): array
    {
        $invoices = (float)Database::query(
            "SELECT COALESCE(SUM(total),0) AS s FROM orders WHERE customer_id = ? AND status NOT IN ('cancelled')",
            [$customerId]
        )->fetch()['s'];
        $payments = (float)Database::query(
            "SELECT COALESCE(SUM(amount),0) AS s FROM payments WHERE party_type='customer' AND party_id = ? AND direction='in'",
            [$customerId]
        )->fetch()['s'];
        $adjustments = (float)Database::query(
            "SELECT COALESCE(SUM(amount),0) AS s FROM arap_adjustments WHERE party_type='customer' AND party_id = ?",
            [$customerId]
        )->fetch()['s'];
        return ['invoiced' => $invoices, 'paid' => $payments, 'adjustments' => $adjustments, 'balance' => max(0, $invoices - $payments - $adjustments)];
    }

    public static function supplierBalance(int $supplierId): array
    {
        $invoices = (float)Database::query(
            "SELECT COALESCE(SUM(total),0) AS s FROM purchase_orders WHERE supplier_id = ? AND status NOT IN ('draft','cancelled')",
            [$supplierId]
        )->fetch()['s'];
        $payments = (float)Database::query(
            "SELECT COALESCE(SUM(amount),0) AS s FROM payments WHERE party_type='supplier' AND party_id = ? AND direction='out'",
            [$supplierId]
        )->fetch()['s'];
        $adjustments = (float)Database::query(
            "SELECT COALESCE(SUM(amount),0) AS s FROM arap_adjustments WHERE party_type='supplier' AND party_id = ?",
            [$supplierId]
        )->fetch()['s'];
        return ['invoiced' => $invoices, 'paid' => $payments, 'adjustments' => $adjustments, 'balance' => max(0, $invoices - $payments - $adjustments)];
    }

    public static function openOrders(string $partyType, int $partyId): array
    {
        if ($partyType === 'customer') {
            return Database::query(
                "SELECT id, order_no AS ref, created_at AS date, total, payment_status,
                        total - COALESCE((SELECT SUM(amount) FROM arap_allocations WHERE order_id = orders.id),0) AS remaining
                 FROM orders WHERE customer_id = ? AND status NOT IN ('cancelled') AND payment_status != 'paid'
                 ORDER BY created_at DESC",
                [$partyId]
            )->fetchAll();
        }
        return Database::query(
            "SELECT id, po_no AS ref, created_at AS date, total, payment_status,
                    total - COALESCE((SELECT SUM(amount) FROM arap_allocations WHERE po_id = purchase_orders.id),0) AS remaining
             FROM purchase_orders WHERE supplier_id = ? AND status NOT IN ('draft','cancelled') AND payment_status != 'paid'
             ORDER BY created_at DESC",
            [$partyId]
        )->fetchAll();
    }

    public static function customerStatement(int $customerId): array
    {
        $customer = Database::query("SELECT id, name, balance FROM customers WHERE id = ?", [$customerId])->fetch();
        if (!$customer) return [];
        $invoices = Database::query(
            "SELECT 'invoice' AS type, order_no AS ref, created_at AS date, total AS amount, payment_status AS status, id
             FROM orders WHERE customer_id = ? AND status NOT IN ('cancelled') ORDER BY created_at DESC",
            [$customerId]
        )->fetchAll();
        $payments = Database::query(
            "SELECT 'payment' AS type, payment_no AS ref, created_at AS date, amount, direction AS status, id
             FROM payments WHERE party_type='customer' AND party_id = ? ORDER BY created_at DESC",
            [$customerId]
        )->fetchAll();
        $adjustments = Database::query(
            "SELECT 'adjustment' AS type, CONCAT(adjustment_type, ' #', id) AS ref, adjustment_date AS date, amount, adjustment_type AS status, id
             FROM arap_adjustments WHERE party_type='customer' AND party_id = ? ORDER BY adjustment_date DESC",
            [$customerId]
        )->fetchAll();
        $lines = array_merge($invoices, $payments, $adjustments);
        usort($lines, fn($a, $b) => strcmp($b['date'], $a['date']) ?: $b['id'] - $a['id']);
        return ['customer' => $customer, 'lines' => $lines, 'balance' => self::customerBalance($customerId)];
    }

    public static function supplierStatement(int $supplierId): array
    {
        $supplier = Database::query("SELECT id, name, balance FROM suppliers WHERE id = ?", [$supplierId])->fetch();
        if (!$supplier) return [];
        $invoices = Database::query(
            "SELECT 'invoice' AS type, po_no AS ref, created_at AS date, total AS amount, payment_status AS status, id
             FROM purchase_orders WHERE supplier_id = ? AND status NOT IN ('draft','cancelled') ORDER BY created_at DESC",
            [$supplierId]
        )->fetchAll();
        $payments = Database::query(
            "SELECT 'payment' AS type, payment_no AS ref, created_at AS date, amount, direction AS status, id
             FROM payments WHERE party_type='supplier' AND party_id = ? ORDER BY created_at DESC",
            [$supplierId]
        )->fetchAll();
        $adjustments = Database::query(
            "SELECT 'adjustment' AS type, CONCAT(adjustment_type, ' #', id) AS ref, adjustment_date AS date, amount, adjustment_type AS status, id
             FROM arap_adjustments WHERE party_type='supplier' AND party_id = ? ORDER BY adjustment_date DESC",
            [$supplierId]
        )->fetchAll();
        $lines = array_merge($invoices, $payments, $adjustments);
        usort($lines, fn($a, $b) => strcmp($b['date'], $a['date']) ?: $b['id'] - $a['id']);
        return ['supplier' => $supplier, 'lines' => $lines, 'balance' => self::supplierBalance($supplierId)];
    }

    public static function aging(string $partyType): array
    {
        $today = date('Y-m-d');
        $table = $partyType === 'customer' ? 'orders' : 'purchase_orders';
        $partyCol = $partyType === 'customer' ? 'customer_id' : 'supplier_id';
        $dateCol = $partyType === 'customer' ? 'created_at' : 'created_at';
        $paidSql = $partyType === 'customer'
            ? "SELECT COALESCE(SUM(amount),0) FROM payments WHERE party_type='customer' AND party_id = o.{$partyCol} AND direction='in'"
            : "SELECT COALESCE(SUM(amount),0) FROM payments WHERE party_type='supplier' AND party_id = o.{$partyCol} AND direction='out'";
        $adjSql = "SELECT COALESCE(SUM(amount),0) FROM arap_adjustments WHERE party_type=? AND party_id = o.{$partyCol}";
        $buckets = [0, 0, 0, 0, 0]; // current, 30, 60, 90, 120+
        $rows = Database::query(
            "SELECT o.{$partyCol} AS party_id, SUM(o.total - ($paidSql) - ($adjSql)) AS open_amount, DATEDIFF(?, DATE(o.{$dateCol})) AS age
             FROM {$table} o
             WHERE o.status NOT IN ('cancelled') AND o.{$partyCol} IS NOT NULL
             GROUP BY o.{$partyCol}, age
             HAVING open_amount > 0",
            [$partyType, $today]
        )->fetchAll();
        foreach ($rows as $r) {
            $age = (int)$r['age'];
            $amt = (float)$r['open_amount'];
            if ($age <= 30) $buckets[0] += $amt;
            elseif ($age <= 60) $buckets[1] += $amt;
            elseif ($age <= 90) $buckets[2] += $amt;
            elseif ($age <= 120) $buckets[3] += $amt;
            else $buckets[4] += $amt;
        }
        return ['buckets' => $buckets, 'total' => array_sum($buckets)];
    }

    public static function agingByParty(string $partyType): array
    {
        $today = date('Y-m-d');
        $partyTable = $partyType === 'customer' ? 'customers' : 'suppliers';
        $invTable = $partyType === 'customer' ? 'orders' : 'purchase_orders';
        $partyCol = $partyType === 'customer' ? 'customer_id' : 'supplier_id';
        $paidSql = $partyType === 'customer'
            ? "SELECT COALESCE(SUM(amount),0) FROM payments WHERE party_type='customer' AND party_id = p.id AND direction='in'"
            : "SELECT COALESCE(SUM(amount),0) FROM payments WHERE party_type='supplier' AND party_id = p.id AND direction='out'";
        $adjSql = "SELECT COALESCE(SUM(amount),0) FROM arap_adjustments WHERE party_type=? AND party_id = p.id";
        return Database::query(
            "SELECT p.id, p.name, p.balance,
                    COALESCE((SELECT SUM(o.total) FROM {$invTable} o WHERE o.{$partyCol} = p.id AND o.status NOT IN ('cancelled')),0) - ({$paidSql}) - ({$adjSql}) AS open_amount
             FROM {$partyTable} p
             HAVING open_amount > 0
             ORDER BY open_amount DESC",
            [$partyType]
        )->fetchAll();
    }

    public static function recordPayment(string $partyType, int $partyId, float $amount, string $date, string $method, string $reference, string $description, int $userId, array $allocations = []): int
    {
        if ($amount <= 0) throw new \RuntimeException('المبلغ يجب أن يكون أكبر من صفر');
        $direction = $partyType === 'customer' ? 'in' : 'out';
        $allocated = array_sum(array_column($allocations, 'amount'));
        if ($allocated > $amount + 0.001) throw new \RuntimeException('مجموع التخصيصات أكبر من مبلغ السند');
        $no = Helpers::docNumber('PAY', 'payments', 'payment_no');
        Database::beginTransaction();
        try {
            Database::query(
                "INSERT INTO payments (payment_no, party_type, party_id, direction, amount, method, reference, description, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?)",
                [$no, $partyType, $partyId, $direction, $amount, $method, $reference, $description, $userId]
            );
            $paymentId = (int)Database::lastInsertId();
            foreach ($allocations as $a) {
                $aAmt = (float)($a['amount'] ?? 0);
                if ($aAmt <= 0) continue;
                $orderId = (int)($a['order_id'] ?? 0) ?: null;
                $poId = (int)($a['po_id'] ?? 0) ?: null;
                Database::query(
                    "INSERT INTO arap_allocations (party_type, party_id, payment_id, order_id, po_id, amount) VALUES (?,?,?,?,?,?)",
                    [$partyType, $partyId, $paymentId, $orderId, $poId, $aAmt]
                );
                self::updateOrderPaymentStatus($partyType, $orderId, $poId);
            }
            self::postPaymentJournal($partyType, $direction, $amount, $date, $no, $paymentId);
            if ($partyType === 'customer') {
                Database::query("UPDATE customers SET balance = balance - ? WHERE id = ?", [$amount, $partyId]);
            } elseif ($partyType === 'supplier') {
                Database::query("UPDATE suppliers SET balance = balance - ? WHERE id = ?", [$amount, $partyId]);
            }
            Database::commit();
            return $paymentId;
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    private static function updateOrderPaymentStatus(string $partyType, ?int $orderId, ?int $poId): void
    {
        if ($partyType === 'customer' && $orderId) {
            $total = (float)Database::query("SELECT total FROM orders WHERE id = ?", [$orderId])->fetch()['total'];
            $paid = (float)Database::query("SELECT COALESCE(SUM(amount),0) AS s FROM arap_allocations WHERE order_id = ?", [$orderId])->fetch()['s'];
            $status = $paid >= $total ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');
            Database::query("UPDATE orders SET payment_status = ? WHERE id = ?", [$status, $orderId]);
        } elseif ($partyType === 'supplier' && $poId) {
            $total = (float)Database::query("SELECT total FROM purchase_orders WHERE id = ?", [$poId])->fetch()['total'];
            $paid = (float)Database::query("SELECT COALESCE(SUM(amount),0) AS s FROM arap_allocations WHERE po_id = ?", [$poId])->fetch()['s'];
            $status = $paid >= $total ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');
            Database::query("UPDATE purchase_orders SET payment_status = ? WHERE id = ?", [$status, $poId]);
        }
    }

    public static function recordAdjustment(string $partyType, int $partyId, float $amount, string $date, string $adjustmentType, string $description, int $userId): int
    {
        if ($amount <= 0) throw new \RuntimeException('المبلغ يجب أن يكون أكبر من صفر');
        Database::beginTransaction();
        try {
            Database::query(
                "INSERT INTO arap_adjustments (party_type, party_id, adjustment_date, amount, adjustment_type, description, created_by)
                 VALUES (?,?,?,?,?,?,?)",
                [$partyType, $partyId, $date, $amount, $adjustmentType, $description, $userId]
            );
            $adjId = (int)Database::lastInsertId();
            self::postAdjustmentJournal($partyType, $amount, $date, $adjustmentType, $adjId);
            if ($partyType === 'customer') {
                Database::query("UPDATE customers SET balance = balance - ? WHERE id = ?", [$amount, $partyId]);
            } elseif ($partyType === 'supplier') {
                Database::query("UPDATE suppliers SET balance = balance - ? WHERE id = ?", [$amount, $partyId]);
            }
            Database::commit();
            return $adjId;
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    private static function postPaymentJournal(string $partyType, string $direction, float $amount, string $date, string $no, int $paymentId): void
    {
        $cashAcc = AccountingService::accountId('1000') ?: AccountingService::accountId('1010');
        $arAcc = AccountingService::accountId('1110');
        $apAcc = AccountingService::accountId('2000');
        if (!$cashAcc) return;
        if ($partyType === 'customer' && $arAcc) {
            $lines = $direction === 'in'
                ? [['account_id' => $cashAcc, 'debit' => $amount, 'credit' => 0], ['account_id' => $arAcc, 'debit' => 0, 'credit' => $amount]]
                : [['account_id' => $arAcc, 'debit' => $amount, 'credit' => 0], ['account_id' => $cashAcc, 'debit' => 0, 'credit' => $amount]];
        } elseif ($partyType === 'supplier' && $apAcc) {
            $lines = $direction === 'out'
                ? [['account_id' => $apAcc, 'debit' => $amount, 'credit' => 0], ['account_id' => $cashAcc, 'debit' => 0, 'credit' => $amount]]
                : [['account_id' => $cashAcc, 'debit' => $amount, 'credit' => 0], ['account_id' => $apAcc, 'debit' => 0, 'credit' => $amount]];
        } else { return; }
        AccountingService::post(['entry_date' => $date, 'description' => 'سند دفع/تحصيل - ' . $no, 'source_type' => 'payment', 'source_id' => $paymentId, 'lines' => $lines]);
    }

    private static function postAdjustmentJournal(string $partyType, float $amount, string $date, string $type, int $adjId): void
    {
        $arAcc = AccountingService::accountId('1110');
        $apAcc = AccountingService::accountId('2000');
        $expAcc = AccountingService::accountId('5210') ?: AccountingService::accountId('5200');
        $revAcc = AccountingService::accountId('4100') ?: AccountingService::accountId('4000');
        if ($partyType === 'customer' && $arAcc && $expAcc) {
            $lines = [['account_id' => $expAcc, 'debit' => $amount, 'credit' => 0], ['account_id' => $arAcc, 'debit' => 0, 'credit' => $amount]];
        } elseif ($partyType === 'supplier' && $apAcc && $revAcc) {
            $lines = [['account_id' => $apAcc, 'debit' => $amount, 'credit' => 0], ['account_id' => $revAcc, 'debit' => 0, 'credit' => $amount]];
        } else { return; }
        $entryId = AccountingService::post(['entry_date' => $date, 'description' => 'تسوية ذمم - ' . $type, 'source_type' => 'arap_adjustment', 'source_id' => $adjId, 'lines' => $lines]);
        Database::query("UPDATE arap_adjustments SET journal_entry_id = ? WHERE id = ?", [$entryId, $adjId]);
    }

    public static function overdue(string $partyType, int $days = 30): array
    {
        if ($partyType === 'customer') {
            $rows = Database::query(
                "SELECT o.id, o.order_no AS ref, o.created_at AS date, o.total,
                        c.id AS party_id, c.name AS party_name, c.phone AS party_phone,
                        DATEDIFF(CURDATE(), o.created_at) AS overdue_days,
                        o.total - COALESCE((SELECT SUM(amount) FROM arap_allocations WHERE order_id = o.id),0) AS remaining
                 FROM orders o
                 JOIN customers c ON c.id = o.customer_id
                 WHERE o.status NOT IN ('cancelled') AND o.payment_status != 'paid'
                   AND DATEDIFF(CURDATE(), o.created_at) >= ?
                 ORDER BY overdue_days DESC",
                [$days]
            )->fetchAll();
        } else {
            $rows = Database::query(
                "SELECT po.id, po.po_no AS ref, po.created_at AS date, po.total,
                        s.id AS party_id, s.name AS party_name, s.phone AS party_phone,
                        DATEDIFF(CURDATE(), po.created_at) AS overdue_days,
                        po.total - COALESCE((SELECT SUM(amount) FROM arap_allocations WHERE po_id = po.id),0) AS remaining
                 FROM purchase_orders po
                 JOIN suppliers s ON s.id = po.supplier_id
                 WHERE po.status NOT IN ('draft','cancelled') AND po.payment_status != 'paid'
                   AND DATEDIFF(CURDATE(), po.created_at) >= ?
                 ORDER BY overdue_days DESC",
                [$days]
            )->fetchAll();
        }
        $total = 0.0;
        foreach ($rows as $r) $total += (float)$r['remaining'];
        return ['rows' => $rows, 'total' => $total, 'count' => count($rows)];
    }
}
