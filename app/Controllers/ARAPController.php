<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Auth, Response, ARAPService};

class ARAPController extends Controller
{
    public function receivables(): void
    {
        $customers = ARAPService::customers();
        foreach ($customers as &$c) {
            $c['balance'] = ARAPService::customerBalance((int)$c['id'])['balance'];
        }
        unset($c);
        $aging = ARAPService::aging('customer');
        $this->view('accounting/arap/receivables', ['title' => 'الحسابات المدينة', 'customers' => $customers, 'aging' => $aging]);
    }

    public function payables(): void
    {
        $suppliers = ARAPService::suppliers();
        foreach ($suppliers as &$s) {
            $s['balance'] = ARAPService::supplierBalance((int)$s['id'])['balance'];
        }
        unset($s);
        $aging = ARAPService::aging('supplier');
        $this->view('accounting/arap/payables', ['title' => 'الحسابات الدائنة', 'suppliers' => $suppliers, 'aging' => $aging]);
    }

    public function customerStatement(int $id): void
    {
        $data = ARAPService::customerStatement($id);
        if (!$data) { Response::abort(404); }
        $this->view('accounting/arap/statement', ['title' => 'كشف حساب عميل', 'party' => $data['customer'], 'lines' => $data['lines'], 'balance' => $data['balance'], 'type' => 'customer', 'openOrders' => ARAPService::openOrders('customer', $id)]);
    }

    public function supplierStatement(int $id): void
    {
        $data = ARAPService::supplierStatement($id);
        if (!$data) { Response::abort(404); }
        $this->view('accounting/arap/statement', ['title' => 'كشف حساب مورد', 'party' => $data['supplier'], 'lines' => $data['lines'], 'balance' => $data['balance'], 'type' => 'supplier', 'openOrders' => ARAPService::openOrders('supplier', $id)]);
    }

    public function storePayment(): void
    {
        $partyType = (string)$this->input('party_type');
        $partyId = (int)$this->input('party_id');
        try {
            $allocations = [];
            foreach ($_POST['allocations'] ?? [] as $key => $amt) {
                $amt = (float)$amt;
                if ($amt <= 0) continue;
                if ($partyType === 'customer' && str_starts_with($key, 'order_')) {
                    $allocations[] = ['order_id' => (int)substr($key, 6), 'amount' => $amt];
                } elseif ($partyType === 'supplier' && str_starts_with($key, 'po_')) {
                    $allocations[] = ['po_id' => (int)substr($key, 3), 'amount' => $amt];
                }
            }
            ARAPService::recordPayment($partyType, $partyId, (float)$this->input('amount'), (string)$this->input('date', date('Y-m-d')), (string)$this->input('method', 'cash'), (string)$this->input('reference'), (string)$this->input('description'), (int)Auth::id(), $allocations);
            $this->flash('success', 'تم تسجيل السند');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        Response::redirect('/accounting/' . ($partyType === 'customer' ? 'receivables' : 'payables'));
    }

    public function storeAdjustment(): void
    {
        $partyType = (string)$this->input('party_type');
        $partyId = (int)$this->input('party_id');
        try {
            ARAPService::recordAdjustment($partyType, $partyId, (float)$this->input('amount'), (string)$this->input('date', date('Y-m-d')), (string)$this->input('adjustment_type'), (string)$this->input('description'), (int)Auth::id());
            $this->flash('success', 'تم تسجيل التسوية');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        Response::redirect('/accounting/' . ($partyType === 'customer' ? 'receivables' : 'payables'));
    }

    public function agingReport(): void
    {
        $type = (string)$this->input('type', 'customer');
        if (!in_array($type, ['customer', 'supplier'], true)) $type = 'customer';
        $buckets = ARAPService::aging($type);
        $parties = ARAPService::agingByParty($type);
        $this->view('accounting/arap/aging', ['title' => 'أعمار الديون', 'type' => $type, 'buckets' => $buckets, 'parties' => $parties]);
    }

    public function overdueReport(): void
    {
        $type = (string)$this->input('type', 'customer');
        if (!in_array($type, ['customer', 'supplier'], true)) $type = 'customer';
        $days = max(1, (int)$this->input('days', 30));
        $report = ARAPService::overdue($type, $days);
        $this->view('accounting/arap/overdue', ['title' => 'تنبيهات الديون المتأخرة', 'type' => $type, 'days' => $days, 'report' => $report]);
    }
}
