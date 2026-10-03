<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Auth, Helpers, Response, AccountingService, ExportHelper, ImportHelper, MaterialConsumptionService, CostCenterService};

class AccountingController extends Controller
{
    public function index(): void
    {
        $assets = AccountingService::balance((int)AccountingService::accountId('1'));
        $liab = AccountingService::balance((int)AccountingService::accountId('2'));
        $equity = AccountingService::balance((int)AccountingService::accountId('3'));
        $revenue = AccountingService::balance((int)AccountingService::accountId('4'));
        $expenses = AccountingService::balance((int)AccountingService::accountId('5'));
        $entries = Database::query(
            "SELECT je.*, cc.name AS cost_center_name FROM journal_entries je
             LEFT JOIN cost_centers cc ON cc.id = je.cost_center_id
             ORDER BY je.id DESC LIMIT 10"
        )->fetchAll();
        $this->view('accounting/index', [
            'title' => 'المحاسبة',
            'assets' => $assets, 'liabilities' => $liab, 'equity' => $equity,
            'revenue' => $revenue, 'expenses' => $expenses,
            'entries' => $entries,
        ]);
    }

    public function accounts(): void
    {
        $search = trim((string)$this->input('search', ''));
        $params = [];
        $where = "a.is_active = 1";
        if ($search !== '') {
            $where .= " AND (a.name LIKE ? OR a.code LIKE ?)";
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }
        $accounts = Database::query(
            "SELECT a.*, p.name AS parent_name, p.code AS parent_code FROM accounts a
             LEFT JOIN accounts p ON p.id = a.parent_id
             WHERE $where ORDER BY a.code LIMIT 2000",
            $params
        )->fetchAll();
        $parents = Database::query("SELECT id, code, name FROM accounts WHERE is_leaf = 0 AND is_active = 1 ORDER BY code")->fetchAll();
        $this->view('accounting/accounts', [
            'title' => 'شجرة الحسابات',
            'accounts' => $accounts, 'parents' => $parents, 'search' => $search,
        ]);
    }

    public function storeAccount(): void
    {
        $name = trim((string)$this->input('name'));
        $type = (string)$this->input('type');
        $parentId = (int)$this->input('parent_id');
        if (!$name || !in_array($type, ['asset','liability','equity','revenue','expense'], true)) {
            $this->flash('danger', 'بيانات الحساب غير صالحة');
            Response::redirect('/accounting/accounts');
        }
        try {
            $code = AccountingService::generateCode($parentId);
            Database::query(
                "INSERT INTO accounts (code, name, type, parent_id, account_group, is_leaf, opening_balance)
                 VALUES (?,?,?,?,?,?,?)",
                [$code, $name, $type, $parentId, (string)$this->input('account_group'), 1, (float)$this->input('opening_balance', 0)]
            );
            Database::query("UPDATE accounts SET is_leaf = 0 WHERE id = ?", [$parentId]);
            $this->flash('success', "تم إنشاء الحساب $code");
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        Response::redirect('/accounting/accounts');
    }

    public function editAccount(string $id): void
    {
        $account = Database::query("SELECT * FROM accounts WHERE id = ? AND is_active = 1", [(int)$id])->fetch();
        if (!$account) {
            $this->flash('danger', 'الحساب غير موجود');
            Response::redirect('/accounting/accounts');
        }
        $parents = Database::query(
            "SELECT id, code, name FROM accounts WHERE is_leaf = 0 AND is_active = 1 AND id != ? ORDER BY code",
            [(int)$id]
        )->fetchAll();
        $this->view('accounting/accounts_edit', [
            'title' => 'تعديل حساب: ' . $account['name'],
            'account' => $account, 'parents' => $parents,
        ]);
    }

    public function updateAccount(string $id): void
    {
        $id = (int)$id;
        $account = Database::query("SELECT * FROM accounts WHERE id = ? AND is_active = 1", [$id])->fetch();
        if (!$account) {
            $this->flash('danger', 'الحساب غير موجود');
            Response::redirect('/accounting/accounts');
        }
        $name = trim((string)$this->input('name'));
        $type = (string)$this->input('type');
        $parentId = (int)$this->input('parent_id');
        if (!$name || !in_array($type, ['asset','liability','equity','revenue','expense'], true)) {
            $this->flash('danger', 'بيانات الحساب غير صالحة');
            Response::redirect('/accounting/accounts/' . $id . '/edit');
        }
        if ($parentId === $id) {
            $this->flash('danger', 'لا يمكن أن يكون الحساب أباً لنفسه');
            Response::redirect('/accounting/accounts/' . $id . '/edit');
        }
        $oldParentId = $account['parent_id'] ? (int)$account['parent_id'] : null;
        Database::beginTransaction();
        try {
            Database::query(
                "UPDATE accounts SET name = ?, type = ?, parent_id = ?, account_group = ?, opening_balance = ? WHERE id = ?",
                [$name, $type, $parentId ?: null, (string)$this->input('account_group'), (float)$this->input('opening_balance', 0), $id]
            );
            if ($parentId) {
                Database::query("UPDATE accounts SET is_leaf = 0 WHERE id = ?", [$parentId]);
            }
            if ($oldParentId && $oldParentId !== $parentId) {
                $hasChildren = (int) Database::query(
                    "SELECT COUNT(*) AS c FROM accounts WHERE parent_id = ? AND is_active = 1",
                    [$oldParentId]
                )->fetch()['c'];
                if ($hasChildren === 0) {
                    Database::query("UPDATE accounts SET is_leaf = 1 WHERE id = ?", [$oldParentId]);
                }
            }
            Database::commit();
            $this->flash('success', 'تم تحديث الحساب');
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->flash('danger', $e->getMessage());
        }
        Response::redirect('/accounting/accounts');
    }

    public function deleteAccount(string $id): void
    {
        $id = (int)$id;
        $account = Database::query("SELECT * FROM accounts WHERE id = ? AND is_active = 1", [$id])->fetch();
        if (!$account) {
            $this->flash('danger', 'الحساب غير موجود');
            Response::redirect('/accounting/accounts');
        }
        $children = (int) Database::query(
            "SELECT COUNT(*) AS c FROM accounts WHERE parent_id = ? AND is_active = 1",
            [$id]
        )->fetch()['c'];
        $lines = (int) Database::query(
            "SELECT COUNT(*) AS c FROM journal_lines WHERE account_id = ?",
            [$id]
        )->fetch()['c'];
        if ($children > 0 || $lines > 0) {
            $this->flash('danger', 'لا يمكن حذف الحساب لأنه يحتوي على حسابات فرعية أو حركات');
            Response::redirect('/accounting/accounts');
        }
        Database::beginTransaction();
        try {
            Database::query("UPDATE accounts SET is_active = 0 WHERE id = ?", [$id]);
            $parentId = $account['parent_id'] ? (int)$account['parent_id'] : null;
            if ($parentId) {
                $hasChildren = (int) Database::query(
                    "SELECT COUNT(*) AS c FROM accounts WHERE parent_id = ? AND is_active = 1",
                    [$parentId]
                )->fetch()['c'];
                if ($hasChildren === 0) {
                    Database::query("UPDATE accounts SET is_leaf = 1 WHERE id = ?", [$parentId]);
                }
            }
            Database::commit();
            $this->flash('success', 'تم حذف الحساب');
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->flash('danger', $e->getMessage());
        }
        Response::redirect('/accounting/accounts');
    }

    public function journal(): void
    {
        $entries = Database::query(
            "SELECT je.*, cc.name AS cost_center_name, u.full_name FROM journal_entries je
             LEFT JOIN cost_centers cc ON cc.id = je.cost_center_id
             LEFT JOIN users u ON u.id = je.created_by
             ORDER BY je.id DESC LIMIT 100"
        )->fetchAll();
        $accounts = Database::query("SELECT id, code, name FROM accounts WHERE is_leaf = 1 AND is_active = 1 ORDER BY code")->fetchAll();
        $costCenters = CostCenterService::dropdown();
        $this->view('accounting/journal', [
            'title' => 'القيود اليومية',
            'entries' => $entries, 'accounts' => $accounts, 'costCenters' => $costCenters,
            'editEntry' => null, 'editLines' => [],
        ]);
    }

    public function editJournal(string $id): void
    {
        $entry = Database::query("SELECT * FROM journal_entries WHERE id = ?", [(int)$id])->fetch();
        if (!$entry) {
            $this->flash('danger', 'القيد غير موجود');
            Response::redirect('/accounting/journal');
        }
        $lines = Database::query(
            "SELECT l.*, a.code, a.name AS account_name FROM journal_lines l
             JOIN accounts a ON a.id = l.account_id
             WHERE l.entry_id = ? ORDER BY l.id",
            [(int)$id]
        )->fetchAll();
        $entries = Database::query(
            "SELECT je.*, cc.name AS cost_center_name, u.full_name FROM journal_entries je
             LEFT JOIN cost_centers cc ON cc.id = je.cost_center_id
             LEFT JOIN users u ON u.id = je.created_by
             ORDER BY je.id DESC LIMIT 100"
        )->fetchAll();
        $accounts = Database::query("SELECT id, code, name FROM accounts WHERE is_leaf = 1 AND is_active = 1 ORDER BY code")->fetchAll();
        $costCenters = CostCenterService::dropdown();
        $this->view('accounting/journal', [
            'title' => 'تعديل قيد يومي',
            'entries' => $entries, 'accounts' => $accounts, 'costCenters' => $costCenters,
            'editEntry' => $entry, 'editLines' => $lines,
        ]);
    }

    public function storeJournal(): void
    {
        $date = (string)$this->input('entry_date', date('Y-m-d'));
        $desc = (string)$this->input('description');
        $ccId = $this->input('cost_center_id') ? (int)$this->input('cost_center_id') : null;
        $accIds = (array)$this->input('account_id');
        $debits = (array)$this->input('debit');
        $credits = (array)$this->input('credit');
        $lineDescs = (array)$this->input('line_description');
        $lines = [];
        foreach ($accIds as $i => $aid) {
            if (!$aid) continue;
            $dr = (float)($debits[$i] ?? 0);
            $cr = (float)($credits[$i] ?? 0);
            if ($dr == 0 && $cr == 0) continue;
            $lines[] = ['account_id' => (int)$aid, 'debit' => $dr, 'credit' => $cr, 'description' => (string)($lineDescs[$i] ?? '')];
        }
        try {
            AccountingService::post([
                'entry_date' => $date, 'description' => $desc, 'cost_center_id' => $ccId,
                'lines' => $lines,
            ]);
            $this->flash('success', 'تم تسجيل القيد');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        Response::redirect('/accounting/journal');
    }

    public function reverseEntry(string $id): void
    {
        try {
            AccountingService::reverse((int)$id, (string)$this->input('reason'));
            $this->flash('success', 'تم عكس القيد');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        Response::redirect('/accounting/journal');
    }

    public function updateJournal(string $id): void
    {
        $date = (string)$this->input('entry_date', date('Y-m-d'));
        $desc = (string)$this->input('description');
        $ccId = $this->input('cost_center_id') ? (int)$this->input('cost_center_id') : null;
        $accIds = (array)$this->input('account_id');
        $debits = (array)$this->input('debit');
        $credits = (array)$this->input('credit');
        $lineDescs = (array)$this->input('line_description');
        $lines = [];
        foreach ($accIds as $i => $aid) {
            if (!$aid) continue;
            $dr = (float)($debits[$i] ?? 0);
            $cr = (float)($credits[$i] ?? 0);
            if ($dr == 0 && $cr == 0) continue;
            $lines[] = ['account_id' => (int)$aid, 'debit' => $dr, 'credit' => $cr, 'description' => (string)($lineDescs[$i] ?? '')];
        }
        try {
            AccountingService::update((int)$id, [
                'entry_date' => $date, 'description' => $desc, 'cost_center_id' => $ccId,
                'lines' => $lines,
            ]);
            $this->flash('success', 'تم تحديث القيد');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        Response::redirect('/accounting/journal');
    }

    public function importJournal(): void
    {
        if (empty($_FILES['excel']['tmp_name'])) {
            $this->flash('danger', 'لم يتم اختيار ملف Excel');
            Response::redirect('/accounting/journal');
        }
        try {
            $result = AccountingService::importJournal($_FILES['excel']['tmp_name']);
            if (!empty($result['errors'])) {
                $this->flash('danger', implode('<br>', $result['errors']));
            } else {
                $this->flash('success', 'تم استيراد ' . $result['created'] . ' قيد');
            }
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        Response::redirect('/accounting/journal');
    }

    public function journalTemplate(): void
    {
        AccountingService::downloadJournalTemplate();
    }

    public function assets(): void
    {
        $assets = Database::query(
            "SELECT fa.*, ac.name AS category_name, cc.name AS cost_center_name, b.name AS branch_name
             FROM fixed_assets fa
             LEFT JOIN asset_categories ac ON ac.id = fa.category_id
             LEFT JOIN cost_centers cc ON cc.id = fa.cost_center_id
             LEFT JOIN branches b ON b.id = fa.branch_id
             ORDER BY fa.id DESC LIMIT 100"
        )->fetchAll();
        $categories = Database::query("SELECT * FROM asset_categories WHERE is_active = 1 ORDER BY name")->fetchAll();
        $costCenters = CostCenterService::dropdown();
        $branches = Database::query("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name")->fetchAll();
        $this->view('accounting/assets', [
            'title' => 'الأصول الثابتة',
            'assets' => $assets, 'categories' => $categories,
            'costCenters' => $costCenters, 'branches' => $branches,
        ]);
    }

    public function storeAsset(): void
    {
        $name = trim((string)$this->input('name'));
        if (!$name) {
            $this->flash('danger', 'اسم الأصل مطلوب');
            Response::redirect('/accounting/assets');
        }
        try {
            $no = Helpers::docNumber('AST', 'fixed_assets', 'asset_no');
            Database::query(
                "INSERT INTO fixed_assets (asset_no, name, category_id, purchase_date, purchase_cost, salvage_value, life_years, depreciation_method, cost_center_id, branch_id, notes, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
                [$no, $name, $this->input('category_id') ?: null, (string)$this->input('purchase_date', date('Y-m-d')),
                 (float)$this->input('purchase_cost', 0), (float)$this->input('salvage_value', 0),
                 (int)$this->input('life_years', 5), (string)$this->input('depreciation_method', 'straight_line'),
                 $this->input('cost_center_id') ?: null, $this->input('branch_id') ?: null,
                 (string)$this->input('notes'), Auth::id()]
            );
            $this->flash('success', "تم إنشاء الأصل $no");
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        Response::redirect('/accounting/assets');
    }

    public function runDepreciation(): void
    {
        $period = (string)$this->input('period', date('Y-m'));
        $list = AccountingService::depreciateAll($period);
        $this->flash('success', 'تم إحلال إهلاك ' . count($list) . ' أصل');
        Response::redirect('/accounting/assets');
    }

    public function reports(): void
    {
        $period = (string)$this->input('period', date('Y-m'));
        $this->view('accounting/reports', [
            'title' => 'التقارير المالية',
            'period' => $period,
        ]);
    }

    public function trialBalance(): void
    {
        $to = (string)$this->input('to', date('Y-m-d'));
        $accounts = Database::query("SELECT id, code, name, type, opening_balance FROM accounts WHERE is_active = 1 ORDER BY code")->fetchAll();
        foreach ($accounts as &$a) {
            $a['balance'] = AccountingService::balance((int)$a['id'], $to);
        }
        unset($a);
        $format = (string)$this->input('format');
        if ($format === 'excel' || $format === 'pdf') {
            $rows = [];
            $tdr = $tcr = 0;
            foreach ($accounts as $a) {
                $bal = $a['balance'];
                $dr = in_array($a['type'], ['asset','expense']) ? max(0, $bal) : max(0, -$bal);
                $cr = in_array($a['type'], ['asset','expense']) ? max(0, -$bal) : max(0, $bal);
                $tdr += $dr; $tcr += $cr;
                $rows[] = [
                    $a['code'], $a['name'], $a['type'],
                    number_format($dr, 0), number_format($cr, 0), number_format($bal, 0),
                ];
            }
            $headers = ['الكود', 'الاسم', 'النوع', 'المدين', 'الدائن', 'الرصيد'];
            $totals = ['المجموع' => number_format($tdr, 0) . ' / ' . number_format($tcr, 0)];
            if ($format === 'excel') {
                ExportHelper::excel('trial_balance_' . $to . '.xlsx', [[
                    'title' => 'ميزان المراجعة', 'report_title' => 'ميزان المراجعة', 'period' => 'حتى ' . $to,
                    'headers' => $headers, 'rows' => $rows, 'totals' => $totals,
                ]]);
            }
            ExportHelper::pdf('trial_balance_' . $to . '.pdf', [
                'title' => 'ميزان المراجعة', 'period' => 'حتى ' . $to,
                'headers' => $headers, 'rows' => $rows, 'totals' => $totals,
            ]);
        }
        $this->viewBare('accounting/report_trial_balance', ['accounts' => $accounts, 'to' => $to]);
    }

    public function profitLoss(): void
    {
        $from = (string)$this->input('from', date('Y-m-01'));
        $to = (string)$this->input('to', date('Y-m-d'));
        $revenue = $this->groupTotal(['revenue'], $from, $to);
        $cogs = $this->groupTotal(['cogs'], $from, $to);
        $expenses = $this->groupTotal(['salaries','operating','depreciation','utilities','rent','marketing'], $from, $to);
        $gross = $revenue - $cogs;
        $net = $gross - $expenses;
        $format = (string)$this->input('format');
        if ($format === 'excel' || $format === 'pdf') {
            $headers = ['البند', 'المبلغ'];
            $rows = [
                ['إيرادات المبيعات', number_format($revenue, 0)],
                ['(-) تكلفة البضاعة المباعة', '(' . number_format($cogs, 0) . ')'],
                ['إجمالي الربح الأولي', number_format($gross, 0)],
                ['(-) المصروفات التشغيلية', '(' . number_format($expenses, 0) . ')'],
                ['صافي الدخل', number_format($net, 0)],
            ];
            $totals = [];
            if ($format === 'excel') {
                ExportHelper::excel('profit_loss_' . $from . '_' . $to . '.xlsx', [[
                    'title' => 'قائمة الدخل', 'report_title' => 'قائمة الدخل', 'period' => 'من ' . $from . ' إلى ' . $to,
                    'headers' => $headers, 'rows' => $rows, 'totals' => $totals,
                ]]);
            }
            ExportHelper::pdf('profit_loss_' . $from . '_' . $to . '.pdf', [
                'title' => 'قائمة الدخل', 'period' => 'من ' . $from . ' إلى ' . $to,
                'headers' => $headers, 'rows' => $rows, 'totals' => $totals,
            ]);
        }
        $this->viewBare('accounting/report_profit_loss', [
            'from' => $from, 'to' => $to,
            'revenue' => $revenue, 'cogs' => $cogs, 'expenses' => $expenses,
        ]);
    }

    public function balanceSheet(): void
    {
        $to = (string)$this->input('to', date('Y-m-d'));
        $assets = $this->groupTotal(['current_assets','inventory','receivables','prepaid','fixed_assets','accumulated_depreciation'], $to);
        $liabilities = $this->groupTotal(['payables','taxes','accruals'], $to);
        $equity = $this->groupTotal(['capital','retained','drawings'], $to);
        $format = (string)$this->input('format');
        if ($format === 'excel' || $format === 'pdf') {
            $headers = ['البند', 'المبلغ'];
            $rows = [
                ['الأصول', number_format($assets, 0)],
                ['الخصوم', '(' . number_format($liabilities, 0) . ')'],
                ['حقوق الملكية', '(' . number_format($equity, 0) . ')'],
                ['مجموع الخصوم + حقوق الملكية', number_format($liabilities + $equity, 0)],
                ['فحص الميزانية', number_format($assets - ($liabilities + $equity), 0)],
            ];
            $totals = [];
            if ($format === 'excel') {
                ExportHelper::excel('balance_sheet_' . $to . '.xlsx', [[
                    'title' => 'الميزانية العمومية', 'report_title' => 'الميزانية العمومية', 'period' => 'حتى ' . $to,
                    'headers' => $headers, 'rows' => $rows, 'totals' => $totals,
                ]]);
            }
            ExportHelper::pdf('balance_sheet_' . $to . '.pdf', [
                'title' => 'الميزانية العمومية', 'period' => 'حتى ' . $to,
                'headers' => $headers, 'rows' => $rows, 'totals' => $totals,
            ]);
        }
        $this->viewBare('accounting/report_balance_sheet', [
            'to' => $to, 'assets' => $assets, 'liabilities' => $liabilities, 'equity' => $equity,
        ]);
    }

    private function groupTotal(array $groups, ?string $from = null, ?string $to = null): float
    {
        $in = implode(',', array_fill(0, count($groups), '?'));
        $params = $groups;
        $sql = "SELECT id FROM accounts WHERE account_group IN ($in) AND is_active = 1 AND is_leaf = 1";
        $accounts = Database::query($sql, $params)->fetchAll();
        $total = 0;
        foreach ($accounts as $a) {
            $total += AccountingService::balance((int)$a['id'], $to);
        }
        return $total;
    }

    public function ledger(): void
    {
        $accountId = (int)$this->input('account_id');
        $from = (string)$this->input('from', date('Y-m-01'));
        $to = (string)$this->input('to', date('Y-m-d'));
        $account = $accountId ? Database::query("SELECT * FROM accounts WHERE id = ?", [$accountId])->fetch() : null;
        $lines = [];
        if ($account) {
            $lines = Database::query(
                "SELECT l.*, e.entry_no, e.entry_date, e.description, cc.name AS cost_center_name
                 FROM journal_lines l
                 JOIN journal_entries e ON e.id = l.entry_id
                 LEFT JOIN cost_centers cc ON cc.id = e.cost_center_id
                 WHERE l.account_id = ? AND e.status = 'posted' AND e.entry_date BETWEEN ? AND ?
                 ORDER BY e.entry_date, e.id",
                [$accountId, $from, $to]
            )->fetchAll();
        }
        $format = (string)$this->input('format');
        if ($account && ($format === 'excel' || $format === 'pdf')) {
            $running = (float)$account['opening_balance'];
            $normal = in_array($account['type'], ['asset','expense']) ? 1 : -1;
            $rows = [];
            $tDr = $tCr = 0;
            foreach ($lines as $l) {
                $running += $normal * ((float)$l['debit'] - (float)$l['credit']);
                $tDr += (float)$l['debit']; $tCr += (float)$l['credit'];
                $rows[] = [
                    $l['entry_date'], $l['entry_no'], $l['description'], $l['cost_center_name'] ?? '-',
                    number_format($l['debit'], 0), number_format($l['credit'], 0), number_format($running, 0),
                ];
            }
            $headers = ['التاريخ', 'رقم القيد', 'الوصف', 'مركز التكلفة', 'مدين', 'دائن', 'الرصيد'];
            $filename = 'ledger_' . $account['code'] . '_' . $from . '_' . $to;
            $totals = ['المجموع' => number_format($tDr, 0) . ' / ' . number_format($tCr, 0)];
            if ($format === 'excel') {
                ExportHelper::excel($filename . '.xlsx', [[
                    'title' => 'كشف حساب', 'report_title' => $account['code'] . ' - ' . $account['name'], 'period' => 'من ' . $from . ' إلى ' . $to,
                    'headers' => $headers, 'rows' => $rows, 'totals' => $totals,
                ]]);
            }
            ExportHelper::pdf($filename . '.pdf', [
                'title' => $account['code'] . ' - ' . $account['name'], 'period' => 'من ' . $from . ' إلى ' . $to,
                'headers' => $headers, 'rows' => $rows, 'totals' => $totals,
            ]);
        }
        $accounts = Database::query("SELECT id, code, name FROM accounts WHERE is_active = 1 ORDER BY code")->fetchAll();
        $this->view('accounting/ledger', [
            'title' => ' كشف حساب',
            'account' => $account, 'lines' => $lines, 'accounts' => $accounts,
            'from' => $from, 'to' => $to,
        ]);
    }

    /** تنزيل نموذج Excel لاستيراد الحسابات */
    public function accountsTemplate(): void
    {
        ImportHelper::downloadTemplate(
            ['code', 'name', 'type', 'parent_code', 'account_group', 'opening_balance'],
            'accounts_template.xlsx',
            ['1001', 'حساب تجريبي', 'asset', '1', 'current_assets', 0]
        );
    }

    /** استيراد الحسابات من Excel */
    public function importAccounts(): void
    {
        if (empty($_FILES['import_file']['tmp_name']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('danger', 'لم يتم اختيار ملف');
            Response::redirect('/accounting/accounts');
        }
        $read = ImportHelper::readFile($_FILES['import_file']['tmp_name']);
        if ($read['errors']) {
            ImportHelper::flashErrors($read['errors']);
            $this->flash('danger', 'خطأ في قراءة الملف');
            Response::redirect('/accounting/accounts');
        }
        $rows = $read['rows'];
        $errors = array_merge(
            ImportHelper::validateRequired($rows, ['code', 'name', 'type']),
            ImportHelper::validateUnique($rows, 'code'),
            ImportHelper::validateNotInDb($rows, 'code', 'accounts', 'code')
        );
        $validTypes = ['asset','liability','equity','revenue','expense'];
        foreach ($rows as $idx => $row) {
            if (!in_array(strtolower(trim((string)($row['type'] ?? ''))), $validTypes, true)) {
                $errors[] = "الصف " . ($idx + 2) . ': النوع غير صالح';
            }
        }
        if ($errors) {
            ImportHelper::flashErrors($errors);
            $this->flash('danger', 'يوجد ' . count($errors) . ' خطأ في الملف');
            Response::redirect('/accounting/accounts');
        }
        Database::beginTransaction();
        try {
            $count = 0;
            foreach ($rows as $row) {
                $code = trim((string)$row['code']);
                $parentCode = trim((string)($row['parent_code'] ?? ''));
                $parentId = null;
                if ($parentCode) {
                    $parent = Database::query("SELECT id FROM accounts WHERE code = ? LIMIT 1", [$parentCode])->fetch();
                    $parentId = $parent ? (int)$parent['id'] : null;
                }
                $type = strtolower(trim((string)$row['type']));
                Database::query(
                    "INSERT INTO accounts (code, name, type, parent_id, account_group, is_leaf, opening_balance)
                     VALUES (?,?,?,?,?,?,?)",
                    [$code, trim((string)$row['name']), $type, $parentId,
                     trim((string)($row['account_group'] ?? '')), 1, (float)($row['opening_balance'] ?? 0)]
                );
                if ($parentId) {
                    Database::query("UPDATE accounts SET is_leaf = 0 WHERE id = ?", [$parentId]);
                }
                $count++;
            }
            Database::commit();
            Helpers::audit('استيراد حسابات', 'accounts', 0, "created:$count");
            $this->flash('success', "تم استيراد $count حساب");
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->flash('danger', 'خطأ أثناء الحفظ: ' . $e->getMessage());
        }
        Response::redirect('/accounting/accounts');
    }

    /** استيراد شجرة حسابات من ملف Excel بالصيغة العربية (COA TOP) */
    public function importCoa(): void
    {
        if (empty($_FILES['import_file']['tmp_name']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('danger', 'لم يتم اختيار ملف');
            Response::redirect('/accounting/accounts');
        }
        $result = AccountingService::importCoa($_FILES['import_file']['tmp_name'], (int) Auth::id());
        if ($result['errors']) {
            ImportHelper::flashErrors($result['errors']);
            $this->flash('danger', 'يوجد أخطاء في الاستيراد');
        } else {
            $this->flash('success', "تم استيراد {$result['created']} حساب وتحديث {$result['updated']} حساب");
        }
        Response::redirect('/accounting/accounts');
    }

    public function consumption(): void
    {
        $filters = MaterialConsumptionService::filters();
        $from = $filters['from'];
        $to = $filters['to'];
        $itemId = $filters['item_id'];
        $branchId = $filters['branch_id'];
        $sectionId = $filters['section_id'];

        $tab = (string)($this->input('tab', 'sales'));
        $allowedTabs = ['sales', 'recipe', 'production', 'department', 'variance', 'waste'];
        if (!in_array($tab, $allowedTabs, true)) $tab = 'sales';

        $data = match ($tab) {
            'sales' => MaterialConsumptionService::bySales($from, $to, $itemId, $branchId),
            'recipe' => MaterialConsumptionService::byRecipe($from, $to, $itemId, $branchId),
            'production' => MaterialConsumptionService::byProduction($from, $to, $itemId),
            'department' => MaterialConsumptionService::byDepartment($from, $to, $sectionId, $branchId),
            'variance' => MaterialConsumptionService::varianceAnalysis($from, $to, $itemId),
            'waste' => MaterialConsumptionService::wasteDetails($from, $to, $itemId),
            default => [],
        };

        $this->view('accounting/consumption', [
            'title' => 'استهلاك المواد',
            'tab' => $tab,
            'filters' => $filters,
            'data' => $data,
            'items' => MaterialConsumptionService::itemsList(),
            'branches' => MaterialConsumptionService::branchesList(),
            'sections' => MaterialConsumptionService::sectionsList(),
        ]);
    }
}
