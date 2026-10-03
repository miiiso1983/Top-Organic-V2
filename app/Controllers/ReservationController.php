<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Auth, Response, ReservationService, ReservationTypeService, Helpers, WorkflowEngine, WorkflowTemplateService};

class ReservationController extends Controller
{
    public function index(): void
    {
        $filters = [
            'status' => (string)($_GET['status'] ?? ''),
            'date_from' => (string)($_GET['date_from'] ?? ''),
            'date_to' => (string)($_GET['date_to'] ?? ''),
            'branch_id' => (int)($_GET['branch_id'] ?? 0),
            'q' => (string)($_GET['q'] ?? ''),
        ];
        $reservations = ReservationService::list(array_filter($filters));
        $tables = Database::query(
            "SELECT *, COALESCE(max_capacity, capacity, seats) AS max_guests FROM dining_tables
             WHERE (status IN ('available','reserved') OR status IS NULL) AND allows_reservation != 0
             ORDER BY floor, section, name"
        )->fetchAll();
        $branches = Database::query("SELECT id, name FROM branches WHERE is_active=1 ORDER BY name")->fetchAll();

        $branchId = $filters['branch_id'] ?: (int)(Auth::branchId() ?? 0);
        $tomorrowDate = date('Y-m-d', strtotime('+1 day'));
        $tomorrow = ['total' => 0, 'large_groups' => 0];
        try {
            $tomorrow = Database::query(
                "SELECT
                    COUNT(*) AS total,
                    COUNT(CASE WHEN guests > 9 THEN 1 END) AS large_groups
                 FROM reservations
                 WHERE DATE(reserved_at) = ?
                   AND status NOT IN ('cancelled','no_show','rejected')
                   AND (? = 0 OR branch_id = ?)",
                [$tomorrowDate, $branchId, $branchId]
            )->fetch();
        } catch (\Throwable $e) {
            $tomorrow = ['total' => 0, 'large_groups' => 0, 'error' => $e->getMessage()];
        }

        $statusLabels = ['pending'=>'بانتظار التأكيد','confirmed'=>'مؤكد','seated'=>'تم الجلوس','cancelled'=>'ملغي','no_show'=>'لم يحضر','waiting'=>'قائمة انتظار','rejected'=>'مرفوض','completed'=>'مكتمل'];

        $this->view('sales/reservations', [
            'title' => 'إدارة الحجوزات',
            'reservations' => $reservations,
            'tables' => $tables,
            'branches' => $branches,
            'statusLabels' => $statusLabels,
            'filters' => $filters,
            'tomorrow' => $tomorrow,
        ]);
    }

    public function store(): void
    {
        if (!$this->canManage()) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/reservations');
        }
        try {
            ReservationService::create($this->reservationInput(), Auth::id());
            $this->flash('success', 'تم إنشاء الحجز');
        } catch (\Throwable $e) {
            $this->flash('danger', 'خطأ: ' . $e->getMessage());
        }
        Response::redirect('/reservations');
    }

    public function show(string $id): void
    {
        $res = ReservationService::getWithDetails((int)$id);
        if (!$res) {
            Response::redirect('/reservations');
        }
        $logs = Database::query(
            "SELECT * FROM reservation_logs WHERE reservation_id = ? ORDER BY created_at DESC",
            [(int)$id]
        )->fetchAll();
        $this->view('sales/reservation_show', [
            'title' => 'حجز #' . ($res['reservation_no'] ?? $id),
            'res' => $res, 'logs' => $logs,
        ]);
    }

    public function edit(string $id): void
    {
        $res = ReservationService::getWithDetails((int)$id);
        if (!$res) {
            Response::redirect('/reservations');
        }
        $tables = Database::query(
            "SELECT *, COALESCE(max_capacity, capacity, seats) AS max_guests FROM dining_tables
             WHERE allows_reservation != 0 ORDER BY floor, section, name"
        )->fetchAll();
        $branches = Database::query("SELECT id, name FROM branches WHERE is_active=1 ORDER BY name")->fetchAll();
        $statusLabels = ['pending'=>'بانتظار التأكيد','confirmed'=>'مؤكد','seated'=>'تم الجلوس','cancelled'=>'ملغي','no_show'=>'لم يحضر','waiting'=>'قائمة انتظار','rejected'=>'مرفوض','completed'=>'مكتمل'];
        $this->view('sales/reservation_edit', [
            'title' => 'تعديل حجز #' . ($res['reservation_no'] ?? $id),
            'res' => $res, 'tables' => $tables, 'branches' => $branches, 'statusLabels' => $statusLabels,
        ]);
    }

    public function update(string $id): void
    {
        if (!$this->canManage()) {
            $this->flash('danger', 'ليس لديك صلاحية');
            Response::redirect('/reservations');
        }
        try {
            ReservationService::update((int)$id, $this->reservationInput(), Auth::id());
            $this->flash('success', 'تم تحديث الحجز');
            $res = ReservationService::get((int)$id);
            $newStatus = (string)$this->input('status');
            if ($res && $newStatus && $newStatus !== $res['status']) {
                $this->applyStatusChange((int)$id, $newStatus, (string)$this->input('reason'));
                $this->flash('success', 'تم تحديث الحجز والحالة');
            }
        } catch (\Throwable $e) {
            $this->flash('danger', 'خطأ: ' . $e->getMessage());
        }
        Response::redirect('/reservations/' . $id);
    }

    public function updateStatus(string $id): void
    {
        if (!$this->canManage()) {
            Response::redirect('/reservations');
        }
        $status = (string)$this->input('status');
        $returnTo = (string)$this->input('return_to', '/reservations/' . $id);
        try {
            $this->applyStatusChange((int)$id, $status, (string)$this->input('reason'));
            $this->flash('success', 'تم تحديث حالة الحجز');
        } catch (\Throwable $e) {
            $this->flash('danger', 'خطأ: ' . $e->getMessage());
        }
        Response::redirect($returnTo ?: '/reservations/' . $id);
    }

    private function applyStatusChange(int $id, string $status, string $reason): void
    {
        match ($status) {
            'confirmed' => ReservationService::confirm($id, Auth::id()),
            'seated' => ReservationService::seat($id, Auth::id()),
            'completed' => ReservationService::complete($id, Auth::id()),
            'waiting' => ReservationService::moveToWaiting($id, Auth::id()),
            'cancelled' => ReservationService::cancel($id, $reason, Auth::id()),
            'rejected' => ReservationService::reject($id, $reason, Auth::id()),
            'pending' => true,
            default => throw new \RuntimeException('حالة غير معروفة'),
        };
    }

    public function reschedule(string $id): void
    {
        if (!$this->canManage()) {
            Response::redirect('/reservations');
        }
        try {
            ReservationService::reschedule((int)$id, (string)$this->input('reserved_at'), Auth::id());
            $this->flash('success', 'تم إعادة جدولة الحجز');
        } catch (\Throwable $e) {
            $this->flash('danger', 'خطأ: ' . $e->getMessage());
        }
        Response::redirect('/reservations/' . $id);
    }

    public function checkIn(string $id): void
    {
        if (!$this->canManage()) {
            Response::redirect('/reservations');
        }
        try {
            ReservationService::checkIn((int)$id, Auth::id());
            $this->flash('success', 'تم تسجيل دخول الضيوف');
        } catch (\Throwable $e) {
            $this->flash('danger', 'خطأ: ' . $e->getMessage());
        }
        Response::redirect('/reservations/' . $id);
    }

    public function checkOut(string $id): void
    {
        if (!$this->canManage()) {
            Response::redirect('/reservations');
        }
        try {
            ReservationService::checkOut((int)$id, Auth::id());
            $this->flash('success', 'تم تسجيل خروج الضيوف');
        } catch (\Throwable $e) {
            $this->flash('danger', 'خطأ: ' . $e->getMessage());
        }
        Response::redirect('/reservations/' . $id);
    }

    public function availableTables(): void
    {
        $guests = (int)($_GET['guests'] ?? 2);
        $at = (string)($_GET['reserved_at'] ?? '');
        $duration = (int)($_GET['duration'] ?? 90);
        $exclude = (int)($_GET['exclude'] ?? 0);
        $branchId = (int)($_GET['branch_id'] ?? 0);
        $tables = ReservationService::findAvailableTables($guests, $at, $duration, $exclude ?: null, $branchId ?: null);
        Response::json(['ok' => true, 'tables' => $tables]);
    }

    public function suggestTable(): void
    {
        $guests = (int)($_GET['guests'] ?? 2);
        $at = (string)($_GET['reserved_at'] ?? '');
        $duration = (int)($_GET['duration'] ?? 90);
        $type = (string)($_GET['type'] ?? 'regular');
        $exclude = (int)($_GET['exclude'] ?? 0);
        $branchId = (int)($_GET['branch_id'] ?? 0);
        $table = ReservationService::suggestBestTable($guests, $at, $duration, $type, $branchId ?: null, $exclude ?: null);
        Response::json(['ok' => true, 'table' => $table]);
    }

    public function floorPlan(): void
    {
        $branchId = (int)($_GET['branch_id'] ?? 0);
        $params = [];
        $sql = "SELECT t.*, r.id AS reservation_id, r.customer_name, r.guests, r.reserved_at, r.status AS res_status,
                       r.reservation_no, r.customer_phone, r.duration_minutes, rt.name_ar AS type_name
                FROM dining_tables t
                LEFT JOIN reservations r ON r.table_id = t.id AND r.status IN ('confirmed','seated','pending')
                LEFT JOIN reservation_types rt ON rt.code = r.reservation_type
                WHERE t.allows_reservation != 0 AND (t.parent_table_id IS NULL)";
        if ($branchId) {
            $sql .= " AND t.branch_id = ?";
            $params[] = $branchId;
        }
        $sql .= " ORDER BY t.floor, t.pos_y, t.pos_x";
        $tables = Database::query($sql, $params)->fetchAll();
        $floors = array_unique(array_column($tables, 'floor'));
        $branches = Database::query("SELECT id, name FROM branches WHERE is_active=1 ORDER BY name")->fetchAll();
        $this->view('sales/floor_plan', [
            'title' => 'مخطط الطاولات', 'tables' => $tables, 'floors' => $floors, 'branches' => $branches,
        ]);
    }

    public function logs(string $id): void
    {
        $logs = Database::query(
            "SELECT * FROM reservation_logs WHERE reservation_id = ? ORDER BY created_at DESC",
            [(int)$id]
        )->fetchAll();
        Response::json(['ok' => true, 'logs' => $logs]);
    }

    /** نموذج الحجز العام عبر الويب (بدون تسجيل دخول) */
    public function publicForm(): void
    {
        $branches = Database::query("SELECT id, name FROM branches WHERE is_active=1 ORDER BY name")->fetchAll();
        $types = ReservationTypeService::list(true);
        $this->viewBare('sales/book_public', ['saved' => false, 'branches' => $branches, 'types' => $types, 'form' => []]);
    }

    public function publicStore(): void
    {
        $b = $_POST;
        $branches = Database::query("SELECT id, name FROM branches WHERE is_active=1 ORDER BY name")->fetchAll();
        try {
            $id = ReservationService::publicBook($b);
            $res = ReservationService::getWithDetails($id);
            $this->viewBare('sales/book_public', [
                'saved' => true,
                'branches' => $branches,
                'reservation' => $res,
            ]);
        } catch (\Throwable $e) {
            $this->viewBare('sales/book_public', [
                'saved' => false,
                'branches' => $branches,
                'error' => $e->getMessage(),
                'form' => $b,
            ]);
        }
    }

    public function publicSlots(): void
    {
        $branchId = (int)($_GET['branch_id'] ?? 0);
        $date = (string)($_GET['date'] ?? '');
        $guests = (int)($_GET['guests'] ?? 2);
        $typeCode = (string)($_GET['type'] ?? 'regular');
        if (!$branchId || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            Response::json(['ok' => false, 'error' => 'بيانات غير كافية']);
            return;
        }
        $slots = ReservationService::getPublicSlots($branchId, $date, $guests, $typeCode);
        Response::json(['ok' => true, 'slots' => $slots]);
    }

    public function publicSendOtp(): void
    {
        $b = $this->jsonInput();
        $phone = (string)($b['customer_phone'] ?? '');
        $email = (string)($b['customer_email'] ?? '');
        if (!$phone) {
            Response::json(['ok' => false, 'error' => 'رقم الهاتف مطلوب']);
            return;
        }
        ReservationService::sendOtp($phone, $email);
        Response::json(['ok' => true, 'message' => 'تم إرسال رمز التحقق']);
    }

    private function canManage(): bool
    {
        return Auth::can('reservations.manage') || Auth::can('reservations.approve');
    }

    private function reservationInput(): array
    {
        return [
            'customer_id' => (int)$this->input('customer_id') ?: null,
            'customer_name' => (string)$this->input('customer_name'),
            'customer_phone' => (string)$this->input('customer_phone'),
            'customer_email' => (string)$this->input('customer_email'),
            'branch_id' => (int)$this->input('branch_id') ?: null,
            'table_id' => $this->input('table_id') ?: null,
            'guests' => (int)$this->input('guests', 2),
            'reserved_at' => (string)$this->input('reserved_at'),
            'duration_minutes' => (int)$this->input('duration_minutes', 90),
            'reservation_type' => (string)$this->input('reservation_type', 'dine_in'),
            'source' => (string)$this->input('source', 'call_center'),
            'notes' => (string)$this->input('notes'),
            'customer_notes' => (string)$this->input('customer_notes'),
        ];
    }
}
