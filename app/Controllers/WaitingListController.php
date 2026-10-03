<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Auth, Response, WaitingListService, ReservationService};

class WaitingListController extends Controller
{
    public function index(): void
    {
        if (!Auth::can('waiting_list.manage')) {
            Response::redirect('/dashboard');
        }
        $branchId = (int)($_GET['branch_id'] ?? Auth::branchId() ?? 0);
        $items = WaitingListService::list($branchId > 0 ? $branchId : null);
        $branches = Database::query("SELECT id, name FROM branches WHERE is_active=1 ORDER BY name")->fetchAll();
        $tables = Database::query(
            "SELECT t.*, COALESCE(t.merged_capacity, t.max_capacity, t.capacity, t.seats) AS max_guests
             FROM dining_tables t
             WHERE t.allows_reservation = 1 AND (t.status IN ('available','reserved','merged'))
               AND (? = 0 OR t.branch_id = ?)
             ORDER BY t.floor, t.section, t.name",
            [$branchId, $branchId]
        )->fetchAll();
        $this->view('sales/waiting_list', [
            'title' => 'قائمة الانتظار',
            'items' => $items,
            'branches' => $branches,
            'tables' => $tables,
            'branch_id' => $branchId,
        ]);
    }

    public function store(): void
    {
        if (!Auth::can('waiting_list.manage')) {
            Response::json(['ok' => false, 'error' => 'ليس لديك صلاحية']);
            return;
        }
        try {
            $id = WaitingListService::add($this->inputData(), Auth::id());
            Response::json(['ok' => true, 'id' => $id, 'message' => 'تم الإضافة لقائمة الانتظار']);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function directCheckIn(string $id): void
    {
        if (!Auth::can('waiting_list.manage')) {
            Response::json(['ok' => false, 'error' => 'ليس لديك صلاحية']);
            return;
        }
        $tableId = (int)$this->input('table_id');
        if (!$tableId) {
            Response::json(['ok' => false, 'error' => 'الطاولة مطلوبة']);
            return;
        }
        try {
            WaitingListService::directCheckIn((int)$id, $tableId, Auth::id());
            Response::json(['ok' => true, 'message' => 'تم تحويل العميل إلى طاولة بنجاح']);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function remove(string $id): void
    {
        if (!Auth::can('waiting_list.manage')) {
            Response::json(['ok' => false, 'error' => 'ليس لديك صلاحية']);
            return;
        }
        try {
            WaitingListService::remove((int)$id, Auth::id());
            Response::json(['ok' => true, 'message' => 'تمت الإزالة من قائمة الانتظار']);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function notify(string $id): void
    {
        if (!Auth::can('waiting_list.notify')) {
            Response::json(['ok' => false, 'error' => 'ليس لديك صلاحية']);
            return;
        }
        try {
            WaitingListService::notifyGuest((int)$id);
            Response::json(['ok' => true, 'message' => 'تم إرسال التنبيه']);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function suggestTable(string $id): void
    {
        if (!Auth::can('waiting_list.manage')) {
            Response::json(['ok' => false, 'error' => 'ليس لديك صلاحية']);
            return;
        }
        try {
            $table = WaitingListService::suggestTableFor((int)$id);
            Response::json(['ok' => true, 'table' => $table]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    private function inputData(): array
    {
        return [
            'customer_name' => (string)$this->input('customer_name'),
            'customer_phone' => (string)$this->input('customer_phone'),
            'customer_email' => (string)$this->input('customer_email'),
            'branch_id' => (int)$this->input('branch_id') ?: null,
            'party_size' => (int)$this->input('party_size', 2),
            'expected_arrival_at' => (string)$this->input('expected_arrival_at'),
            'duration_minutes' => (int)$this->input('duration_minutes', 90),
            'reservation_type' => (string)$this->input('reservation_type', 'dine_in'),
            'is_vip' => (int)$this->input('is_vip', 0),
            'notes' => (string)$this->input('notes'),
            'source' => 'walk_in',
        ];
    }
}
