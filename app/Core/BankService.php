<?php
namespace App\Core;

/**
 * خدمة البنوك والصندوق: حسابات بنكية، صناديق، تحويلات، شيكات، سلف، عهد
 */
class BankService
{
    private static function cashAccountId(): int
    {
        return (int)(AccountingService::accountId('1000') ?: 0);
    }

    private static function bankAccountId(): int
    {
        return (int)(AccountingService::accountId('1010') ?: 0);
    }

    private static function checkPendingAccountId(): int
    {
        return (int)(AccountingService::accountId('1050') ?: 0);
    }

    private static function checkOutgoingAccountId(): int
    {
        return (int)(AccountingService::accountId('2050') ?: 0);
    }

    private static function advanceAccountId(): int
    {
        return (int)(AccountingService::accountId('1060') ?: 0);
    }

    private static function custodyAccountId(): int
    {
        return (int)(AccountingService::accountId('1070') ?: 0);
    }

    private static function expenseAccountId(): int
    {
        return (int)(AccountingService::accountId('5200') ?: 0);
    }

    private static function arAccountId(): int
    {
        return (int)(AccountingService::accountId('1110') ?: 0);
    }

    private static function apAccountId(): int
    {
        return (int)(AccountingService::accountId('2000') ?: 0);
    }

    /* ============== الصناديق ============== */
    public static function cashRegisters(): array
    {
        return Database::query(
            "SELECT r.*, b.name AS branch_name, u.full_name AS responsible_name
             FROM cash_registers r
             LEFT JOIN branches b ON b.id = r.branch_id
             LEFT JOIN users u ON u.id = r.responsible_user_id
             ORDER BY r.name"
        )->fetchAll();
    }

    public static function cashRegister(int $id): ?array
    {
        $row = Database::query(
            "SELECT r.*, b.name AS branch_name, u.full_name AS responsible_name
             FROM cash_registers r
             LEFT JOIN branches b ON b.id = r.branch_id
             LEFT JOIN users u ON u.id = r.responsible_user_id
             WHERE r.id = ?",
            [$id]
        )->fetch();
        return $row ?: null;
    }

    public static function createCashRegister(array $data): int
    {
        Database::query(
            "INSERT INTO cash_registers (branch_id, name, account_id, responsible_user_id, balance, is_active)
             VALUES (?,?,?,?,0,1)",
            [$data['branch_id'] ?? null, $data['name'], $data['account_id'] ?? null, $data['responsible_user_id'] ?? null]
        );
        return (int)Database::lastInsertId();
    }

    public static function updateCashRegister(int $id, array $data): void
    {
        Database::query(
            "UPDATE cash_registers SET name=?, branch_id=?, account_id=?, responsible_user_id=?, is_active=? WHERE id=?",
            [$data['name'], $data['branch_id'] ?? null, $data['account_id'] ?? null, $data['responsible_user_id'] ?? null, $data['is_active'] ?? 1, $id]
        );
    }

    public static function deleteCashRegister(int $id): void
    {
        Database::query("DELETE FROM cash_registers WHERE id = ?", [$id]);
    }

    /* ============== الحسابات البنكية ============== */
    public static function bankAccounts(): array
    {
        return Database::query("SELECT * FROM bank_accounts ORDER BY name")->fetchAll();
    }

    public static function bankAccount(int $id): ?array
    {
        $row = Database::query("SELECT * FROM bank_accounts WHERE id = ?", [$id])->fetch();
        return $row ?: null;
    }

    public static function createBankAccount(array $data): int
    {
        Database::query(
            "INSERT INTO bank_accounts (name, bank_name, account_no, iban, branch, currency, opening_balance, current_balance, account_id, cost_center_id, is_active)
             VALUES (?,?,?,?,?,?,?,?,?,?,1)",
            [$data['name'], $data['bank_name'], $data['account_no'], $data['iban'] ?? null, $data['branch'] ?? null,
             $data['currency'] ?? 'SAR', $data['opening_balance'] ?? 0, $data['opening_balance'] ?? 0,
             $data['account_id'] ?? null, $data['cost_center_id'] ?? null]
        );
        return (int)Database::lastInsertId();
    }

    public static function updateBankAccount(int $id, array $data): void
    {
        Database::query(
            "UPDATE bank_accounts SET name=?, bank_name=?, account_no=?, iban=?, branch=?, currency=?,
                opening_balance=?, current_balance=?, account_id=?, cost_center_id=?, is_active=?
             WHERE id=?",
            [$data['name'], $data['bank_name'], $data['account_no'], $data['iban'] ?? null, $data['branch'] ?? null,
             $data['currency'] ?? 'SAR', $data['opening_balance'] ?? 0, $data['current_balance'] ?? 0,
             $data['account_id'] ?? null, $data['cost_center_id'] ?? null, $data['is_active'] ?? 1, $id]
        );
    }

    public static function deleteBankAccount(int $id): void
    {
        Database::query("DELETE FROM bank_accounts WHERE id = ?", [$id]);
    }

    public static function bankTransactions(int $bankId): array
    {
        return Database::query(
            "SELECT t.*, u.full_name FROM bank_transactions t
             LEFT JOIN users u ON u.id = t.created_by
             WHERE t.bank_account_id = ? ORDER BY t.id DESC LIMIT 200",
            [$bankId]
        )->fetchAll();
    }

    /* ============== الإيداعات والسحوبات ============== */
    public static function deposit(int $bankId, float $amount, string $date, string $reference, string $description, int $userId): int
    {
        if ($amount <= 0) throw new \RuntimeException('المبلغ يجب أن يكون أكبر من صفر');
        Database::beginTransaction();
        try {
            Database::query(
                "INSERT INTO bank_transactions (bank_account_id, trx_type, amount, reference, description, trx_date, created_by)
                 VALUES (?,?,?,?,?,?,?)",
                [$bankId, 'deposit', $amount, $reference, $description, $date, $userId]
            );
            $trxId = (int)Database::lastInsertId();
            Database::query("UPDATE bank_accounts SET current_balance = current_balance + ? WHERE id = ?", [$amount, $bankId]);
            self::postJournal($date, 'إيداع بنكي', [
                ['account_id' => self::bankAccountId(), 'debit' => $amount, 'credit' => 0],
                ['account_id' => self::cashAccountId(), 'debit' => 0, 'credit' => $amount]
            ], 'bank_transaction', $trxId);
            Database::commit();
            return $trxId;
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public static function withdraw(int $bankId, float $amount, string $date, string $reference, string $description, int $userId): int
    {
        if ($amount <= 0) throw new \RuntimeException('المبلغ يجب أن يكون أكبر من صفر');
        Database::beginTransaction();
        try {
            Database::query(
                "INSERT INTO bank_transactions (bank_account_id, trx_type, amount, reference, description, trx_date, created_by)
                 VALUES (?,?,?,?,?,?,?)",
                [$bankId, 'withdrawal', $amount, $reference, $description, $date, $userId]
            );
            $trxId = (int)Database::lastInsertId();
            Database::query("UPDATE bank_accounts SET current_balance = current_balance - ? WHERE id = ?", [$amount, $bankId]);
            self::postJournal($date, 'سحب بنكي', [
                ['account_id' => self::cashAccountId(), 'debit' => $amount, 'credit' => 0],
                ['account_id' => self::bankAccountId(), 'debit' => 0, 'credit' => $amount]
            ], 'bank_transaction', $trxId);
            Database::commit();
            return $trxId;
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    private static function postJournal(string $date, string $description, array $lines, string $sourceType, int $sourceId): void
    {
        $filtered = array_filter($lines, fn($l) => !empty($l['account_id']) && ($l['debit'] > 0 || $l['credit'] > 0));
        if (count($filtered) < 2) return;
        AccountingService::post(['entry_date' => $date, 'description' => $description, 'source_type' => $sourceType, 'source_id' => $sourceId, 'lines' => array_values($filtered)]);
    }

    /* ============== التحويلات ============== */
    public static function transfers(): array
    {
        return Database::query(
            "SELECT tr.*, u.full_name, fc.name AS from_cash, fb.name AS from_bank, tc.name AS to_cash, tb.name AS to_bank
             FROM bank_transfers tr
             LEFT JOIN cash_registers fc ON fc.id = IF(tr.from_type='cash', tr.from_id, NULL)
             LEFT JOIN bank_accounts fb ON fb.id = IF(tr.from_type='bank', tr.from_id, NULL)
             LEFT JOIN cash_registers tc ON tc.id = IF(tr.to_type='cash', tr.to_id, NULL)
             LEFT JOIN bank_accounts tb ON tb.id = IF(tr.to_type='bank', tr.to_id, NULL)
             LEFT JOIN users u ON u.id = tr.created_by
             ORDER BY tr.id DESC LIMIT 200"
        )->fetchAll();
    }

    public static function createTransfer(array $data, int $userId): int
    {
        $amount = (float)$data['amount'];
        if ($amount <= 0) throw new \RuntimeException('المبلغ يجب أن يكون أكبر من صفر');
        $fromType = $data['from_type'];
        $fromId = (int)$data['from_id'];
        $toType = $data['to_type'];
        $toId = (int)$data['to_id'];
        $date = $data['transfer_date'];
        Database::beginTransaction();
        try {
            Database::query(
                "INSERT INTO bank_transfers (from_type, from_id, to_type, to_id, amount, transfer_date, reference, description, status, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?)",
                [$fromType, $fromId, $toType, $toId, $amount, $date, $data['reference'] ?? '', $data['description'] ?? '', 'completed', $userId]
            );
            $id = (int)Database::lastInsertId();
            self::adjustBalance($fromType, $fromId, -$amount);
            self::adjustBalance($toType, $toId, $amount);
            $lines = self::transferJournalLines($fromType, $fromId, $toType, $toId, $amount);
            self::postJournal($date, 'تحويل نقدي/بنكي', $lines, 'bank_transfer', $id);
            Database::commit();
            return $id;
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    private static function adjustBalance(string $type, int $id, float $amount): void
    {
        if ($type === 'cash') {
            Database::query("UPDATE cash_registers SET balance = balance + ? WHERE id = ?", [$amount, $id]);
        } else {
            Database::query("UPDATE bank_accounts SET current_balance = current_balance + ? WHERE id = ?", [$amount, $id]);
        }
    }

    private static function transferJournalLines(string $fromType, int $fromId, string $toType, int $toId, float $amount): array
    {
        $fromAcc = $fromType === 'cash' ? self::cashAccountId() : (int)(self::bankAccountIdById($fromId) ?: self::bankAccountId());
        $toAcc = $toType === 'cash' ? self::cashAccountId() : (int)(self::bankAccountIdById($toId) ?: self::bankAccountId());
        return [['account_id' => $toAcc, 'debit' => $amount, 'credit' => 0], ['account_id' => $fromAcc, 'debit' => 0, 'credit' => $amount]];
    }

    private static function bankAccountIdById(int $id): int
    {
        $row = Database::query("SELECT account_id FROM bank_accounts WHERE id = ?", [$id])->fetch();
        return (int)($row['account_id'] ?? 0);
    }

    /* ============== الشيكات ============== */
    public static function checks(string $type): array
    {
        return Database::query(
            "SELECT c.*, u.full_name, b.name AS bank_name FROM checks c
             LEFT JOIN users u ON u.id = c.created_by
             LEFT JOIN bank_accounts b ON b.id = c.bank_account_id
             WHERE c.check_type = ? ORDER BY c.id DESC LIMIT 200",
            [$type]
        )->fetchAll();
    }

    public static function createCheck(array $data, int $userId): int
    {
        $amount = (float)$data['amount'];
        if ($amount <= 0) throw new \RuntimeException('المبلغ يجب أن يكون أكبر من صفر');
        $type = $data['check_type'];
        $date = $data['check_date'];
        Database::beginTransaction();
        try {
            Database::query(
                "INSERT INTO checks (check_no, check_type, party_type, party_id, bank_account_id, amount, check_date, due_date, status, reference, description, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
                [$data['check_no'], $type, $data['party_type'] ?? null, $data['party_id'] ?? null,
                 $data['bank_account_id'] ?? null, $amount, $date, $data['due_date'] ?? null, 'pending',
                 $data['reference'] ?? '', $data['description'] ?? '', $userId]
            );
            $id = (int)Database::lastInsertId();
            if ($type === 'incoming') {
                self::postJournal($date, 'شيك وارد - ' . $data['check_no'], [
                    ['account_id' => self::checkPendingAccountId(), 'debit' => $amount, 'credit' => 0],
                    ['account_id' => self::partyArAccountId($data['party_type']), 'debit' => 0, 'credit' => $amount]
                ], 'check', $id);
            } else {
                self::postJournal($date, 'شيك صادر - ' . $data['check_no'], [
                    ['account_id' => self::partyApAccountId($data['party_type']), 'debit' => $amount, 'credit' => 0],
                    ['account_id' => self::checkOutgoingAccountId(), 'debit' => 0, 'credit' => $amount]
                ], 'check', $id);
            }
            Database::commit();
            return $id;
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    private static function partyArAccountId(?string $partyType): int
    {
        return $partyType === 'customer' ? self::arAccountId() : self::checkPendingAccountId();
    }

    private static function partyApAccountId(?string $partyType): int
    {
        return $partyType === 'supplier' ? self::apAccountId() : self::checkOutgoingAccountId();
    }

    public static function clearCheck(int $id, string $date, int $userId): void
    {
        Database::beginTransaction();
        try {
            $check = Database::query("SELECT * FROM checks WHERE id = ?", [$id])->fetch();
            if (!$check) throw new \RuntimeException('الشيك غير موجود');
            $amount = (float)$check['amount'];
            Database::query("UPDATE checks SET status = 'cleared', cleared_date = ? WHERE id = ?", [$date, $id]);
            if ($check['check_type'] === 'incoming') {
                self::postJournal($date, 'تحصيل شيك وارد - ' . $check['check_no'], [
                    ['account_id' => self::bankAccountId(), 'debit' => $amount, 'credit' => 0],
                    ['account_id' => self::checkPendingAccountId(), 'debit' => 0, 'credit' => $amount]
                ], 'check', $id);
                if ($check['bank_account_id']) {
                    Database::query("UPDATE bank_accounts SET current_balance = current_balance + ? WHERE id = ?", [$amount, $check['bank_account_id']]);
                }
            } else {
                self::postJournal($date, 'صرف شيك صادر - ' . $check['check_no'], [
                    ['account_id' => self::checkOutgoingAccountId(), 'debit' => $amount, 'credit' => 0],
                    ['account_id' => self::bankAccountId(), 'debit' => 0, 'credit' => $amount]
                ], 'check', $id);
                if ($check['bank_account_id']) {
                    Database::query("UPDATE bank_accounts SET current_balance = current_balance - ? WHERE id = ?", [$amount, $check['bank_account_id']]);
                }
            }
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public static function bounceCheck(int $id, string $date, int $userId): void
    {
        Database::beginTransaction();
        try {
            $check = Database::query("SELECT * FROM checks WHERE id = ?", [$id])->fetch();
            if (!$check) throw new \RuntimeException('الشيك غير موجود');
            $amount = (float)$check['amount'];
            Database::query("UPDATE checks SET status = 'bounced' WHERE id = ?", [$id]);
            if ($check['check_type'] === 'incoming') {
                self::postJournal($date, 'شيك وارد مرتد - ' . $check['check_no'], [
                    ['account_id' => self::partyArAccountId($check['party_type']), 'debit' => $amount, 'credit' => 0],
                    ['account_id' => self::checkPendingAccountId(), 'debit' => 0, 'credit' => $amount]
                ], 'check', $id);
            } else {
                self::postJournal($date, 'شيك صادر مرتد - ' . $check['check_no'], [
                    ['account_id' => self::checkOutgoingAccountId(), 'debit' => $amount, 'credit' => 0],
                    ['account_id' => self::partyApAccountId($check['party_type']), 'debit' => 0, 'credit' => $amount]
                ], 'check', $id);
            }
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    /* ============== التسويات البنكية ============== */
    public static function reconciliations(int $bankId): array
    {
        return Database::query(
            "SELECT r.*, u.full_name, b.name AS bank_name
             FROM bank_reconciliations r
             JOIN bank_accounts b ON b.id = r.bank_account_id
             LEFT JOIN users u ON u.id = r.created_by
             WHERE r.bank_account_id = ? ORDER BY r.id DESC",
            [$bankId]
        )->fetchAll();
    }

    public static function createReconciliation(array $data, int $userId): int
    {
        $bankId = (int)$data['bank_account_id'];
        $stmt = $data['statement_date'];
        $stmtBal = (float)$data['statement_balance'];
        $sysBal = (float)Database::query("SELECT current_balance FROM bank_accounts WHERE id = ?", [$bankId])->fetch()['current_balance'];
        Database::beginTransaction();
        try {
            Database::query(
                "INSERT INTO bank_reconciliations (bank_account_id, statement_date, statement_balance, system_balance, difference, status, notes, created_by)
                 VALUES (?,?,?,?,?,?,?,?)",
                [$bankId, $stmt, $stmtBal, $sysBal, $stmtBal - $sysBal, 'open', $data['notes'] ?? '', $userId]
            );
            $id = (int)Database::lastInsertId();
            Database::commit();
            return $id;
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public static function addReconciliationItem(int $reconId, array $data): int
    {
        Database::query(
            "INSERT INTO bank_reconciliation_items (reconciliation_id, trx_type, amount, description, is_resolved)
             VALUES (?,?,?,?,?)",
            [$reconId, $data['trx_type'], $data['amount'], $data['description'] ?? '', $data['is_resolved'] ?? 0]
        );
        return (int)Database::lastInsertId();
    }

    public static function markReconciled(int $id): void
    {
        Database::query("UPDATE bank_reconciliations SET status = 'reconciled' WHERE id = ?", [$id]);
        AccountingService::postReconciliationAdjustments($id);
    }

    /* ============== السلف النقدية ============== */
    public static function cashAdvances(): array
    {
        return Database::query(
            "SELECT a.*, e.full_name AS employee_name, c.name AS cash_name, b.name AS bank_name, u.full_name
             FROM cash_advances a
             JOIN employees e ON e.id = a.employee_id
             LEFT JOIN cash_registers c ON c.id = a.cash_register_id
             LEFT JOIN bank_accounts b ON b.id = a.bank_account_id
             LEFT JOIN users u ON u.id = a.created_by
             ORDER BY a.id DESC LIMIT 200"
        )->fetchAll();
    }

    public static function createCashAdvance(array $data, int $userId): int
    {
        $amount = (float)$data['amount'];
        if ($amount <= 0) throw new \RuntimeException('المبلغ يجب أن يكون أكبر من صفر');
        Database::query(
            "INSERT INTO cash_advances (employee_id, amount, advance_date, purpose, status, payment_method, cash_register_id, bank_account_id, created_by)
             VALUES (?,?,?,?,?,?,?,?,?)",
            [$data['employee_id'], $amount, $data['advance_date'], $data['purpose'] ?? '', 'pending', $data['payment_method'] ?? 'cash', $data['cash_register_id'] ?? null, $data['bank_account_id'] ?? null, $userId]
        );
        return (int)Database::lastInsertId();
    }

    public static function approveCashAdvance(int $id, int $userId): void
    {
        Database::beginTransaction();
        try {
            $adv = Database::query("SELECT * FROM cash_advances WHERE id = ?", [$id])->fetch();
            if (!$adv) throw new \RuntimeException('السلف غير موجود');
            Database::query("UPDATE cash_advances SET status = 'approved' WHERE id = ?", [$id]);
            $amount = (float)$adv['amount'];
            if ($adv['payment_method'] === 'cash' && $adv['cash_register_id']) {
                Database::query("UPDATE cash_registers SET balance = balance - ? WHERE id = ?", [$amount, $adv['cash_register_id']]);
                self::postJournal($adv['advance_date'], 'إصدار سلف نقدية', [
                    ['account_id' => self::advanceAccountId(), 'debit' => $amount, 'credit' => 0],
                    ['account_id' => self::cashAccountId(), 'debit' => 0, 'credit' => $amount]
                ], 'cash_advance', $id);
            } elseif ($adv['payment_method'] === 'bank' && $adv['bank_account_id']) {
                Database::query("UPDATE bank_accounts SET current_balance = current_balance - ? WHERE id = ?", [$amount, $adv['bank_account_id']]);
                self::postJournal($adv['advance_date'], 'إصدار سلف نقدية', [
                    ['account_id' => self::advanceAccountId(), 'debit' => $amount, 'credit' => 0],
                    ['account_id' => self::bankAccountId(), 'debit' => 0, 'credit' => $amount]
                ], 'cash_advance', $id);
            }
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public static function settleCashAdvance(int $id, float $amount, string $date, int $userId): void
    {
        if ($amount <= 0) throw new \RuntimeException('المبلغ يجب أن يكون أكبر من صفر');
        Database::beginTransaction();
        try {
            $adv = Database::query("SELECT * FROM cash_advances WHERE id = ?", [$id])->fetch();
            if (!$adv) throw new \RuntimeException('السلف غير موجود');
            $newSettled = (float)$adv['settled_amount'] + $amount;
            $status = $newSettled >= (float)$adv['amount'] ? 'settled' : 'approved';
            Database::query("UPDATE cash_advances SET settled_amount = ?, status = ? WHERE id = ?", [$newSettled, $status, $id]);
            self::postJournal($date, 'تسوية سلف نقدية', [
                ['account_id' => self::expenseAccountId(), 'debit' => $amount, 'credit' => 0],
                ['account_id' => self::advanceAccountId(), 'debit' => 0, 'credit' => $amount]
            ], 'cash_advance', $id);
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    /* ============== العهد المالية ============== */
    public static function custodies(): array
    {
        return Database::query(
            "SELECT c.*, e.full_name AS employee_name, cr.name AS cash_name, b.name AS bank_name, u.full_name
             FROM financial_custodies c
             JOIN employees e ON e.id = c.employee_id
             LEFT JOIN cash_registers cr ON cr.id = c.cash_register_id
             LEFT JOIN bank_accounts b ON b.id = c.bank_account_id
             LEFT JOIN users u ON u.id = c.created_by
             ORDER BY c.id DESC LIMIT 200"
        )->fetchAll();
    }

    public static function createCustody(array $data, int $userId): int
    {
        $amount = (float)$data['amount'];
        if ($amount <= 0) throw new \RuntimeException('المبلغ يجب أن يكون أكبر من صفر');
        Database::beginTransaction();
        try {
            Database::query(
                "INSERT INTO financial_custodies (employee_id, amount, custody_date, purpose, status, cash_register_id, bank_account_id, created_by)
                 VALUES (?,?,?,?,?,?,?,?)",
                [$data['employee_id'], $amount, $data['custody_date'], $data['purpose'] ?? '', 'active', $data['cash_register_id'] ?? null, $data['bank_account_id'] ?? null, $userId]
            );
            $id = (int)Database::lastInsertId();
            if ($data['cash_register_id']) {
                Database::query("UPDATE cash_registers SET balance = balance - ? WHERE id = ?", [$amount, $data['cash_register_id']]);
                self::postJournal($data['custody_date'], 'إصدار عهدة مالية', [
                    ['account_id' => self::custodyAccountId(), 'debit' => $amount, 'credit' => 0],
                    ['account_id' => self::cashAccountId(), 'debit' => 0, 'credit' => $amount]
                ], 'financial_custody', $id);
            } elseif ($data['bank_account_id']) {
                Database::query("UPDATE bank_accounts SET current_balance = current_balance - ? WHERE id = ?", [$amount, $data['bank_account_id']]);
                self::postJournal($data['custody_date'], 'إصدار عهدة مالية', [
                    ['account_id' => self::custodyAccountId(), 'debit' => $amount, 'credit' => 0],
                    ['account_id' => self::bankAccountId(), 'debit' => 0, 'credit' => $amount]
                ], 'financial_custody', $id);
            }
            Database::commit();
            return $id;
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public static function addCustodyExpense(int $custodyId, array $data, int $userId): int
    {
        $amount = (float)$data['amount'];
        if ($amount <= 0) throw new \RuntimeException('المبلغ يجب أن يكون أكبر من صفر');
        Database::beginTransaction();
        try {
            Database::query(
                "INSERT INTO custody_expenses (custody_id, amount, expense_date, category, description, receipt_no, created_by)
                 VALUES (?,?,?,?,?,?,?)",
                [$custodyId, $amount, $data['expense_date'], $data['category'] ?? '', $data['description'] ?? '', $data['receipt_no'] ?? '', $userId]
            );
            $id = (int)Database::lastInsertId();
            Database::query("UPDATE financial_custodies SET spent_amount = spent_amount + ? WHERE id = ?", [$amount, $custodyId]);
            self::postJournal($data['expense_date'], 'مصروف عهدة', [
                ['account_id' => self::expenseAccountId(), 'debit' => $amount, 'credit' => 0],
                ['account_id' => self::custodyAccountId(), 'debit' => 0, 'credit' => $amount]
            ], 'custody_expense', $id);
            Database::commit();
            return $id;
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public static function returnCustody(int $id, float $amount, string $date, int $userId): void
    {
        if ($amount <= 0) throw new \RuntimeException('المبلغ يجب أن يكون أكبر من صفر');
        Database::beginTransaction();
        try {
            $custody = Database::query("SELECT * FROM financial_custodies WHERE id = ?", [$id])->fetch();
            if (!$custody) throw new \RuntimeException('العهدة غير موجودة');
            $newReturned = (float)$custody['returned_amount'] + $amount;
            Database::query("UPDATE financial_custodies SET returned_amount = ? WHERE id = ?", [$newReturned, $id]);
            if ($custody['cash_register_id']) {
                Database::query("UPDATE cash_registers SET balance = balance + ? WHERE id = ?", [$amount, $custody['cash_register_id']]);
                self::postJournal($date, 'رد عهدة مالية', [
                    ['account_id' => self::cashAccountId(), 'debit' => $amount, 'credit' => 0],
                    ['account_id' => self::custodyAccountId(), 'debit' => 0, 'credit' => $amount]
                ], 'financial_custody', $id);
            } elseif ($custody['bank_account_id']) {
                Database::query("UPDATE bank_accounts SET current_balance = current_balance + ? WHERE id = ?", [$amount, $custody['bank_account_id']]);
                self::postJournal($date, 'رد عهدة مالية', [
                    ['account_id' => self::bankAccountId(), 'debit' => $amount, 'credit' => 0],
                    ['account_id' => self::custodyAccountId(), 'debit' => 0, 'credit' => $amount]
                ], 'financial_custody', $id);
            }
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public static function settleCustody(int $id): void
    {
        Database::query("UPDATE financial_custodies SET status = 'settled' WHERE id = ?", [$id]);
    }
}

