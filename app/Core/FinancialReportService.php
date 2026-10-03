<?php
namespace App\Core;

/**
 * خدمة التقارير المالية
 */
class FinancialReportService
{
    /* ============== لوحة المؤشرات المالية ============== */
    public static function dashboard(): array
    {
        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');
        $cash = (float)Database::query("SELECT COALESCE(SUM(balance),0) FROM cash_registers WHERE is_active=1")->fetchColumn();
        $bank = (float)Database::query("SELECT COALESCE(SUM(current_balance),0) FROM bank_accounts WHERE is_active=1")->fetchColumn();
        $ar = (float)Database::query(
            "SELECT COALESCE(SUM(o.total),0) - COALESCE(SUM(p.amount),0) FROM orders o
             LEFT JOIN (SELECT order_id, SUM(amount) AS amount FROM order_payments GROUP BY order_id) p ON p.order_id = o.id
             WHERE o.status NOT IN ('cancelled') AND o.payment_status != 'paid'"
        )->fetchColumn();
        $ap = (float)Database::query(
            "SELECT COALESCE(SUM(po.total),0) - COALESCE(SUM(p.amount),0) FROM purchase_orders po
             LEFT JOIN (SELECT po_id, SUM(amount) AS amount FROM arap_allocations WHERE po_id IS NOT NULL GROUP BY po_id) p ON p.po_id = po.id
             WHERE po.status NOT IN ('cancelled') AND po.payment_status != 'paid'"
        )->fetchColumn();
        $monthRevenue = (float)Database::query(
            "SELECT COALESCE(SUM(total),0) FROM orders WHERE status NOT IN ('cancelled') AND DATE(created_at) BETWEEN ? AND ?",
            [$monthStart, $today]
        )->fetchColumn();
        $monthExpenses = (float)Database::query(
            "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE status IN ('approved','paid') AND DATE(expense_date) BETWEEN ? AND ?",
            [$monthStart, $today]
        )->fetchColumn();
        $dailyRevenue = Database::query(
            "SELECT DATE(created_at) AS d, COALESCE(SUM(total),0) AS total FROM orders
             WHERE status NOT IN ('cancelled') AND DATE(created_at) >= DATE_SUB(?, INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY d",
            [$today]
        )->fetchAll();
        return [
            'cash' => $cash, 'bank' => $bank, 'ar' => $ar, 'ap' => $ap,
            'monthRevenue' => $monthRevenue, 'monthExpenses' => $monthExpenses,
            'netProfit' => $monthRevenue - $monthExpenses,
            'dailyRevenue' => $dailyRevenue
        ];
    }

    /* ============== ميزان المراجعة ============== */
    public static function trialBalance(string $from, string $to): array
    {
        $accounts = Database::query("SELECT id, code, name, type, opening_balance FROM accounts WHERE is_active=1 ORDER BY code")->fetchAll();
        foreach ($accounts as &$a) {
            $op = (float)$a['opening_balance'];
            $rows = Database::query(
                "SELECT COALESCE(SUM(debit),0) AS dr, COALESCE(SUM(credit),0) AS cr
                 FROM journal_lines l JOIN journal_entries e ON e.id = l.entry_id
                 WHERE l.account_id = ? AND e.status = 'posted' AND e.entry_date <= ?",
                [$a['id'], $to]
            )->fetch();
            $dr = (float)$rows['dr']; $cr = (float)$rows['cr'];
            $isDebit = in_array($a['type'], AccountingService::DEBIT_NORMAL, true);
            $bal = $isDebit ? $op + $dr - $cr : $op + $cr - $dr;
            $a['opening'] = $op;
            $a['debit'] = $dr;
            $a['credit'] = $cr;
            $a['balance'] = $bal;
        }
        unset($a);
        return $accounts;
    }

    /* ============== قائمة الدخل ============== */
    public static function incomeStatement(string $from, string $to): array
    {
        $revenue = (float)Database::query(
            "SELECT COALESCE(SUM(l.credit),0) - COALESCE(SUM(l.debit),0) AS s
             FROM journal_lines l JOIN journal_entries e ON e.id = l.entry_id
             JOIN accounts a ON a.id = l.account_id
             WHERE a.type = 'revenue' AND e.status = 'posted' AND e.entry_date BETWEEN ? AND ?",
            [$from, $to]
        )->fetchColumn();
        $expenses = (float)Database::query(
            "SELECT COALESCE(SUM(l.debit),0) - COALESCE(SUM(l.credit),0) AS s
             FROM journal_lines l JOIN journal_entries e ON e.id = l.entry_id
             JOIN accounts a ON a.id = l.account_id
             WHERE a.type = 'expense' AND e.status = 'posted' AND e.entry_date BETWEEN ? AND ?",
            [$from, $to]
        )->fetchColumn();
        $cogs = (float)Database::query(
            "SELECT COALESCE(SUM(l.debit),0) - COALESCE(SUM(l.credit),0) AS s
             FROM journal_lines l JOIN journal_entries e ON e.id = l.entry_id
             JOIN accounts a ON a.id = l.account_id
             WHERE a.code LIKE '5000' AND e.status = 'posted' AND e.entry_date BETWEEN ? AND ?",
            [$from, $to]
        )->fetchColumn();
        return ['revenue' => $revenue, 'cogs' => $cogs, 'expenses' => $expenses, 'netIncome' => $revenue - $cogs - $expenses];
    }

    /* ============== الميزانية العمومية ============== */
    public static function balanceSheet(string $to): array
    {
        $assets = (float)Database::query(
            "SELECT COALESCE(SUM(l.debit),0) - COALESCE(SUM(l.credit),0) AS s
             FROM journal_lines l JOIN journal_entries e ON e.id = l.entry_id
             JOIN accounts a ON a.id = l.account_id
             WHERE a.type = 'asset' AND e.status = 'posted' AND e.entry_date <= ?",
            [$to]
        )->fetchColumn();
        $liabilities = (float)Database::query(
            "SELECT COALESCE(SUM(l.credit),0) - COALESCE(SUM(l.debit),0) AS s
             FROM journal_lines l JOIN journal_entries e ON e.id = l.entry_id
             JOIN accounts a ON a.id = l.account_id
             WHERE a.type = 'liability' AND e.status = 'posted' AND e.entry_date <= ?",
            [$to]
        )->fetchColumn();
        $equity = (float)Database::query(
            "SELECT COALESCE(SUM(l.credit),0) - COALESCE(SUM(l.debit),0) AS s
             FROM journal_lines l JOIN journal_entries e ON e.id = l.entry_id
             JOIN accounts a ON a.id = l.account_id
             WHERE a.type = 'equity' AND e.status = 'posted' AND e.entry_date <= ?",
            [$to]
        )->fetchColumn();
        return ['assets' => $assets, 'liabilities' => $liabilities, 'equity' => $equity, 'totalLiabilitiesEquity' => $liabilities + $equity];
    }

    /* ============== التدفقات النقدية ============== */
    public static function cashFlow(string $from, string $to): array
    {
        $cashIn = (float)Database::query(
            "SELECT COALESCE(SUM(amount),0) FROM cash_transactions WHERE trx_type='in' AND DATE(created_at) BETWEEN ? AND ?",
            [$from, $to]
        )->fetchColumn();
        $cashOut = (float)Database::query(
            "SELECT COALESCE(SUM(amount),0) FROM cash_transactions WHERE trx_type='out' AND DATE(created_at) BETWEEN ? AND ?",
            [$from, $to]
        )->fetchColumn();
        $bankIn = (float)Database::query(
            "SELECT COALESCE(SUM(amount),0) FROM bank_transactions WHERE trx_type IN ('deposit','transfer_in','check_in') AND DATE(trx_date) BETWEEN ? AND ?",
            [$from, $to]
        )->fetchColumn();
        $bankOut = (float)Database::query(
            "SELECT COALESCE(SUM(amount),0) FROM bank_transactions WHERE trx_type IN ('withdrawal','transfer_out','check_out') AND DATE(trx_date) BETWEEN ? AND ?",
            [$from, $to]
        )->fetchColumn();
        return [
            'cashIn' => $cashIn, 'cashOut' => $cashOut, 'netCash' => $cashIn - $cashOut,
            'bankIn' => $bankIn, 'bankOut' => $bankOut, 'netBank' => $bankIn - $bankOut,
            'netTotal' => ($cashIn + $bankIn) - ($cashOut + $bankOut)
        ];
    }

    /* ============== الأستاذ العام ============== */
    public static function generalLedger(int $accountId, string $from, string $to): array
    {
        $account = Database::query("SELECT * FROM accounts WHERE id = ?", [$accountId])->fetch();
        if (!$account) return ['account' => null, 'lines' => []];
        $op = (float)$account['opening_balance'];
        $prior = Database::query(
            "SELECT COALESCE(SUM(debit),0) AS dr, COALESCE(SUM(credit),0) AS cr
             FROM journal_lines l JOIN journal_entries e ON e.id = l.entry_id
             WHERE l.account_id = ? AND e.status = 'posted' AND e.entry_date < ?",
            [$accountId, $from]
        )->fetch();
        $isDebit = in_array($account['type'], AccountingService::DEBIT_NORMAL, true);
        $opening = $isDebit ? $op + (float)$prior['dr'] - (float)$prior['cr'] : $op + (float)$prior['cr'] - (float)$prior['dr'];
        $lines = Database::query(
            "SELECT e.entry_no, e.entry_date, e.description, l.debit, l.credit
             FROM journal_lines l JOIN journal_entries e ON e.id = l.entry_id
             WHERE l.account_id = ? AND e.status = 'posted' AND e.entry_date BETWEEN ? AND ?
             ORDER BY e.entry_date, e.id",
            [$accountId, $from, $to]
        )->fetchAll();
        $balance = $opening;
        foreach ($lines as &$l) {
            $balance = $isDebit ? $balance + (float)$l['debit'] - (float)$l['credit'] : $balance + (float)$l['credit'] - (float)$l['debit'];
            $l['balance'] = $balance;
        }
        unset($l);
        return ['account' => $account, 'opening' => $opening, 'lines' => $lines];
    }

    /* ============== كشف حساب بنكي ============== */
    public static function bankStatement(int $bankId, string $from, string $to): array
    {
        $account = Database::query("SELECT * FROM bank_accounts WHERE id = ?", [$bankId])->fetch();
        if (!$account) return ['account' => null, 'lines' => []];
        $opening = (float)Database::query(
            "SELECT COALESCE(SUM(CASE WHEN trx_type IN ('deposit','transfer_in','check_in','interest') THEN amount ELSE -amount END),0) AS s
             FROM bank_transactions WHERE bank_account_id = ? AND trx_date < ?",
            [$bankId, $from]
        )->fetchColumn() + (float)$account['opening_balance'];
        $lines = Database::query(
            "SELECT * FROM bank_transactions WHERE bank_account_id = ? AND trx_date BETWEEN ? AND ? ORDER BY trx_date, id",
            [$bankId, $from, $to]
        )->fetchAll();
        $balance = $opening;
        foreach ($lines as &$l) {
            $delta = in_array($l['trx_type'], ['deposit','transfer_in','check_in','interest']) ? (float)$l['amount'] : -(float)$l['amount'];
            $balance += $delta;
            $l['balance'] = $balance;
        }
        unset($l);
        return ['account' => $account, 'opening' => $opening, 'lines' => $lines];
    }

    /* ============== كشف صندوق ============== */
    public static function cashStatement(int $registerId, string $from, string $to): array
    {
        $register = Database::query("SELECT * FROM cash_registers WHERE id = ?", [$registerId])->fetch();
        if (!$register) return ['register' => null, 'lines' => []];
        $opening = (float)Database::query(
            "SELECT COALESCE(SUM(CASE WHEN trx_type='in' THEN amount ELSE -amount END),0) FROM cash_transactions WHERE register_id = ? AND DATE(created_at) < ?",
            [$registerId, $from]
        )->fetchColumn() + (float)$register['balance'];
        $lines = Database::query(
            "SELECT * FROM cash_transactions WHERE register_id = ? AND DATE(created_at) BETWEEN ? AND ? ORDER BY created_at",
            [$registerId, $from, $to]
        )->fetchAll();
        $balance = $opening;
        foreach ($lines as &$l) {
            $balance += $l['trx_type'] === 'in' ? (float)$l['amount'] : -(float)$l['amount'];
            $l['balance'] = $balance;
        }
        unset($l);
        return ['register' => $register, 'opening' => $opening, 'lines' => $lines];
    }

    /* ============== ميزان مراجعة تفصيلي ============== */
    public static function detailedTrialBalance(string $from, string $to): array
    {
        $accounts = self::trialBalance($from, $to);
        $lines = Database::query(
            "SELECT a.code, a.name, a.type, e.entry_date, e.entry_no, e.description, l.debit, l.credit
             FROM journal_lines l
             JOIN journal_entries e ON e.id = l.entry_id
             JOIN accounts a ON a.id = l.account_id
             WHERE e.status = 'posted' AND e.entry_date BETWEEN ? AND ?
             ORDER BY a.code, e.entry_date",
            [$from, $to]
        )->fetchAll();
        return ['accounts' => $accounts, 'lines' => $lines];
    }

    /* ============== الأستاذ المساعد (عميل/مورد) ============== */
    public static function subledger(string $partyType, int $partyId, string $from, string $to): array
    {
        if ($partyType === 'customer') {
            $party = Database::query("SELECT * FROM customers WHERE id = ?", [$partyId])->fetch();
            $invTable = 'orders'; $partyCol = 'customer_id'; $statusNot = "'cancelled'";
        } else {
            $party = Database::query("SELECT * FROM suppliers WHERE id = ?", [$partyId])->fetch();
            $invTable = 'purchase_orders'; $partyCol = 'supplier_id'; $statusNot = "'draft','cancelled'";
        }
        if (!$party) return ['party' => null, 'lines' => []];
        $invoices = Database::query(
            "SELECT id, created_at AS date, total, 'invoice' AS type FROM {$invTable}
             WHERE {$partyCol} = ? AND status NOT IN ({$statusNot}) AND DATE(created_at) BETWEEN ? AND ?",
            [$partyId, $from, $to]
        )->fetchAll();
        $payments = Database::query(
            "SELECT id, created_at AS date, amount, 'payment' AS type FROM payments
             WHERE party_type = ? AND party_id = ? AND DATE(created_at) BETWEEN ? AND ?",
            [$partyType, $partyId, $from, $to]
        )->fetchAll();
        $adjustments = Database::query(
            "SELECT id, adjustment_date AS date, amount, 'adjustment' AS type FROM arap_adjustments
             WHERE party_type = ? AND party_id = ? AND DATE(adjustment_date) BETWEEN ? AND ?",
            [$partyType, $partyId, $from, $to]
        )->fetchAll();
        $lines = array_merge($invoices, $payments, $adjustments);
        usort($lines, fn($a, $b) => strcmp($a['date'], $b['date']));
        return ['party' => $party, 'lines' => $lines];
    }

    /* ============== تقرير المصروفات ============== */
    public static function expensesReport(string $from, string $to, ?int $costCenterId = null): array
    {
        $params = [$from, $to];
        $ccSql = '';
        if ($costCenterId) { $ccSql = 'AND e.cost_center_id = ?'; $params[] = $costCenterId; }
        return Database::query(
            "SELECT e.*, cc.name AS cost_center_name, u.full_name
             FROM expenses e
             LEFT JOIN cost_centers cc ON cc.id = e.cost_center_id
             LEFT JOIN users u ON u.id = e.created_by
             WHERE e.status IN ('approved','paid') AND DATE(e.expense_date) BETWEEN ? AND ? {$ccSql}
             ORDER BY e.expense_date DESC",
            $params
        )->fetchAll();
    }

    /* ============== تقرير الإيرادات ============== */
    public static function revenueReport(string $from, string $to, ?int $costCenterId = null): array
    {
        $params = [$from, $to];
        $ccSql = '';
        if ($costCenterId) { $ccSql = 'AND o.cost_center_id = ?'; $params[] = $costCenterId; }
        return Database::query(
            "SELECT o.id, o.order_no, o.created_at, o.total, o.order_type, c.name AS customer_name, cc.name AS cost_center_name
             FROM orders o
             LEFT JOIN customers c ON c.id = o.customer_id
             LEFT JOIN cost_centers cc ON cc.id = o.cost_center_id
             WHERE o.status NOT IN ('cancelled') AND DATE(o.created_at) BETWEEN ? AND ? {$ccSql}
             ORDER BY o.created_at DESC",
            $params
        )->fetchAll();
    }

    /* ============== تقرير الذمم المدينة ============== */
    public static function arReport(string $to): array
    {
        return Database::query(
            "SELECT c.id, c.name, c.phone, COALESCE(SUM(o.total),0) AS invoiced,
                    COALESCE(SUM(p.amount),0) AS paid,
                    COALESCE(SUM(o.total),0) - COALESCE(SUM(p.amount),0) - COALESCE(SUM(a.amount),0) AS balance
             FROM customers c
             LEFT JOIN orders o ON o.customer_id = c.id AND o.status NOT IN ('cancelled')
             LEFT JOIN (SELECT order_id, SUM(amount) AS amount FROM order_payments GROUP BY order_id) p ON p.order_id = o.id
             LEFT JOIN (SELECT party_id, SUM(amount) AS amount FROM arap_adjustments WHERE party_type='customer' GROUP BY party_id) a ON a.party_id = c.id
             GROUP BY c.id, c.name, c.phone
             HAVING balance > 0
             ORDER BY balance DESC"
        )->fetchAll();
    }

    /* ============== تقرير الذمم الدائنة ============== */
    public static function apReport(string $to): array
    {
        return Database::query(
            "SELECT s.id, s.name, s.phone, COALESCE(SUM(po.total),0) AS invoiced,
                    COALESCE(SUM(al.amount),0) AS paid,
                    COALESCE(SUM(po.total),0) - COALESCE(SUM(al.amount),0) - COALESCE(SUM(a.amount),0) AS balance
             FROM suppliers s
             LEFT JOIN purchase_orders po ON po.supplier_id = s.id AND po.status NOT IN ('draft','cancelled')
             LEFT JOIN (SELECT po_id, SUM(amount) AS amount FROM arap_allocations WHERE po_id IS NOT NULL GROUP BY po_id) al ON al.po_id = po.id
             LEFT JOIN (SELECT party_id, SUM(amount) AS amount FROM arap_adjustments WHERE party_type='supplier' GROUP BY party_id) a ON a.party_id = s.id
             WHERE s.is_active = 1
             GROUP BY s.id, s.name, s.phone
             HAVING balance > 0
             ORDER BY balance DESC"
        )->fetchAll();
    }

    /* ============== تقرير الأصول ============== */
    public static function assetsReport(): array
    {
        return Database::query(
            "SELECT fa.*, c.name AS category_name, b.name AS branch_name
             FROM fixed_assets fa
             LEFT JOIN asset_categories c ON c.id = fa.category_id
             LEFT JOIN branches b ON b.id = fa.branch_id
             ORDER BY fa.id DESC"
        )->fetchAll();
    }

    /* ============== تقرير الإهلاكات ============== */
    public static function depreciationReport(string $from, string $to): array
    {
        return Database::query(
            "SELECT de.*, fa.name AS asset_name, fa.asset_no
             FROM depreciation_entries de
             JOIN fixed_assets fa ON fa.id = de.asset_id
             WHERE DATE(de.created_at) BETWEEN ? AND ?
             ORDER BY de.created_at DESC",
            [$from, $to]
        )->fetchAll();
    }

    /* ============== تقرير مراكز الكلفة ============== */
    public static function costCenterReport(string $from, string $to): array
    {
        $centers = CostCenterService::dropdownWithCode();
        foreach ($centers as &$c) {
            $prof = CostCenterService::profitability((int)$c['id'], $from, $to);
            $c['revenue'] = $prof['revenue'];
            $c['expenses'] = $prof['expenses'];
            $c['profit'] = $prof['profit'];
        }
        unset($c);
        return $centers;
    }

    /* ============== تقرير استهلاك المواد ============== */
    public static function materialConsumption(string $from, string $to): array
    {
        return Database::query(
            "SELECT i.name AS item_name, u.name AS unit_name,
                    COALESCE(SUM(pc.qty),0) AS consumed_qty,
                    COALESCE(SUM(pc.qty * i.cost_price),0) AS consumed_cost
             FROM production_consumption pc
             JOIN production_orders po ON po.id = pc.production_id
             JOIN items i ON i.id = pc.item_id
             LEFT JOIN units u ON u.id = i.unit_id
             WHERE po.status != 'cancelled' AND DATE(po.production_date) BETWEEN ? AND ?
             GROUP BY i.id, i.name, u.name
             ORDER BY consumed_cost DESC",
            [$from, $to]
        )->fetchAll();
    }

    /* ============== الربحية حسب الفرع ============== */
    public static function profitabilityByBranch(string $from, string $to): array
    {
        return Database::query(
            "SELECT b.id, b.name,
                    COALESCE(SUM(o.total),0) AS revenue,
                    0 AS expenses
             FROM branches b
             LEFT JOIN orders o ON o.branch_id = b.id AND o.status NOT IN ('cancelled') AND DATE(o.created_at) BETWEEN ? AND ?
             GROUP BY b.id, b.name
             ORDER BY revenue DESC",
            [$from, $to]
        )->fetchAll();
    }

    /* ============== الربحية حسب القسم/المنتج/الوصفة (إطار عام) ============== */
    public static function profitabilityByProduct(string $from, string $to): array
    {
        return Database::query(
            "SELECT mi.name, SUM(oi.qty) AS qty, SUM(oi.total) AS revenue,
                    SUM(oi.qty * mi.cost) AS cost
             FROM order_items oi
             JOIN orders o ON o.id = oi.order_id
             JOIN menu_items mi ON mi.id = oi.menu_item_id
             WHERE o.status NOT IN ('cancelled') AND DATE(o.created_at) BETWEEN ? AND ?
             GROUP BY mi.id, mi.name
             ORDER BY revenue DESC",
            [$from, $to]
        )->fetchAll();
    }
}

