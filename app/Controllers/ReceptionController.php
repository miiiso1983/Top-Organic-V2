<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Auth, Response, ReservationService, Helpers};

class ReceptionController extends Controller
{
    public function index(): void
    {
        if (!Auth::can('reception.manage')) {
            Response::redirect('/dashboard');
        }
        $branchId = (int)($_GET['branch_id'] ?? Auth::branchId() ?? 0);
        $date = (string)($_GET['date'] ?? date('Y-m-d'));
        $dashboard = ReservationService::receptionDashboard($branchId > 0 ? $branchId : null, $date);
        $branches = Database::query("SELECT id, name FROM branches WHERE is_active=1 ORDER BY name")->fetchAll();
        $tables = Database::query(
            "SELECT t.*, COALESCE(t.max_capacity, t.capacity, t.seats) AS max_guests
             FROM dining_tables t
             WHERE t.allows_reservation = 1 AND (? = 0 OR t.branch_id = ?)
             ORDER BY t.floor, t.section, t.name",
            [$branchId, $branchId]
        )->fetchAll();
        $statusLabels = ['pending'=>'بانتظار التأكيد','confirmed'=>'مؤكد','seated'=>'تم الجلوس','cancelled'=>'ملغي','no_show'=>'لم يحضر','waiting'=>'قائمة انتظار','rejected'=>'مرفوض','completed'=>'مكتمل'];

        $this->view('sales/reception', [
            'title' => 'شاشة الاستقبال',
            'dashboard' => $dashboard,
            'branches' => $branches,
            'tables' => $tables,
            'statusLabels' => $statusLabels,
        ]);
    }

    public function checkIn(string $id): void
    {
        if (!Auth::can('reception.manage')) {
            Response::json(['ok' => false, 'error' => 'ليس لديك صلاحية']);
            return;
        }
        try {
            ReservationService::checkIn((int)$id, Auth::id());
            Response::json(['ok' => true, 'message' => 'تم تسجيل وصول العميل']);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function noShow(string $id): void
    {
        if (!Auth::can('reception.manage')) {
            Response::json(['ok' => false, 'error' => 'ليس لديك صلاحية']);
            return;
        }
        try {
            ReservationService::noShow((int)$id, Auth::id());
            Response::json(['ok' => true, 'message' => 'تم تسجيل عدم حضور العميل']);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function assignTable(string $id): void
    {
        if (!Auth::can('reception.manage')) {
            Response::json(['ok' => false, 'error' => 'ليس لديك صلاحية']);
            return;
        }
        $tableId = (int)$this->input('table_id');
        if (!$tableId) {
            Response::json(['ok' => false, 'error' => 'الطاولة مطلوبة']);
            return;
        }
        try {
            ReservationService::update((int)$id, ['table_id' => $tableId], Auth::id());
            Response::json(['ok' => true, 'message' => 'تم تحويل العميل للطاولة']);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function complete(string $id): void
    {
        if (!Auth::can('reception.manage')) {
            Response::json(['ok' => false, 'error' => 'ليس لديك صلاحية']);
            return;
        }
        try {
            ReservationService::checkOut((int)$id, Auth::id());
            Response::json(['ok' => true, 'message' => 'تم إنهاء الحجز']);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function addNotes(string $id): void
    {
        if (!Auth::can('reception.manage')) {
            Response::json(['ok' => false, 'error' => 'ليس لديك صلاحية']);
            return;
        }
        $notes = trim((string)$this->input('notes'));
        try {
            ReservationService::addNotes((int)$id, $notes, Auth::id());
            Response::json(['ok' => true, 'message' => 'تم إضافة الملاحظات']);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }
}
