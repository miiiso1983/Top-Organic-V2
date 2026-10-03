<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Auth, Helpers, Response, ApprovalService, CostCenterService};

class FinanceController extends Controller
{
    public function cash(): void
    {
        $registers = Database::query(
            "SELECT r.*, b.name AS branch_name FROM cash_registers r
             LEFT JOIN branches b ON b.id = r.branch_id WHERE r.is_active=1"
        )->fetchAll();
        $transactions = Database::query(
            "SELECT t.*, r.name AS register_name, u.full_name FROM cash_transactions t
             JOIN cash_registers r ON r.id = t.register_id
             LEFT JOIN users u ON u.id = t.created_by
             ORDER BY t.id DESC LIMIT 200"
        )->fetchAll();
        $today = Database::query(
            "SELECT COALESCE(SUM(CASE WHEN trx_type='in' THEN amount ELSE 0 END),0) AS cash_in,
                    COALESCE(SUM(CASE WHEN trx_type='out' THEN amount ELSE 0 END),0) AS cash_out
             FROM cash_transactions WHERE DATE(created_at) = CURDATE()"
        )->fetch();

        $this->view('finance/cash', [
            'title' => 'الصندوق النقدي', 'registers' => $registers,
            'transactions' => $transactions, 'today' => $today,
        ]);
    }

    public function storeCashTrx(): void
    {
        $registerId = (int)$this->input('register_id');
        $type   = $this->input('trx_type') === 'out' ? 'out' : 'in';
        $amount = (float)$this->input('amount');

        Database::beginTransaction();
        try {
            Database::query(
                "INSERT INTO cash_transactions (register_id, trx_type, amount, category, reference, description, created_by)
                 VALUES (?,?,?,?,?,?,?)",
                [$registerId, $type, $amount, (string)$this->input('category'),
                 (string)$this->input('reference'), (string)$this->input('description'), Auth::id()]
            );
            $trxId = (int)Database::lastInsertId();
            $delta = $type === 'in' ? $amount : -$amount;
            Database::query("UPDATE cash_registers SET balance = balance + ? WHERE id = ?", [$delta, $registerId]);
            Helpers::queueSync('cash_transactions', $trxId, 'insert', [
                'register_id' => $registerId, 'trx_type' => $type, 'amount' => $amount,
                'category' => (string)$this->input('category'), 'reference' => (string)$this->input('reference'),
            ]);
            Database::commit();
            $this->flash('success', 'تم تسجيل الحركة وتحديث رصيد الصندوق');
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->flash('danger', 'خطأ: ' . $e->getMessage());
        }
        Response::redirect('/finance/cash');
    }

    public function expenses(): void
    {
        $expenses = Database::query(
            "SELECT e.*, cc.name AS cost_center_name, u.full_name FROM expenses e
             LEFT JOIN cost_centers cc ON cc.id = e.cost_center_id
             LEFT JOIN users u ON u.id = e.created_by
             ORDER BY e.id DESC LIMIT 200"
        )->fetchAll();
        $costCenters = CostCenterService::dropdown();
        $monthTotal = Database::query(
            "SELECT COALESCE(SUM(amount),0) AS total FROM expenses
             WHERE status IN ('approved','paid') AND DATE_FORMAT(expense_date,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m')"
        )->fetch();

        $this->view('finance/expenses', [
            'title' => 'المصروفات', 'expenses' => $expenses,
            'costCenters' => $costCenters, 'monthTotal' => $monthTotal,
        ]);
    }

    public function storeExpense(): void
    {
        $no = Helpers::docNumber('EXP', 'expenses', 'expense_no');
        Database::query(
            "INSERT INTO expenses (expense_no, category, amount, cost_center_id, expense_date, description, created_by)
             VALUES (?,?,?,?,?,?,?)",
            [$no, (string)$this->input('category'), (float)$this->input('amount'),
             $this->input('cost_center_id') ?: null,
             (string)$this->input('expense_date', date('Y-m-d')),
             (string)$this->input('description'), Auth::id()]
        );
        $expenseId = (int)Database::lastInsertId();
        Helpers::queueSync('expenses', $expenseId, 'insert', [
            'expense_no' => $no, 'category' => (string)$this->input('category'),
            'amount' => (float)$this->input('amount'),
        ]);
        // المصروفات تخضع لمسار الموافقات
        ApprovalService::request('expense', $expenseId);
        $this->flash('success', "تم تسجيل المصروف $no وأرسل للموافقة");
        Response::redirect('/finance/expenses');
    }

    public function payments(): void
    {
        $payments = Database::query(
            "SELECT p.*, u.full_name FROM payments p
             LEFT JOIN users u ON u.id = p.created_by
             ORDER BY p.id DESC LIMIT 200"
        )->fetchAll();
        $suppliers = Database::query("SELECT id, name, balance FROM suppliers WHERE is_active=1 ORDER BY name")->fetchAll();
        $customers = Database::query("SELECT id, name, balance FROM customers ORDER BY name")->fetchAll();
        $employees = Database::query("SELECT id, full_name FROM employees WHERE file_status='open' ORDER BY full_name")->fetchAll();

        $this->view('finance/payments', [
            'title' => 'الدفع والتحصيل (الذمم)', 'payments' => $payments,
            'suppliers' => $suppliers, 'customers' => $customers, 'employees' => $employees,
        ]);
    }

    public function storePayment(): void
    {
        $no = Helpers::docNumber('PAY', 'payments', 'payment_no');
        $partyType = (string)$this->input('party_type', 'other');
        $partyId   = (int)$this->input('party_id') ?: null;
        $direction = $this->input('direction') === 'in' ? 'in' : 'out';
        $amount    = (float)$this->input('amount');

        Database::beginTransaction();
        try {
            Database::query(
                "INSERT INTO payments (payment_no, party_type, party_id, direction, amount, method, reference, description, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?)",
                [$no, $partyType, $partyId, $direction, $amount,
                 (string)$this->input('method', 'cash'), (string)$this->input('reference'),
                 (string)$this->input('description'), Auth::id()]
            );
            // تحديث رصيد الطرف: دفعة للمورد تخفض ذمته، تحصيل من عميل يخفض ذمته
            if ($partyType === 'supplier' && $partyId && $direction === 'out') {
                Database::query("UPDATE suppliers SET balance = balance - ? WHERE id = ?", [$amount, $partyId]);
            } elseif ($partyType === 'customer' && $partyId && $direction === 'in') {
                Database::query("UPDATE customers SET balance = balance - ? WHERE id = ?", [$amount, $partyId]);
            }
            Helpers::queueSync('payments', (int)Database::lastInsertId(), 'insert', [
                'payment_no' => $no, 'party_type' => $partyType, 'party_id' => $partyId,
                'direction' => $direction, 'amount' => $amount,
            ]);
            Database::commit();
            $this->flash('success', "تم تسجيل السند $no");
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->flash('danger', 'خطأ: ' . $e->getMessage());
        }
        Response::redirect('/finance/payments');
    }

    public function accounts(): void
    {
        $accounts = Database::query(
            "SELECT a.*,
                    COALESCE((SELECT SUM(l.debit) FROM journal_lines l WHERE l.account_id = a.id),0) AS total_debit,
                    COALESCE((SELECT SUM(l.credit) FROM journal_lines l WHERE l.account_id = a.id),0) AS total_credit
             FROM accounts a WHERE a.is_active=1 ORDER BY a.code"
        )->fetchAll();
        $entries = Database::query(
            "SELECT je.*, cc.name AS cost_center_name FROM journal_entries je
             LEFT JOIN cost_centers cc ON cc.id = je.cost_center_id
             ORDER BY je.id DESC LIMIT 50"
        )->fetchAll();
        // ملخص مالي سريع: مبيعات الشهر، مصروفات الشهر، صافي
        $sales = Database::query(
            "SELECT COALESCE(SUM(total),0) AS t FROM orders
             WHERE status NOT IN ('cancelled') AND DATE_FORMAT(created_at,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m')"
        )->fetch();
        $expenses = Database::query(
            "SELECT COALESCE(SUM(amount),0) AS t FROM expenses
             WHERE status IN ('approved','paid') AND DATE_FORMAT(expense_date,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m')"
        )->fetch();

        $this->view('finance/accounts', [
            'title' => 'الحسابات العامة', 'accounts' => $accounts, 'entries' => $entries,
            'monthSales' => (float)$sales['t'], 'monthExpenses' => (float)$expenses['t'],
        ]);
    }
}
