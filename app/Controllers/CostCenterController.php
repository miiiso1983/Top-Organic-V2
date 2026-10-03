<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Auth, Response, Helpers, CostCenterService};

class CostCenterController extends Controller
{
    public function index(): void
    {
        $this->view('finance/cost_centers/index', [
            'title' => 'مراكز الكلفة',
            'costCenters' => CostCenterService::all(),
            'tree' => CostCenterService::tree(),
            'users' => Database::query("SELECT id, full_name FROM users WHERE is_active=1 ORDER BY full_name")->fetchAll()
        ]);
    }

    public function store(): void
    {
        try {
            CostCenterService::create($_POST);
            $this->flash('success', 'تم إنشاء مركز الكلفة');
        } catch (\Throwable $e) { $this->flash('danger', $e->getMessage()); }
        Response::redirect('/finance/cost-centers');
    }

    public function edit(int $id): void
    {
        $center = CostCenterService::find($id);
        if (!$center) { Response::abort(404); }
        $this->view('finance/cost_centers/edit', [
            'title' => 'تعديل مركز الكلفة',
            'center' => $center,
            'costCenters' => CostCenterService::all(),
            'users' => Database::query("SELECT id, full_name FROM users WHERE is_active=1 ORDER BY full_name")->fetchAll()
        ]);
    }

    public function update(int $id): void
    {
        try {
            CostCenterService::update($id, $_POST);
            $this->flash('success', 'تم تحديث مركز الكلفة');
        } catch (\Throwable $e) { $this->flash('danger', $e->getMessage()); }
        Response::redirect('/finance/cost-centers');
    }

    public function delete(int $id): void
    {
        try {
            CostCenterService::delete($id);
            $this->flash('success', 'تم حذف مركز الكلفة');
        } catch (\Throwable $e) { $this->flash('danger', $e->getMessage()); }
        Response::redirect('/finance/cost-centers');
    }

    public function report(int $id): void
    {
        $center = CostCenterService::find($id);
        if (!$center) { Response::abort(404); }
        $from = (string)$this->input('from', date('Y-m-01'));
        $to = (string)$this->input('to', date('Y-m-d'));
        $this->view('finance/cost_centers/report', [
            'title' => 'تقرير مركز الكلفة: ' . $center['name'],
            'center' => $center,
            'from' => $from, 'to' => $to,
            'profitability' => CostCenterService::profitability($id, $from, $to),
            'transactions' => CostCenterService::transactions($id, $from, $to)
        ]);
    }

    public function allocations(): void
    {
        $this->view('finance/cost_centers/allocations', [
            'title' => 'توزيع المصروفات على مراكز الكلفة',
            'costCenters' => CostCenterService::active(),
            'expenses' => Database::query(
                "SELECT e.id, e.expense_no, e.category, e.amount, e.expense_date, cc.name AS cost_center_name
                 FROM expenses e
                 LEFT JOIN cost_centers cc ON cc.id = e.cost_center_id
                 WHERE e.status IN ('approved','paid')
                 ORDER BY e.id DESC LIMIT 100"
            )->fetchAll()
        ]);
    }

    public function storeAllocation(): void
    {
        try {
            $sourceId = (int)$this->input('source_id');
            $allocs = [];
            foreach ($_POST['allocations'] ?? [] as $costCenterId => $pct) {
                $pct = (float)$pct;
                if ($pct > 0) $allocs[] = ['cost_center_id' => (int)$costCenterId, 'percentage' => $pct];
            }
            CostCenterService::allocate('expense', $sourceId, $allocs);
            $this->flash('success', 'تم توزيع المصروف');
        } catch (\Throwable $e) { $this->flash('danger', $e->getMessage()); }
        Response::redirect('/finance/cost-centers/allocations');
    }

    public function link(): void
    {
        try {
            $table = (string)$this->input('table');
            $id = (int)$this->input('id');
            $ccId = $this->input('cost_center_id') ? (int)$this->input('cost_center_id') : null;
            CostCenterService::link($table, $id, $ccId);
            $this->flash('success', 'تم ربط العملية بمركز الكلفة');
        } catch (\Throwable $e) { $this->flash('danger', $e->getMessage()); }
        Response::redirect($_SERVER['HTTP_REFERER'] ?? '/finance/cost-centers');
    }
}
