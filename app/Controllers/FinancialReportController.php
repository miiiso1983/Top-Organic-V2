<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Response, Helpers, ExportHelper, FinancialReportService, CostCenterService};

class FinancialReportController extends Controller
{
    private function range(): array
    {
        $from = (string)($_GET['from'] ?? date('Y-m-01'));
        $to = (string)($_GET['to'] ?? date('Y-m-d'));
        return [$from, $to];
    }

    private function maybeExport(string $filename, array $sheets): void
    {
        $format = (string)($_GET['format'] ?? '');
        if ($format === 'excel') {
            ExportHelper::excel($filename . '.xlsx', $sheets);
        }
    }

    public function dashboard(): void
    {
        $this->view('reports/financial/dashboard', ['title' => 'لوحة المؤشرات المالية', 'data' => FinancialReportService::dashboard()]);
    }

    public function trialBalance(): void
    {
        [$from, $to] = $this->range();
        $data = FinancialReportService::trialBalance($from, $to);
        $this->maybeExport('trial_balance_' . $from . '_' . $to, [[
            'title' => 'ميزان المراجعة', 'report_title' => 'ميزان المراجعة', 'period' => "من {$from} إلى {$to}",
            'headers' => ['الكود', 'الحساب', 'النوع', 'مدين', 'دائن', 'الرصيد'],
            'rows' => array_map(fn($a) => [$a['code'], $a['name'], $a['type'], Helpers::money($a['debit']), Helpers::money($a['credit']), Helpers::money($a['balance'])], $data)
        ]]);
        $this->view('reports/financial/trial_balance', ['title' => 'ميزان المراجعة', 'from' => $from, 'to' => $to, 'accounts' => $data]);
    }

    public function incomeStatement(): void
    {
        [$from, $to] = $this->range();
        $data = FinancialReportService::incomeStatement($from, $to);
        $this->view('reports/financial/income_statement', ['title' => 'قائمة الدخل', 'from' => $from, 'to' => $to, 'data' => $data]);
    }

    public function balanceSheet(): void
    {
        $to = (string)($_GET['to'] ?? date('Y-m-d'));
        $data = FinancialReportService::balanceSheet($to);
        $this->view('reports/financial/balance_sheet', ['title' => 'الميزانية العمومية', 'to' => $to, 'data' => $data]);
    }

    public function cashFlow(): void
    {
        [$from, $to] = $this->range();
        $data = FinancialReportService::cashFlow($from, $to);
        $this->view('reports/financial/cash_flow', ['title' => 'التدفقات النقدية', 'from' => $from, 'to' => $to, 'data' => $data]);
    }

    public function generalLedger(): void
    {
        [$from, $to] = $this->range();
        $accountId = (int)($_GET['account_id'] ?? 0);
        $data = $accountId ? FinancialReportService::generalLedger($accountId, $from, $to) : ['account' => null, 'lines' => []];
        $accounts = Database::query("SELECT id, code, name FROM accounts WHERE is_active=1 ORDER BY code")->fetchAll();
        $this->view('reports/financial/general_ledger', ['title' => 'الأستاذ العام', 'from' => $from, 'to' => $to, 'accountId' => $accountId, 'accounts' => $accounts, 'data' => $data]);
    }

    public function bankStatement(): void
    {
        [$from, $to] = $this->range();
        $bankId = (int)($_GET['bank_id'] ?? 0);
        $data = $bankId ? FinancialReportService::bankStatement($bankId, $from, $to) : ['account' => null, 'lines' => []];
        $banks = Database::query("SELECT id, name FROM bank_accounts WHERE is_active=1 ORDER BY name")->fetchAll();
        $this->view('reports/financial/bank_statement', ['title' => 'كشف حساب بنكي', 'from' => $from, 'to' => $to, 'bankId' => $bankId, 'banks' => $banks, 'data' => $data]);
    }

    public function cashStatement(): void
    {
        [$from, $to] = $this->range();
        $registerId = (int)($_GET['register_id'] ?? 0);
        $data = $registerId ? FinancialReportService::cashStatement($registerId, $from, $to) : ['register' => null, 'lines' => []];
        $registers = Database::query("SELECT id, name FROM cash_registers WHERE is_active=1 ORDER BY name")->fetchAll();
        $this->view('reports/financial/cash_statement', ['title' => 'كشف صندوق', 'from' => $from, 'to' => $to, 'registerId' => $registerId, 'registers' => $registers, 'data' => $data]);
    }

    public function detailedTrialBalance(): void
    {
        [$from, $to] = $this->range();
        $data = FinancialReportService::detailedTrialBalance($from, $to);
        $this->view('reports/financial/detailed_trial_balance', ['title' => 'ميزان المراجعة التفصيلي', 'from' => $from, 'to' => $to, 'data' => $data]);
    }

    public function subledger(): void
    {
        [$from, $to] = $this->range();
        $type = (string)($_GET['party_type'] ?? 'customer');
        $partyId = (int)($_GET['party_id'] ?? 0);
        $data = $partyId ? FinancialReportService::subledger($type, $partyId, $from, $to) : ['party' => null, 'lines' => []];
        $parties = $type === 'customer'
            ? Database::query("SELECT id, name FROM customers ORDER BY name")->fetchAll()
            : Database::query("SELECT id, name FROM suppliers WHERE is_active=1 ORDER BY name")->fetchAll();
        $this->view('reports/financial/subledger', ['title' => 'الأستاذ المساعد', 'from' => $from, 'to' => $to, 'type' => $type, 'partyId' => $partyId, 'parties' => $parties, 'data' => $data]);
    }

    public function expenses(): void
    {
        [$from, $to] = $this->range();
        $ccId = (int)($_GET['cost_center_id'] ?? 0) ?: null;
        $data = FinancialReportService::expensesReport($from, $to, $ccId);
        $costCenters = CostCenterService::dropdown();
        $this->view('reports/financial/expenses', ['title' => 'تقرير المصروفات', 'from' => $from, 'to' => $to, 'costCenterId' => $ccId, 'costCenters' => $costCenters, 'data' => $data]);
    }

    public function revenue(): void
    {
        [$from, $to] = $this->range();
        $ccId = (int)($_GET['cost_center_id'] ?? 0) ?: null;
        $data = FinancialReportService::revenueReport($from, $to, $ccId);
        $costCenters = CostCenterService::dropdown();
        $this->view('reports/financial/revenue', ['title' => 'تقرير الإيرادات', 'from' => $from, 'to' => $to, 'costCenterId' => $ccId, 'costCenters' => $costCenters, 'data' => $data]);
    }

    public function ar(): void
    {
        $to = (string)($_GET['to'] ?? date('Y-m-d'));
        $data = FinancialReportService::arReport($to);
        $this->view('reports/financial/ar', ['title' => 'تقرير الذمم المدينة', 'to' => $to, 'data' => $data]);
    }

    public function ap(): void
    {
        $to = (string)($_GET['to'] ?? date('Y-m-d'));
        $data = FinancialReportService::apReport($to);
        $this->view('reports/financial/ap', ['title' => 'تقرير الذمم الدائنة', 'to' => $to, 'data' => $data]);
    }

    public function assets(): void
    {
        $data = FinancialReportService::assetsReport();
        $this->view('reports/financial/assets', ['title' => 'تقرير الأصول', 'data' => $data]);
    }

    public function depreciation(): void
    {
        [$from, $to] = $this->range();
        $data = FinancialReportService::depreciationReport($from, $to);
        $this->view('reports/financial/depreciation', ['title' => 'تقرير الإهلاكات', 'from' => $from, 'to' => $to, 'data' => $data]);
    }

    public function costCenters(): void
    {
        [$from, $to] = $this->range();
        $data = FinancialReportService::costCenterReport($from, $to);
        $this->view('reports/financial/cost_centers', ['title' => 'تقرير مراكز الكلفة', 'from' => $from, 'to' => $to, 'data' => $data]);
    }

    public function materialConsumption(): void
    {
        [$from, $to] = $this->range();
        $data = FinancialReportService::materialConsumption($from, $to);
        $this->view('reports/financial/material_consumption', ['title' => 'تقرير استهلاك المواد', 'from' => $from, 'to' => $to, 'data' => $data]);
    }

    public function profitabilityByBranch(): void
    {
        [$from, $to] = $this->range();
        $data = FinancialReportService::profitabilityByBranch($from, $to);
        $this->view('reports/financial/profitability_branch', ['title' => 'الربحية حسب الفرع', 'from' => $from, 'to' => $to, 'data' => $data]);
    }

    public function profitabilityByProduct(): void
    {
        [$from, $to] = $this->range();
        $data = FinancialReportService::profitabilityByProduct($from, $to);
        $this->view('reports/financial/profitability_product', ['title' => 'الربحية حسب المنتج', 'from' => $from, 'to' => $to, 'data' => $data]);
    }
}

