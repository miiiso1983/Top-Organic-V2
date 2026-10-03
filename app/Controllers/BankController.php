<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Auth, Response, Helpers, BankService};

class BankController extends Controller
{
    public function index(): void
    {
        $cash = BankService::cashRegisters();
        $banks = BankService::bankAccounts();
        $totalCash = array_sum(array_column($cash, 'balance'));
        $totalBank = array_sum(array_column($banks, 'current_balance'));
        $this->view('finance/banking/index', [
            'title' => 'البنوك والصندوق',
            'cashRegisters' => $cash, 'bankAccounts' => $banks,
            'totalCash' => $totalCash, 'totalBank' => $totalBank
        ]);
    }

    public function cashRegister(int $id): void
    {
        $register = BankService::cashRegister($id);
        if (!$register) { Response::abort(404); }
        $transactions = Database::query(
            "SELECT t.*, u.full_name FROM cash_transactions t
             LEFT JOIN users u ON u.id = t.created_by
             WHERE t.register_id = ? ORDER BY t.id DESC LIMIT 200",
            [$id]
        )->fetchAll();
        $this->view('finance/banking/cash_register', ['title' => 'صندوق: ' . $register['name'], 'register' => $register, 'transactions' => $transactions]);
    }

    public function storeCashRegister(): void
    {
        try {
            BankService::createCashRegister($_POST);
            $this->flash('success', 'تم إنشاء الصندوق');
        } catch (\Throwable $e) { $this->flash('danger', $e->getMessage()); }
        Response::redirect('/finance/banking');
    }

    public function updateCashRegister(int $id): void
    {
        try {
            BankService::updateCashRegister($id, $_POST);
            $this->flash('success', 'تم تحديث الصندوق');
        } catch (\Throwable $e) { $this->flash('danger', $e->getMessage()); }
        Response::redirect('/finance/banking');
    }

    public function deleteCashRegister(int $id): void
    {
        try {
            BankService::deleteCashRegister($id);
            $this->flash('success', 'تم حذف الصندوق');
        } catch (\Throwable $e) { $this->flash('danger', $e->getMessage()); }
        Response::redirect('/finance/banking');
    }

    public function bankAccount(int $id): void
    {
        $account = BankService::bankAccount($id);
        if (!$account) { Response::abort(404); }
        $transactions = BankService::bankTransactions($id);
        $this->view('finance/banking/bank_account', ['title' => 'حساب بنكي: ' . $account['name'], 'account' => $account, 'transactions' => $transactions]);
    }

    public function storeBankAccount(): void
    {
        try {
            BankService::createBankAccount($_POST);
            $this->flash('success', 'تم إنشاء الحساب البنكي');
        } catch (\Throwable $e) { $this->flash('danger', $e->getMessage()); }
        Response::redirect('/finance/banking');
    }

    public function updateBankAccount(int $id): void
    {
        try {
            BankService::updateBankAccount($id, $_POST);
            $this->flash('success', 'تم تحديث الحساب البنكي');
        } catch (\Throwable $e) { $this->flash('danger', $e->getMessage()); }
        Response::redirect('/finance/banking');
    }

    public function deleteBankAccount(int $id): void
    {
        try {
            BankService::deleteBankAccount($id);
            $this->flash('success', 'تم حذف الحساب البنكي');
        } catch (\Throwable $e) { $this->flash('danger', $e->getMessage()); }
        Response::redirect('/finance/banking');
    }

    public function storeDeposit(): void
    {
        try {
            BankService::deposit((int)$this->input('bank_account_id'), (float)$this->input('amount'), (string)$this->input('date', date('Y-m-d')), (string)$this->input('reference'), (string)$this->input('description'), (int)Auth::id());
            $this->flash('success', 'تم تسجيل الإيداع');
        } catch (\Throwable $e) { $this->flash('danger', $e->getMessage()); }
        Response::redirect('/finance/banking/banks/' . (int)$this->input('bank_account_id'));
    }

    public function storeWithdrawal(): void
    {
        try {
            BankService::withdraw((int)$this->input('bank_account_id'), (float)$this->input('amount'), (string)$this->input('date', date('Y-m-d')), (string)$this->input('reference'), (string)$this->input('description'), (int)Auth::id());
            $this->flash('success', 'تم تسجيل السحب');
        } catch (\Throwable $e) { $this->flash('danger', $e->getMessage()); }
        Response::redirect('/finance/banking/banks/' . (int)$this->input('bank_account_id'));
    }

    public function transfers(): void
    {
        $this->view('finance/banking/transfers', [
            'title' => 'التحويلات',
            'transfers' => BankService::transfers(),
            'cashRegisters' => BankService::cashRegisters(),
            'bankAccounts' => BankService::bankAccounts()
        ]);
    }

    public function storeTransfer(): void
    {
        try {
            BankService::createTransfer($_POST, (int)Auth::id());
            $this->flash('success', 'تم تسجيل التحويل');
        } catch (\Throwable $e) { $this->flash('danger', $e->getMessage()); }
        Response::redirect('/finance/banking/transfers');
    }

    public function checks(): void
    {
        $type = (string)$this->input('type', 'incoming');
        if (!in_array($type, ['incoming', 'outgoing'], true)) $type = 'incoming';
        $this->view('finance/banking/checks', [
            'title' => 'الشيكات ' . ($type === 'incoming' ? 'الواردة' : 'الصادرة'),
            'type' => $type,
            'checks' => BankService::checks($type),
            'bankAccounts' => BankService::bankAccounts(),
            'customers' => Database::query("SELECT id, name FROM customers ORDER BY name")->fetchAll(),
            'suppliers' => Database::query("SELECT id, name FROM suppliers WHERE is_active=1 ORDER BY name")->fetchAll()
        ]);
    }

    public function storeCheck(): void
    {
        try {
            BankService::createCheck($_POST, (int)Auth::id());
            $this->flash('success', 'تم تسجيل الشيك');
        } catch (\Throwable $e) { $this->flash('danger', $e->getMessage()); }
        Response::redirect('/finance/banking/checks?type=' . urlencode((string)$this->input('check_type', 'incoming')));
    }

    public function clearCheck(int $id): void
    {
        try {
            BankService::clearCheck($id, (string)$this->input('date', date('Y-m-d')), (int)Auth::id());
            $this->flash('success', 'تم تحصيل الشيك');
        } catch (\Throwable $e) { $this->flash('danger', $e->getMessage()); }
        Response::redirect('/finance/banking/checks?type=' . urlencode((string)$this->input('type', 'incoming')));
    }

    public function bounceCheck(int $id): void
    {
        try {
            BankService::bounceCheck($id, (string)$this->input('date', date('Y-m-d')), (int)Auth::id());
            $this->flash('success', 'تم تسجيل ارتداد الشيك');
        } catch (\Throwable $e) { $this->flash('danger', $e->getMessage()); }
        Response::redirect('/finance/banking/checks?type=' . urlencode((string)$this->input('type', 'incoming')));
    }

    public function reconciliations(): void
    {
        $bankId = (int)$this->input('bank_account_id', 0);
        $this->view('finance/banking/reconciliations', [
            'title' => 'التسويات البنكية',
            'bankId' => $bankId,
            'bankAccounts' => BankService::bankAccounts(),
            'reconciliations' => $bankId ? BankService::reconciliations($bankId) : []
        ]);
    }

    public function storeReconciliation(): void
    {
        try {
            BankService::createReconciliation($_POST, (int)Auth::id());
            $this->flash('success', 'تم إنشاء التسوية');
        } catch (\Throwable $e) { $this->flash('danger', $e->getMessage()); }
        Response::redirect('/finance/banking/reconciliations?bank_account_id=' . (int)$this->input('bank_account_id'));
    }

    public function cashAdvances(): void
    {
        $this->view('finance/banking/cash_advances', [
            'title' => 'السلف النقدية',
            'advances' => BankService::cashAdvances(),
            'employees' => Database::query("SELECT id, full_name FROM employees WHERE file_status='open' ORDER BY full_name")->fetchAll(),
            'cashRegisters' => BankService::cashRegisters(),
            'bankAccounts' => BankService::bankAccounts()
        ]);
    }

    public function storeCashAdvance(): void
    {
        try {
            BankService::createCashAdvance($_POST, (int)Auth::id());
            $this->flash('success', 'تم تسجيل السلف');
        } catch (\Throwable $e) { $this->flash('danger', $e->getMessage()); }
        Response::redirect('/finance/banking/cash-advances');
    }

    public function approveCashAdvance(int $id): void
    {
        try {
            BankService::approveCashAdvance($id, (int)Auth::id());
            $this->flash('success', 'تم اعتماد السلف');
        } catch (\Throwable $e) { $this->flash('danger', $e->getMessage()); }
        Response::redirect('/finance/banking/cash-advances');
    }

    public function settleCashAdvance(int $id): void
    {
        try {
            BankService::settleCashAdvance($id, (float)$this->input('amount'), (string)$this->input('date', date('Y-m-d')), (int)Auth::id());
            $this->flash('success', 'تم تسوية جزء من السلف');
        } catch (\Throwable $e) { $this->flash('danger', $e->getMessage()); }
        Response::redirect('/finance/banking/cash-advances');
    }

    public function custodies(): void
    {
        $this->view('finance/banking/custodies', [
            'title' => 'العهدة المالية',
            'custodies' => BankService::custodies(),
            'employees' => Database::query("SELECT id, full_name FROM employees WHERE file_status='open' ORDER BY full_name")->fetchAll(),
            'cashRegisters' => BankService::cashRegisters(),
            'bankAccounts' => BankService::bankAccounts()
        ]);
    }

    public function storeCustody(): void
    {
        try {
            BankService::createCustody($_POST, (int)Auth::id());
            $this->flash('success', 'تم إصدار العهدة');
        } catch (\Throwable $e) { $this->flash('danger', $e->getMessage()); }
        Response::redirect('/finance/banking/custodies');
    }

    public function addCustodyExpense(): void
    {
        try {
            $custodyId = (int)$this->input('custody_id');
            BankService::addCustodyExpense($custodyId, $_POST, (int)Auth::id());
            $this->flash('success', 'تم تسجيل مصروف العهدة');
        } catch (\Throwable $e) { $this->flash('danger', $e->getMessage()); }
        Response::redirect('/finance/banking/custodies');
    }

    public function returnCustody(int $id): void
    {
        try {
            BankService::returnCustody($id, (float)$this->input('amount'), (string)$this->input('date', date('Y-m-d')), (int)Auth::id());
            $this->flash('success', 'تم تسجيل المبلغ المردود');
        } catch (\Throwable $e) { $this->flash('danger', $e->getMessage()); }
        Response::redirect('/finance/banking/custodies');
    }

    public function settleCustody(int $id): void
    {
        try {
            BankService::settleCustody($id);
            $this->flash('success', 'تم إقفال العهدة');
        } catch (\Throwable $e) { $this->flash('danger', $e->getMessage()); }
        Response::redirect('/finance/banking/custodies');
    }
}

